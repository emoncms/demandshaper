<?php

/*
     All Emoncms code is released under the GNU Affero General Public License.
     See COPYRIGHT.txt and LICENSE.txt.

     ---------------------------------------------------------------------
     Emoncms - open source energy visualisation
     Part of the OpenEnergyMonitor project:
     http://openenergymonitor.org

*/

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class DemandShaper
{
    private $mysqli;
    private $redis;
    private $device;
    private $log;
    private $dir;
    private $default_device;
    
    public function __construct($mysqli,$redis,$device)
    {
        $this->log = new EmonLogger(__FILE__);
        $this->mysqli = $mysqli;
        $this->redis = $redis;
        $this->device = $device;
        
        global $linked_modules_dir;
        $this->dir = $linked_modules_dir;
    }
    
    public function get_devices($userid) {
        $devices_all = $this->device->get_list($userid);
        
        $devices = array();
        foreach ($devices_all as $d) {
            $name = $d["nodeid"];
            if (in_array($d['type'],array("openevse","smartplug","hpmon","wifirelay")))
                $devices[$name] = array("id"=>$d["id"]*1,"type"=>$d["type"]);
        }
        foreach ($devices_all as $d) {
            $name = $d["nodeid"];
            if (in_array($d['type'],array("emonth")))
                $devices[$name] = array("id"=>$d["id"]*1,"type"=>$d["type"]);
        }
        
        return $devices;
    }
    
    public function get_list($userid) {
        $devices = $this->get_devices($userid);
        
        if ($schedules = $this->redis->get("demandshaper:schedules:$userid")) {
            $schedules = json_decode($schedules);
        }
        
        foreach ($devices as $name=>$device) {
             $devices[$name]['custom_name'] = $name;
             if (isset($schedules->$name) && isset($schedules->$name->settings) && isset($schedules->$name->settings->name)) {
                 $devices[$name]['custom_name'] = $schedules->$name->settings->name;
             }
        }
        
        return $devices;
    }
    
    public function set($userid,$schedules)
    {
        // Basic validation
        $userid = (int) $userid;
        
        if ($schedules_old = $this->redis->get("demandshaper:schedules:$userid")) {
            $schedules_old = json_decode($schedules_old);
        }
        $this->redis->set("demandshaper:schedules:$userid",json_encode($schedules));
        
        // remove runtime settings
        $schedules_to_disk = json_decode(json_encode($schedules));
        if ($schedules_to_disk) {
            foreach ($schedules_to_disk as $device=>$schedule) {
                unset($schedules_to_disk->$device->runtime);
            }
        }
        
        // remove runtime settings
        $last_schedules_to_disk = $schedules_old;
        if ($last_schedules_to_disk) {
            foreach ($last_schedules_to_disk as $device=>$schedule) {
                unset($last_schedules_to_disk->$device->runtime);
            }
        }
        
        if (json_encode($schedules_to_disk)!=json_encode($last_schedules_to_disk)) {
        
            $schedules_to_disk = json_encode($schedules_to_disk);
        
            $result = $this->mysqli->query("SELECT `userid` FROM demandshaper WHERE `userid`='$userid'");
            if ($result->num_rows) {
                $stmt = $this->mysqli->prepare("UPDATE demandshaper SET `schedules`=? WHERE `userid`=?");
                $stmt->bind_param("si",$schedules_to_disk,$userid);
                if (!$stmt->execute()) {
                    return array('success'=>false, 'message'=>"Error saving demandshaper settings");
                }
                $this->log->error("Saved to disk");
                return array('success'=>true, 'message'=>"Saved to disk");
                
            } else {
                $stmt = $this->mysqli->prepare("INSERT INTO demandshaper (`userid`,`schedules`) VALUES (?,?)");
                $stmt->bind_param("is", $userid,$schedules_to_disk);
                if (!$stmt->execute()) {
                    return array('success'=>false, 'message'=>"Error saving demandshaper settings");
                }
                $this->log->error("Saved to disk");
                return array('success'=>true, 'message'=>"Saved to disk");
            }
        }
        $this->log->info("Saved to redis only");
        return array('success'=>true, 'message'=>"Saved to redis only");
    }
    
    public function get($userid)
    {
        $userid = (int) $userid;

        $devices = new stdClass();
        $this->default_device = $this->load_default();
        
        // Load schedules from demandshaper cache or mysql
        $demandshaper_devices = new stdClass();
        if ($demandshaper_devices_json = $this->redis->get("demandshaper:schedules:$userid")) {
            $demandshaper_devices = json_decode($demandshaper_devices_json);
        } else {
            $result = $this->mysqli->query("SELECT schedules FROM demandshaper WHERE `userid`='$userid'");
            if ($row = $result->fetch_object()) $demandshaper_devices = json_decode($row->schedules);
        }
        if (!$demandshaper_devices || !is_object($demandshaper_devices) || $demandshaper_devices==null) $demandshaper_devices = new stdClass();
        
        // Load device list from device module
        $device_module_devices = $this->get_devices($userid);
        
        // Copy over device schedules from demandshaper table
        foreach ($device_module_devices as $device_key=>$device) {
            if (isset($demandshaper_devices->$device_key)) {
                // Validate existing saved device: provides a way of upgrading the format
                $devices->$device_key = $this->validate_device($demandshaper_devices->$device_key,$device_key,$device["type"]);
            }
        }

        // Create new device schedule templates if they dont yet exist in the demandshaper table
        foreach ($device_module_devices as $device_key=>$device) {      
            if (!isset($demandshaper_devices->$device_key)) {
                $new_device = json_decode(json_encode($this->default_device));
                $new_device->settings->device_type = $device["type"];     
                $new_device->settings->device = $device_key;
                $new_device->settings->device_name = $device_key;
                // Copy over forecast settings from last device
                if ($last_device = end($devices)) {
                    $new_device->settings->forecast_config = $last_device->settings->forecast_config;
                }
                $devices->$device_key = $new_device;  
            }
        }
        // This will only write to disk if the content has changed
        $this->set($userid,$devices);
        
        return $devices;
    }
    
    public function validate_device($input,$device_key,$device_type) {
    
        $output = json_decode(json_encode($this->default_device));
        
        if (isset($input->settings)) {
            foreach ($output->settings as $key=>$val) {
                if (isset($input->settings->$key)) $output->settings->$key = $input->settings->$key;
            }
        }

        if (isset($input->runtime)) {
            foreach ($output->runtime as $key=>$val) {
                if (isset($input->runtime->$key)) $output->runtime->$key = $input->runtime->$key;
            }
        }
        
        $output->settings->device_type = $device_type;     
        $output->settings->device = $device_key;
        
        return $output;
    }
    
    public function load_default() {
        return json_decode(file_get_contents($this->dir."/demandshaper/demandshaper-module/default.json"));
    }
        
    public function fetch_ovms_v2($vehicleid,$carpass) {
        $csv_str = http_request("GET","https://dexters-web.de/api/call?fn.name=ovms/export&fn.vehicleid=$vehicleid&fn.carpass=$carpass&fn.format=csv&fn.types=D,S&fn.last=1",array());
        $csv_lines = explode("\n",$csv_str);

        $data = array("soc"=>20);
        if (count($csv_lines)>6) {
            $headings1 = explode(",",$csv_lines[1]);
            $data1 = explode(",",$csv_lines[2]);

            $headings2 = explode(",",$csv_lines[4]);
            $data2 = explode(",",$csv_lines[5]);

            for ($i=0; $i<count($headings1); $i++) {
                if (is_numeric($data1[$i])) $data1[$i] *= 1;
                $data[$headings1[$i]] = $data1[$i];
            }

            for ($i=0; $i<count($headings2); $i++) {
                if (is_numeric($data2[$i])) $data2[$i] *= 1;
                $data[$headings2[$i]] = $data2[$i];
            }
        }
        return $data;
    }

    public function get_forecast_list() {
        global $linked_modules_dir;
        $forecast_list = array();
        $dir = "$linked_modules_dir/demandshaper/forecasts";
        $forecasts = scandir($dir);
        for ($i=2; $i<count($forecasts); $i++) {
            if (is_file($dir."/".$forecasts[$i])) {
                require $dir."/".$forecasts[$i];
                $name = str_replace(".php","",$forecasts[$i]);
                $forecast_list_entry_fn = "get_list_entry_$name";
                $forecast_list[$name] = $forecast_list_entry_fn();
            }
        }
        return $forecast_list;
    }
    
    public function get_combined_forecast($config) {
        
        $params = new stdClass();
        $params->timezone = "Europe/London";

        // 1. Set desired forecast interval
        // This will downsample or upsample original forecast
        $params->interval = 1800;

        // 2. Get time now to set starting point
        $now = time();
        $params->start = floor($now/$params->interval)*$params->interval;
        $params->end = $params->start + (3600*24);

        $profile_length = ($params->end-$params->start)/$params->interval;        
        $combined = false;
        foreach ($config as $config_item) {
            $name = $config_item->name;
            if (file_exists($this->dir."/demandshaper/forecasts/$name.php")) {
                require_once $this->dir."/demandshaper/forecasts/$name.php";
                
                // Copy over params
                $fn = "get_list_entry_$name";
                $list_entry = $fn();
                foreach ($list_entry["params"] as $param_key=>$param) {
                    if (isset($config_item->$param_key)) {
                        $params->$param_key = $config_item->$param_key;
                    }
                }
                
                // Fetch forecast
                $fn = "get_forecast_$name";
                if ($forecast = $fn($this->redis,$params)) {
                    // Clone first, combine 2nd, 3rd etc
                    if ($combined==false) {
                        $combined = clone $forecast;
                        for ($td=0; $td<$profile_length; $td++) $combined->profile[$td] = 0;
                    }
                    
                    // Combine
                    for ($td=0; $td<$profile_length; $td++) {
                        $combined->profile[$td] += ($forecast->profile[$td]*$config_item->weight);
                    }
                }
            }
        }
        
        // Return a flat profile if none specified
        if ($combined==false) {
            $combined = new stdClass();
            $combined->start = $params->start;
            $combined->end = $params->end; 
            $combined->interval = $params->interval;
            $combined->profile = array();
            $combined->optimise = MIN;
            for ($td=0; $td<$profile_length; $td++) {
                $combined->profile[$td] = 1;
            }
        }
        
        return $combined;
    }
}
