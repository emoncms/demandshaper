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
    global $mysqli, $redis, $session, $route, $homedir;
    $result = false;

    $route->format = "json";
    $result = false;

    $remoteaccess = false;

    include "Modules/demandshaper/demandshaper_model.php";
    $demandshaper = new DemandShaper($mysqli,$redis);
    
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
                    include "$homedir/demandshaper/scheduler.php";
                    
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
                        include "$homedir/demandshaper/scheduler.php";
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
                    $valid = true;
                    
                    if ($schedules->$device->device_type=="hpmon" || $schedules->$device->device_type=="smartplug") {
                     
                        if ($result = http_request("GET","http://".$schedules->$device->ip."/status",array())) {
                            $result = json_decode($result);
                            $state->ctrl_mode = $result->ctrl_mode;
                        } else {
                            $valid = false;
                        }
                        
                        if ($result = http_request("GET","http://".$schedules->$device->ip."/config",array())) {
                            $result = json_decode($result);
                            $state->timer_start1 = conv_time($result->timer_start1);
                            $state->timer_stop1 = conv_time($result->timer_stop1);
                            $state->timer_start2 = conv_time($result->timer_start2);
                            $state->timer_stop2 = conv_time($result->timer_stop2);
                            $state->voltage_output = 1*$result->voltage_output;
                        } else {
                            $valid = false;
                        }
                        
                    } else if ($schedules->$device->device_type=="openevse") {
                        $schedules->$device->ip = "192.168.1.152";
                        
                        // Get OpenEVSE timer state
                        if ($result = http_request("GET","http://".$schedules->$device->ip."/r?json=1&rapi=\$GD",array())) {
                            // ret: $OK 0 0 0 0^20, $OK 14 30 18 45^2E
                            $result = json_decode($result);
                            $ret = explode(" ",substr($result->ret,4,11));
                            
                            $state->timer_start1 = $ret[0]+($ret[1]/60);
                            $state->timer_stop1 = $ret[2]+($ret[3]/60);
                            $state->timer_start2 = 0;
                            $state->timer_stop2 = 0;
                        } else {
                            $valid = false;
                        }
                        
                        // Get OpenEVSE state
                        if ($result = http_request("GET","http://".$schedules->$device->ip."/r?json=1&rapi=\$GS",array())) {
                            // ret: $OK 1 18524^2B
                            $result = json_decode($result);
                            $ret = explode(" ",$result->ret);
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
                        
                        
                    }
                    
                    if ($valid) return $state; else return false;
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
