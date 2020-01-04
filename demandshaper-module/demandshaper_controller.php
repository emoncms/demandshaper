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

function demandshaper_controller()
{
    global $mysqli, $redis, $session, $route, $settings, $linked_modules_dir, $user;
    $result = false;

    $route->format = "json";
    $result = false;

    $remoteaccess = false;
    
    include "Modules/demandshaper/demandshaper_model.php";
    $demandshaper = new DemandShaper($mysqli,$redis);
    
    require_once "Modules/device/device_model.php";
    $device = new Device($mysqli,$redis);
    
    $forecast_list = $demandshaper->get_forecast_list();
        
    switch ($route->action)
    {  
        case "":
            $route->format = "html";
            if ($session["write"]) {
                $apikey = $user->get_apikey_write($session["userid"]);
                return view("Modules/demandshaper/view.php", array("remoteaccess"=>$remoteaccess, "apikey"=>$apikey, "forecast_list"=>$forecast_list));
            } else {
                // redirect to login
                return "";
            }
            break;
            
        case "forecast":
            $signal = "carbonintensity";
            if (isset($_GET['signal'])) $signal = $_GET['signal'];
            include "$linked_modules_dir/demandshaper/scheduler.php";
            return get_forecast($redis,$signal);
            break;
        
        case "submit":
            if (!$remoteaccess && $session["write"]) {
                $route->format = "json";
                
                if (isset($_POST['schedule']) || isset($_GET['schedule'])) {
                    include "$linked_modules_dir/demandshaper/scheduler.php";
                    
                    $save = 1;
                    if (isset($_GET['save']) && $_GET['save']==0) $save = 0;
                    if (isset($_POST['save']) && $_POST['save']==0) $save = 0;
                                    
                    $schedule = json_decode(prop('schedule'));
                    
                    if (!isset($schedule->settings->ctrlmode)) return array("content"=>"Missing ctrlmode parameter in schedule object");
                    if (!isset($schedule->settings->device)) return array("content"=>"Missing device parameter in schedule object");
                    if (!isset($schedule->settings->end)) return array("content"=>"Missing end parameter in schedule object");
                    if (!isset($schedule->settings->period)) return array("content"=>"Missing period parameter in schedule object");
                    if (!isset($schedule->settings->interruptible)) return array("content"=>"Missing interruptible parameter in schedule object");
                    if (!isset($schedule->settings->runonce)) return array("content"=>"Missing runonce parameter in schedule object");
                    if ($schedule->settings->runonce) $schedule->settings->runonce = time();
                    $device = $schedule->settings->device;
                    
                    $schedules = $demandshaper->get($session["userid"]);
                    
                    $last_schedule = false;
                    if (isset($schedules->$device)) $last_schedule = $schedules->$device;

                    // -------------------------------------------------
                    // Calculate time left
                    // -------------------------------------------------
                    $now = time();
                    $date = new DateTime();
                    $date->setTimezone(new DateTimeZone("Europe/London"));
                    $date->setTimestamp($now);
                    $date->modify("midnight");
                    
                    $end_time = floor($schedule->settings->end / 0.5) * 0.5;
                    $schedule->settings->end_timestamp = $date->getTimestamp() + $end_time*3600;
                    
                    if ($schedule->settings->end_timestamp<$now) $schedule->settings->end_timestamp+=3600*24;
                    
                    if (!$last_schedule || $schedule->settings->end!=$last_schedule->settings->end || $schedule->settings->period!=$last_schedule->settings->period) {
                        $schedule->runtime->timeleft = $schedule->settings->period * 3600;
                    } else {
                        $schedule->runtime->timeleft = $last_schedule->runtime->timeleft;
                    }
                    
                    $timeleft = $schedule->settings->end_timestamp - $now;
                    // if ($schedule->runtime->timeleft>$timeleft) $schedule->runtime->timeleft = $timeleft;
                    
                    $schedule_log_output = "";
                    
                    if ($schedule->settings->ctrlmode=="smart") {
                        $forecast = get_forecast($redis,$schedule->settings->signal);
                        $schedule->runtime->periods = schedule_smart($forecast,$schedule->runtime->timeleft,$schedule->settings->end,$schedule->settings->interruptible,900);
                        $schedule_log_output = "smart ".($schedule->runtime->timeleft/3600)." ".$schedule->settings->end;
                        
                    } else if ($schedule->settings->ctrlmode=="timer") {
                        $forecast = get_forecast($redis,$schedule->settings->signal);
                        $schedule->runtime->periods = schedule_timer(
                            $forecast, 
                            $schedule->settings->timer_start1,$schedule->settings->timer_stop1,$schedule->settings->timer_start2,$schedule->settings->timer_stop2,
                            900
                        );
                        $schedule_log_output = "timer ".$schedule->settings->timer_start1." ".$schedule->settings->timer_stop1." ".$schedule->settings->timer_start2." ".$schedule->settings->timer_stop2;
                    } 
                    
                    if ($save) {
                        $schedules->$device = $schedule;
                        $demandshaper->set($session["userid"],$schedules);
                        $redis->set("demandshaper:trigger",1);
                        schedule_log("$device schedule started ".$schedule_log_output);
                    }
                    
                    return array("schedule"=>$schedule);
                } else {
                    return "Schedule object not present";
                }
            }
            break;
            
        case "get":
            if (!$remoteaccess && $session["read"]) {
                $route->format = "json";
                if (isset($_GET['device'])) {
                    $schedules = $demandshaper->get($session["userid"]);
                    $device = $_GET['device'];
                    if (isset($schedules->$device)) $schedule = $schedules->$device;
                    else {
                        $schedule = new stdClass();
                    }
                    return array("schedule"=>$schedule);
                }
            }
            break;

        // Device list used for menu
        case "list":
            if (!$remoteaccess && $session["read"]) {
                $route->format = "json";
                return $demandshaper->get_list($device,$session['userid']);
            }
            break;

        case "schedules":
            if (!$remoteaccess && $session["read"]) {
                $route->format = "json";
                return $demandshaper->get($session["userid"]);
            }
            break;
            
        case "clearall":
            if (!$remoteaccess && $session["write"]) {
                $route->format = "text";
                $demandshaper->set($session["userid"],new stdClass());
                return "schedules cleared";
            }
            break;
            
        case "delete":
            if (!$remoteaccess && $session["write"] && isset($_GET['device'])) {
                $route->format = "json";
                $device = $_GET['device'];
                $schedules = $demandshaper->get($session["userid"]);
                if (isset($schedules->$device)) {
                    unset ($schedules->$device);
                    $demandshaper->set($session["userid"],$schedules);
                    return array("success"=>true, "message"=>"device deleted");
                } else {
                    return array("success"=>false, "message"=>"device does not exist");
                }
            }
            break;
        
        // This route fetches the device state directly from the smartplug, heatpump monitor using a http request
        // This is used to confirm in the UI that the device state was set correctly
        // It may be possible to transfer this to MQTT in future
        case "get-state":
            if (!$remoteaccess && $session["write"] && isset($_GET['device'])) {
                $route->format = "json";
                $device = $_GET['device'];
                $schedules = $demandshaper->get($session["userid"]);
                if (isset($schedules->$device)) {
                    $state = new stdClass;
                    
                    include "Modules/demandshaper/MQTTRequest.php";
                    $mqtt_request = new MQTTRequest($settings['mqtt']);
                    
                    if ($schedules->$device->settings->device_type=="hpmon" || $schedules->$device->settings->device_type=="smartplug" || $schedules->$device->settings->device_type=="wifirelay") {
                        
                        if ($result = json_decode($mqtt_request->request("emon/$device/in/state","","emon/$device/out/state"))) {
                            $state->ctrl_mode = $result->ctrlmode;
                            $timer_parts = explode(" ",$result->timer);
                            
                            $dateTimeZone = new DateTimeZone("Europe/London");
                            $date = new DateTime("now", $dateTimeZone);
                            $timeOffset = $dateTimeZone->getOffset($date) / 3600;
                            
                            $state->timer_start1 = conv_time($timer_parts[0],$timeOffset);
                            $state->timer_stop1 = conv_time($timer_parts[1],$timeOffset);
                            $state->timer_start2 = conv_time($timer_parts[2],$timeOffset);
                            $state->timer_stop2 = conv_time($timer_parts[3],$timeOffset);
                            $state->voltage_output = $result->vout*1;
                            return $state;
                        } else {
                            return false;
                        }
                            
                    } else if ($schedules->$device->settings->device_type=="openevse") {
                        
                        $valid = true;
                        
                        // Get OpenEVSE timer state
                        if ($result = $mqtt_request->request("emon/$device/rapi/in/\$GD","","emon/$device/rapi/out")) {
                            $ret = explode(" ",substr($result,4,11));
                            if (count($ret)==4) {
                                $state->timer_start1 = ((int)$ret[0])+((int)$ret[1]/60);
                                $state->timer_stop1 = ((int)$ret[2])+((int)$ret[3]/60);
                                $state->timer_start2 = 0;
                                $state->timer_stop2 = 0;
                            } else {
                                $valid = false;
                            }
                        } else {
                            $valid = false;
                        }
                        
                        // Get OpenEVSE state
                        if ($result = $mqtt_request->request("emon/$device/rapi/in/\$GS","","emon/$device/rapi/out")) {
                            $ret = explode(" ",$result);
                            if ($ret[1]==254) {
                                if ($state->timer_start1==0 && $state->timer_stop1==0) {
                                    $state->ctrl_mode = "off";
                                } else {
                                    $state->ctrl_mode = "timer";
                                }
                            } 
                            else if ($ret[1]==1 || $ret[1]==3) {
                                if ($state->timer_start1==0 && $state->timer_stop1==0) {
                                    $state->ctrl_mode = "on";
                                } else {
                                    $state->ctrl_mode = "timer";
                                }
                            } else if ($ret[1]==255) {
                                $state->ctrl_mode = "disabled";
                            }
                        } else {
                            $valid = false;
                        }
                        
                        if ($valid) return $state; else return false;
                    }
                }
            }   
        
            break;
        
        // Fetch EV SOC from ovms API    
        case "ovms":
            if ($session["write"] && isset($_GET["vehicleid"]) && isset($_GET["carpass"])) {
                $route->format = "json";
                
                $vehicleid = $_GET["vehicleid"];
                $carpass = $_GET["carpass"];

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
        
            break;

        case "log":
            if (!$remoteaccess && $session["write"]) {
                $route->format = "text";
                
                $filter = false;
                if (isset($_GET['filter'])) $filter = $_GET['filter'];
                if ($filter=="") $filter = false;
                
                $last_schedule = false;
                if (isset($_GET['last'])) $last_schedule = true;
                                
                if ($out = file_get_contents("/var/log/emoncms/demandshaper.log")) {
                    
                    $lines = explode("\n",$out);
                    $lines_out = "";
                    foreach ($lines as $line) {
                    
                        if ($filter===false) { 
                            $lines_out .= $line."\n";
                        } else if (strpos($line,$filter)!==false) {
                            if ($last_schedule && strpos($line,"schedule started")!==false) $lines_out = "";
                            $lines_out .= $line."\n";
                        } 
                    }
                    return $lines_out;
                }
            }
            break;   
    }   
    
    return array('content'=>'#UNDEFINED#');
}

function conv_time($time,$timeOffset) {
    $h = floor($time*0.01);
    $m = (($time*0.01) - $h)/0.6;
    $t = $h+$m+$timeOffset;
    if ($t<0.0) $t += 24.0;
    if ($t>=24.0) $t -= 24.0;
    return $t;
}

function schedule_log($message){
    if ($fh = @fopen("/var/log/emoncms/demandshaper.log","a")) {
        $now = microtime(true);
        $micro = sprintf("%03d",($now - ($now >> 0)) * 1000);
        $now = DateTime::createFromFormat('U', (int)$now); // Only use UTC for logs
        $now = $now->format("Y-m-d H:i:s").".$micro";
        @fwrite($fh,$now." | ".$message."\n");
        @fclose($fh);
    }
}
