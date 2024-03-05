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
    // Private
    private $mysqli;
    private $redis;
    private $device;
    private $log;
    private $dir;
    private $default_device = false;
    
    // Public
    public $device_class_list = false;
    public $device_class = false;
    
    /**
     * Construct
     *
     * @param class $mysqli mysql instance
     * @param class $redis redis instance
     * @param class $device device class
    */
    public function __construct($mysqli,$redis,$device)
    {
        $this->log = new EmonLogger(__FILE__);
        $this->mysqli = $mysqli;
        $this->redis = $redis;
        $this->device = $device;
        
        global $linked_modules_dir;
        $this->dir = $linked_modules_dir;
        $this->device_class_list = $this->device_class_scan();
    }

    /**
     * Scan for supported device types
     * e.g: smartplug, wifirelay etc.
    */
    public function device_class_scan() {
        // Scan and auto load device classes
        $device_class_list = array();
        $devices_dir = $this->dir."/demandshaper/devices";
        $dir = scandir($devices_dir);
        for ($i=2; $i<count($dir); $i++) {
            $name_ext = explode(".",$dir[$i]);
            if (count($name_ext)==2) {
                if ($name_ext[1]=="php") $device_class_list[] = $name_ext[0];
            }
        }
        return $device_class_list;
    }

    /**
     * Load device classes (MQTT control, default device specific settings)
     *
     * @param class $mqtt_client MQTT instance
     * @param string $mqtt_basetopic MQTT base topic
    */
    public function load_device_classes($mqtt_client,$mqtt_basetopic) {
        // Scan and auto load device classes
        $this->device_class = array();
        foreach ($this->device_class_list as $device_type) {
            require $this->dir."/demandshaper/devices/$device_type.php";
            $this->device_class[$device_type] = new $device_type($mqtt_client,$mqtt_basetopic);
        }
        return $this->device_class;
    }

    /**
     * get_devices
     * used to populate sidebar menu and used below
     *
     * @param integer $userid
    */    
    public function get_devices($userid) {
        $devices_all = $this->device->get_list($userid);
        
        $devices = array();
        foreach ($devices_all as $d) {
            $name = $d["nodeid"];
            if (in_array($d['type'],$this->device_class_list))
                $devices[$name] = array("id"=>$d["id"]*1,"type"=>$d["type"]);
        }
        // foreach ($devices_all as $d) {
        //     $name = $d["nodeid"];
        //     if (in_array($d['type'],array("emonth")))
        //         $devices[$name] = array("id"=>$d["id"]*1,"type"=>$d["type"]);
        // }
        
        return $devices;
    }
    
    public function get_list($userid) {
        $devices = $this->get_devices($userid);
        
        if ($schedules = $this->redis->get("demandshaper:schedules:$userid")) {
            $schedules = json_decode($schedules);
        }
        
        foreach ($devices as $name=>$device) {
             $devices[$name]['device_name'] = $name;
             if (isset($schedules->$name) && isset($schedules->$name->settings) && isset($schedules->$name->settings->device_name)) {
                 $devices[$name]['device_name'] = $schedules->$name->settings->device_name;
             }
        }
        
        return $devices;
    }
    
    // -------------------------------------------------------------------------------
    
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
                $new_device = $this->validate_device(false,$device_key,$device["type"]); // creates a new device
                $new_device->settings->device_name = $device_key;
                // Copy over forecast settings from last device
                if ($last_device = end($devices)) {
                    $new_device->settings->forecast_config = $last_device->settings->forecast_config;
                }
                $devices->$device_key = $new_device;  
            }
        }
        // This will only write to disk if the content has changed
        // $this->set($userid,$devices);
        
        return $devices;
    }
    
    public function validate_device($input,$device_key,$device_type) {

        // Only load first time validate_device is called
        if (!$this->default_device) $this->load_default_device();
        if (!$this->device_class) $this->load_device_classes(false,false);
            
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
        // if ($output->settings->device_name=="default") $output->settings->device_name = $device_key;
        
        // Device specific settings
        if (isset($this->device_class[$device_type])) {
            $device_specific_settings = $this->device_class[$device_type]->default_settings();
            foreach ($device_specific_settings as $key=>$default_val) {
                if (isset($input->settings->$key)) {
                    $output->settings->$key = $input->settings->$key;
                } else {
                    $output->settings->$key = $default_val;
                }
            }
        }
        
        return $output;
    }
    
    public function load_default_device() {
        $this->default_device = json_decode(file_get_contents($this->dir."/demandshaper/demandshaper-module/default.json"));
        return $this->default_device;
    }

    // -------------------------------------------------------------------------------
        
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
    
    public function get_combined_forecast($config,$timezone) {
        
        $params = new stdClass();
        $params->timezone = $timezone;

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
                        
                        // do not set NordPool nulls to 0 as the values don't really exist
                        if($name!="nordpool") {
                            for ($td=0; $td<$profile_length; $td++) $combined->profile[$td] = 0;
                        }
                    } 
                    else {
                        for ($td=0; $td<$profile_length; $td++) {
                            if(isset($forecast->profile[$td])) {
                                if($combined->profile[$td] == null) {
                                    $combined->profile[$td] = 0;
                                } 
                                    
                                $combined->profile[$td] += ($forecast->profile[$td]*$config_item->weight);                                
                            }
                        }
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
    
    // -------------------------------------------------------------------------------
    
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
                if (isset($data1[$i])) {
                    if (is_numeric($data1[$i])) $data1[$i] *= 1;
                    $data[$headings1[$i]] = $data1[$i];
                }
            }

            for ($i=0; $i<count($headings2); $i++) {
                if (isset($data2[$i])) {
                    if (is_numeric($data2[$i])) $data2[$i] *= 1;
                    $data[$headings2[$i]] = $data2[$i];
                }
            }
        }
        return $data;
    }
}
