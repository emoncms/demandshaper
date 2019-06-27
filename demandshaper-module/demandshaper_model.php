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
    
    public function get_list($device,$userid) {
        $devices_all = $device->get_list($userid);
        $devices = array();
        foreach ($devices_all as $d) {
            $name = $d["nodeid"];
            if (in_array($d['type'],array("openevse","smartplug","hpmon")))
                $devices[$name] = array("id"=>$d["id"]*1,"type"=>$d["type"]);
        }
        foreach ($devices_all as $d) {
            $name = $d["nodeid"];
            if (in_array($d['type'],array("emonth")))
                $devices[$name] = array("id"=>$d["id"]*1,"type"=>$d["type"]);
        }
        return $devices;
    }
    
    public function set($userid,$schedules)
    {
        // Basic validation
        $userid = (int) $userid;
        $schedules = json_encode($schedules);
        
        if ($schedules_old = $this->redis->get("demandshaper:schedules")) {
            $schedules_old = json_decode($schedules_old);
        }
        $this->redis->set("demandshaper:schedules",$schedules);
        
        //$save_to_disk = array('timer_start1','timer_stop1','timer_start2','timer_stop2','ctrlmode','end','signal','interruptible','period','device','flowT','repeat','device_type');
        //$old = array();
        //$current = array();
        //foreach ($save_to_disk as $key) {
        //    $old
        //}
        
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
