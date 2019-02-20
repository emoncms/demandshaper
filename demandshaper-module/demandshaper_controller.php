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

    $remoteaccess = true;

    include "Modules/demandshaper/demandshaper_model.php";
    $demandshaper = new DemandShaper($mysqli,$redis);
    
    switch ($route->action)
    {  
        case "":
            if ($session["write"]) {
                $route->format = "html";
                return view("Modules/demandshaper/view.php", array("remoteaccess"=>$remoteaccess));
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
    }
    
    return array('content'=>'#UNDEFINED#');
}
