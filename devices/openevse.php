<?php
class openevse
{
    private $mqtt_client = false;
    private $basetopic = "";
    private $last_ctrlmode = array();
    private $last_timer = array();
    private $last_soc_update = 0;

    public function __construct($mqtt_client,$basetopic) {
        $this->mqtt_client = $mqtt_client;
        $this->basetopic = $basetopic;
    }
    
    public function default_settings() {
        $defaults = new stdClass();
        $defaults->soc_source = "ovms"; // time, energy, distance, input, ovms
        $defaults->battery_capacity = 40.0;
        $defaults->charge_rate = 7.5;
        $defaults->target_soc = 0.8;
        $defaults->current_soc = 0.2;
        $defaults->balpercentage = 0.9;
        $defaults->baltime = 2.0;
        $defaults->car_economy = 4.0;
        $defaults->charge_energy = 0.0;
        $defaults->charge_distance = 0.0;
        $defaults->distance_units = "miles";
        $defaults->ovms_vehicleid = "";
        $defaults->ovms_carpass = "";
        $defaults->divert_mode = 0;
        return $defaults;
    }
    
    public function set_basetopic($basetopic) {
        $this->basetopic = $basetopic;
    }
    
    public function get_time_offset() {
        return 0;
    }

    public function on($device) {
        $device = $this->basetopic."/$device";
        
        if (!isset($this->last_ctrlmode[$device])) $this->last_ctrlmode[$device] = "";
        $this->last_timer[$device] = "00 00 00 00";

        if ($this->last_ctrlmode[$device]!="on") {
            $this->last_ctrlmode[$device] = "on";
            $this->mqtt_client->publish("$device/rapi/in/\$ST","00 00 00 00",0);
            $this->mqtt_client->publish("$device/rapi/in/\$FE","",0);
            schedule_log("$device switch on");
        }
    }
    
    public function off($device) {
        $device = $this->basetopic."/$device";

        if (!isset($this->last_ctrlmode[$device])) $this->last_ctrlmode[$device] = "";
        $this->last_timer[$device] = "00 00 00 00";
        
        if ($this->last_ctrlmode[$device]!="off") {
            $this->last_ctrlmode[$device] = "off";
            $this->mqtt_client->publish("$device/rapi/in/\$ST","00 00 00 00",0);
            $this->mqtt_client->publish("$device/rapi/in/\$FS","",0);
            schedule_log("$device switch off");
        }
    }
    
    public function timer($device,$s1,$e1,$s2,$e2) {
        $device = $this->basetopic."/$device";
        $this->last_ctrlmode[$device] = "timer";
        
        $timer_str = time_conv_dec_str($s1," ")." ".time_conv_dec_str($e1," ");
        if (!isset($this->last_timer[$device])) $this->last_timer[$device] = "";
        
        if ($timer_str!=$this->last_timer[$device]) {
            $this->last_timer[$device] = $timer_str;
            $this->mqtt_client->publish("$device/rapi/in/\$ST",$timer_str,0);
            schedule_log("$device set timer $timer_str");
        }
    }
    
    public function set_divert_mode($device,$mode) {
        $device = $this->basetopic."/$device";
        
        $mode = (int) $mode;
        $mode += 1;
        
        if (!isset($this->last_divert_mode[$device])) $this->last_divert_mode[$device] = "";

        if ($this->last_divert_mode[$device]!=$mode) {
            $this->last_divert_mode[$device] = $mode;
            $this->mqtt_client->publish("$device/divertmode/set",$mode,0);
            schedule_log("$device divert mode $mode");
        }
    }
    
    public function send_state_request($device) {
        $this->mqtt_client->publish($this->basetopic."/$device/in/state","",0);
    }
    
    public function handle_state_response($schedule,$message,$timezone) {
        return false;
    }

    public function get_state($mqtt_request,$device,$timezone) {
        $valid = true;
        $state = new stdClass;
        
        // Get OpenEVSE timer state
        if ($result = $mqtt_request->request($this->basetopic."/$device/rapi/in/\$GD","",$this->basetopic."/$device/rapi/out")) {
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
        if ($result = $mqtt_request->request($this->basetopic."/$device/rapi/in/\$GS","",$this->basetopic."/$device/rapi/out")) {
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
            }
        } else {
            $valid = false;
        }

        if ($valid) return $state; else return false;
    }

    public function auto_update_timeleft($schedule) {
        $userid = 1;
        
        if ((time()-$this->last_soc_update)>600 && $schedule->settings->soc_source!='time') {
            $this->last_soc_update = time();
            
            if ($schedule->settings->soc_source=='input') {
                global $input;
                if ($feedid = $input->exists_nodeid_name($userid,"openevse","soc")) {
                    $schedule->settings->current_soc = $input->get_last_value($feedid)*0.01;
                    schedule_log("Recalculating EVSE schedule based on emoncms input: ".$schedule->settings->current_soc);
                }
                if ($feedid = $input->exists_nodeid_name($userid,"openevse","target_soc")) {
                    $schedule->settings->target_soc = $input->get_last_value($feedid)*0.01;
                    schedule_log("Recalculating EVSE schedule based on emoncms target soc input: ".$schedule->settings->target_soc);
                }
            }
            else if ($schedule->settings->soc_source=='ovms') {
                if ($schedule->settings->ovms_vehicleid!='' && $schedule->settings->ovms_carpass!='') {
                    global $demandshaper;
                    $ovms = $demandshaper->fetch_ovms_v2($schedule->settings->ovms_vehicleid,$schedule->settings->ovms_carpass);
                    if (isset($ovms['soc'])) $schedule->settings->current_soc = $ovms['soc']*0.01;
                    schedule_log("Recalculating EVSE schedule based on ovms: ".$schedule->settings->current_soc);
                }
            }
            $kwh_required = max(($schedule->settings->target_soc-$schedule->settings->current_soc)*$schedule->settings->battery_capacity,0);
            $schedule->settings->period = $kwh_required/$schedule->settings->charge_rate;      
            
            if (isset($schedule->settings->balpercentage) && $schedule->settings->balpercentage < $schedule->settings->target_soc) {
                $schedule->settings->period += $schedule->settings->baltime;
            }
                    
            $schedule->runtime->timeleft = $schedule->settings->period * 3600;
            schedule_log("EVSE timeleft: ".$schedule->runtime->timeleft);                                    
        }
        return $schedule;
    }
}
