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
    global $mysqli, $redis, $session, $route, $mqtt_server, $linked_modules_dir;
    $result = false;

    $route->format = "json";
    $result = false;

    $remoteaccess = false;
    
    include "Modules/demandshaper/demandshaper_model.php";
    $demandshaper = new DemandShaper($mysqli,$redis);
    
    require_once "Modules/device/device_model.php";
    $device = new Device($mysqli,$redis);
    
    switch ($route->action)
    {  
        case "":
            $route->format = "html";
            if ($session["write"]) {
                return view("Modules/demandshaper/view.php", array("remoteaccess"=>$remoteaccess));
            } else {
                // redirect to login
                return "";
            }
            break;
        
        case "submit":
            if (!$remoteaccess && $session["write"]) {
                $route->format = "json";
                
                if (isset($_GET['schedule'])) {
                    include "$linked_modules_dir/demandshaper/scheduler.php";
                    
                    $save = 1;
                    if (isset($_GET['save']) && $_GET['save']==0) $save = 0;
                    
                    $schedule = json_decode($_GET['schedule']);
                    
                    if (!isset($schedule->device)) return array("content"=>"Missing device parameter in schedule object");
                    if (!isset($schedule->end)) return array("content"=>"Missing end parameter in schedule object");
                    if (!isset($schedule->period)) return array("content"=>"Missing period parameter in schedule object");
                    if (!isset($schedule->interruptible)) return array("content"=>"Missing interruptible parameter in schedule object");
                    if (!isset($schedule->runonce)) return array("content"=>"Missing runonce parameter in schedule object");
                    if ($schedule->runonce) $schedule->runonce = time();
                    
                    $device = $schedule->device;
                    $schedules = $demandshaper->get($session["userid"]);
                    $last_schedule = $schedules->$device;

                    // -------------------------------------------------
                    // Calculate time left
                    // -------------------------------------------------
                    $now = time();
                    $date = new DateTime();
                    $date->setTimezone(new DateTimeZone("Europe/London"));
                    $date->setTimestamp($now);
                    $date->modify("midnight");
                    
                    $end_time = floor($schedule->end / 0.5) * 0.5;
                    $end_timestamp = $date->getTimestamp() + $end_time*3600;
                    if ($end_timestamp<$now) $end_timestamp+=3600*24;
                    
                    if ($schedule->end!=$last_schedule->end || $schedule->period!=$last_schedule->period) {
                        $schedule->timeleft = $schedule->period * 3600;
                    } else {
                        $schedule->timeleft = $last_schedule->timeleft;
                    }
                    
                    $timeleft = $end_timestamp - $now;
                    if ($schedule->timeleft>$timeleft) $schedule->timeleft = $timeleft;
                    // -------------------------------------------------
                    
                    $result = schedule($redis,$schedule);
                    
                    $schedule->periods = $result["periods"];
                    $schedule->probability = $result["probability"];
                    
                    if ($save) {
                        $schedules->$device = $schedule;
                        $demandshaper->set($session["userid"],$schedules);
                        $redis->set("demandshaper:trigger",1);
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
                        // Calculate an empty schedule to show in graph view
                        include "$linked_modules_dir/demandshaper/scheduler.php";
                        $schedule = new stdClass();
                        $schedule->end = 0;
                        $schedule->period = 0;
                        $schedule->interruptible = 0;
                        $schedule->runonce = 0;
                        $schedule = schedule($redis,$schedule);
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
                    $mqtt_request = new MQTTRequest($mqtt_server);
                    
                    if ($schedules->$device->device_type=="hpmon" || $schedules->$device->device_type=="smartplug") {
                        
                        if ($result = json_decode($mqtt_request->request("emon/$device/in/state","","emon/$device/out/state"))) {
                            $state->ctrl_mode = $result->ctrlmode;
                            $timer_parts = explode(" ",$result->timer);
                            $state->timer_start1 = conv_time($timer_parts[0]);
                            $state->timer_stop1 = conv_time($timer_parts[1]);
                            $state->timer_start2 = conv_time($timer_parts[2]);
                            $state->timer_stop2 = conv_time($timer_parts[3]);
                            $state->voltage_output = $result->vout*1;
                            return $state;
                        } else {
                            return false;
                        }
                            
                    } else if ($schedules->$device->device_type=="openevse") {
                        
                        $valid = true;
                        
                        // Get OpenEVSE timer state
                        if ($result = $mqtt_request->request("emon/$device/rapi/in/\$GD","","emon/$device/rapi/out")) {
                            $ret = explode(" ",substr($result,4,11));
                            $state->timer_start1 = $ret[0]+($ret[1]/60);
                            $state->timer_stop1 = $ret[2]+($ret[3]/60);
                            $state->timer_start2 = 0;
                            $state->timer_stop2 = 0;
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
                            else if ($ret[1]==1) {
                                if ($state->timer_start1==0 && $state->timer_stop1==0) {
                                    $state->ctrl_mode = "on";
                                } else {
                                    $state->ctrl_mode = "timer";
                                }
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

                $headings1 = explode(",",$csv_lines[1]);
                $data1 = explode(",",$csv_lines[2]);

                $headings2 = explode(",",$csv_lines[4]);
                $data2 = explode(",",$csv_lines[5]);

                $data = array();

                for ($i=0; $i<count($headings1); $i++) {
                    if (is_numeric($data1[$i])) $data1[$i] *= 1;
                    $data[$headings1[$i]] = $data1[$i];
                }

                for ($i=0; $i<count($headings2); $i++) {
                    if (is_numeric($data2[$i])) $data2[$i] *= 1;
                    $data[$headings2[$i]] = $data2[$i];
                }

                return $data;
            }
        
            break;
    }
    
    return array('content'=>'#UNDEFINED#');
}

function conv_time($time) {
    $h = floor($time*0.01);
    $m = (($time*0.01) - $h)/0.6;
    return $h+$m;
}
