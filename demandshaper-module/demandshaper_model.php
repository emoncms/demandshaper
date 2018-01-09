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
    
    public function __construct($mysqli,$redis)
    {
        $this->mysqli = $mysqli;
        $this->redis = $redis;
    }
    
    public function set($userid,$schedules)
    {
        // Basic validation
        $userid = (int) $userid;
        $schedules = json_encode($schedules);
        
        $result = $this->mysqli->query("SELECT `userid` FROM demandshaper WHERE `userid`='$userid'");
        if ($result->num_rows) {
            $stmt = $this->mysqli->prepare("UPDATE demandshaper SET `schedules`=? WHERE `userid`=?");
            $stmt->bind_param("si",$schedules,$userid);
            if (!$stmt->execute()) {
                return array('success'=>false, 'message'=>"Error saving demandshaper settings");
            }
            
            $this->redis->set("schedules",$schedules);
            return array('success'=>true);
            
        } else {
            $stmt = $this->mysqli->prepare("INSERT INTO demandshaper (`userid`,`schedules`) VALUES (?,?)");
            $stmt->bind_param("is", $userid,$schedules);
            if (!$stmt->execute()) {
                return array('success'=>false, 'message'=>"Error saving demandshaper settings");
            }
            $this->redis->set("schedules",$schedules);
            return array('success'=>true);
        }
    }
    
    public function get($userid)
    {
        $userid = (int) $userid;
        
        // Attempt first to load from cache
        $schedulesjson = $this->redis->get("schedules");
        
        if ($schedulesjson) {
            $schedules = json_decode($schedulesjson);
        } else {
            // Load from mysql
            $result = $this->mysqli->query("SELECT schedules FROM demandshaper WHERE `userid`='$userid'");
            if ($row = $result->fetch_object()) {
                $schedules = json_decode($row->schedules);
                $this->redis->set("schedules",json_encode($schedules));
            } else {
                $schedules = new stdClass();
            }
        }
        
        if (!$schedules || !is_object($schedules)) $schedules = new stdClass();
        
        return $schedules;
    }
}
