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
    
    switch ($route->action)
    {  
        case "":
            if ($session["write"]) {
                $route->format = "html";
                $result =  view("Modules/demandshaper/view.php", array());
            }
            break;
        
        case "submit":
            if ($session["write"]) {
                $route->format = "json";
                
                if (isset($_GET['schedule'])) {
                    include "$homedir/demandshaper/scheduler.php";
                    
                    $schedule = json_decode($_GET['schedule']);
                    
                    if (!isset($schedule->device)) return array("content"=>"Missing device parameter in schedule object");
                    if (!isset($schedule->end)) return array("content"=>"Missing end parameter in schedule object");
                    if (!isset($schedule->period)) return array("content"=>"Missing period parameter in schedule object");
                    if (!isset($schedule->interruptible)) return array("content"=>"Missing interruptible parameter in schedule object");
                    if (!isset($schedule->runonce)) return array("content"=>"Missing runonce parameter in schedule object");
                    
                    if ($schedule->runonce) $schedule->runonce = time();
                    
                    $result = schedule($schedule);
                    
                    $schedule->periods = $result["periods"];
                    $schedule->probability = $result["probability"];
                    
                    $device = $schedule->device;
                    
                    $schedules = json_decode($redis->get("schedules"));
                    if (!$schedules) $schedules = new stdClass();
                    $schedules->$device = $schedule;
                    $redis->set("schedules",json_encode($schedules));
                    
                    $result = array("schedule"=>$schedule);
                } else {
                    $result = "Schedule object not present";
                }
            }
            break;
            
        case "get":
            if ($session["write"]) {
                $route->format = "json";
                if (isset($_GET['device'])) {
                    $schedules = json_decode($redis->get("schedules"));
                    $device = $_GET['device'];
                    if (isset($schedules->$device)) $schedule = $schedules->$device;
                    else $schedule = array();
                    $result = array("schedule"=>$schedule);
                }
            }
            break;
    }
    
    return array("content"=>$result);   
}
