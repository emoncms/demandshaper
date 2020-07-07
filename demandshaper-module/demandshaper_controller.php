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

    define("MAX",1);
    define("MIN",0);

    $route->format = "json";
    $result = false;

    $remoteaccess = false;
    
    require_once "$linked_modules_dir/demandshaper/lib/misc.php";

    require_once "Modules/device/device_model.php";
    $device = new Device($mysqli,$redis);

    include "Modules/demandshaper/demandshaper_model.php";
    $demandshaper = new DemandShaper($mysqli,$redis,$device);
    

    
    if ($session['userid']) $timezone = $user->get_timezone($session['userid']);
    
    $forecast_list = $demandshaper->get_forecast_list();
    
    $basetopic = $settings['mqtt']['basetopic'];
    if (isset($settings['mqtt']['multiuser']) && $settings['mqtt']['multiuser'] && $session['userid']) {
        $basetopic .= "/".$session['userid'];
    }
        
    switch ($route->action)
    {
        case "":
            $route->format = "html";
            if ($session["write"]) {
                $schedules = $demandshaper->get($session["userid"]);
                if (isset($_GET['device'])) {
                    $device = $_GET['device'];
                    if (isset($schedules->$device)) {
                        return view("Modules/demandshaper/Views/main.php", array("forecast_list"=>$forecast_list,"schedule"=>$schedules->$device));
                    }
                }
            }
            break;

        case "add-device":
            $route->format = "html";
            if ($session["write"]) {
                return view("Modules/demandshaper/Views/add_device.php", array());
            }
            break;

        case "forecast_test":
            $route->format = "html";
            if ($session["write"]) {
                return view("Modules/demandshaper/Views/forecast_test.php", array("forecast_list"=>$forecast_list));
            }
            break;

        case "forecast-list":
            $route->format = "json";
            return $forecast_list;
                        
        case "forecast":
            if (isset($_POST['config'])) {
                $config = json_decode($_POST['config']);
                return $demandshaper->get_combined_forecast($config);
            } 
            break;
            
        case "schedule":
            if (isset($_POST['config'])) {
                $config = json_decode($_POST['config']);
                $combined = $demandshaper->get_combined_forecast($config);
                
                $period = (int) post('period');
                $end = (int) post('end');
                $interruptible = (int) post('interruptible');

                // Run schedule
                require_once "$linked_modules_dir/demandshaper/lib/scheduler2.php";
                $combined = forecast_calc_min_max($combined);
                if ($interruptible) {
                    return schedule_interruptible($combined,$period,$end,"Europe/London");
                } else {
                    return schedule_block($combined,$period,$end,"Europe/London");
                }
            }
            break;
            
        case "save": 
            if ($session["write"]) {
                if (isset($_POST['schedule']) || isset($_GET['schedule'])) {
                    $schedule = json_decode(prop('schedule'));
                    
                    if (!isset($schedule->settings->device)) return array("content"=>"Missing device parameter in schedule object");
                    $device = $schedule->settings->device;
                    
                    $schedules = $demandshaper->get($session["userid"]); 
                    $schedules->$device = $schedule;
                    $result = $demandshaper->set($session["userid"],$schedules);
                    $redis->rpush("demandshaper:trigger",$session["userid"]); 
                    return $result;
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
                return $demandshaper->get_list($session['userid']);
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
                    include "Modules/demandshaper/MQTTRequest.php";
                    $mqtt_request = new MQTTRequest($settings['mqtt']);
                    
                    $demandshaper->device_class[$schedules->$device->settings->device_type]->set_basetopic($settings['mqtt']['basetopic']);
                    return $demandshaper->device_class[$schedules->$device->settings->device_type]->get_state($mqtt_request,$device,$timezone);
                }
            }   
        
            break;
        
        // Fetch EV SOC from ovms API    
        case "ovms":
            if ($session["write"] && isset($_GET["vehicleid"]) && isset($_GET["carpass"])) {
                $route->format = "json";
                return $demandshaper->fetch_ovms_v2($_GET["vehicleid"],$_GET["carpass"]);
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
