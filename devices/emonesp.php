<?php
class emonesp
{
    public $mqtt_client = false;
    public $basetopic = "";

    public $last_ctrlmode = array();
    public $last_timer = array();    
    public $last_flowT = array();

    public function __construct($mqtt_client,$basetopic) {
        $this->mqtt_client = $mqtt_client;
        $this->basetopic = $basetopic;
    }

    public function default_settings() {
        $defaults = new stdClass();
        return $defaults;
    }
    
    public function set_basetopic($basetopic) {
        $this->basetopic = $basetopic;
    }
    
    public function get_time_offset($timezone) {
        $dateTimeZone = new DateTimeZone($timezone);
        $date = new DateTime("now", $dateTimeZone);
        return $dateTimeZone->getOffset($date) / 3600;
    }

    public function on($device) {
        $device = $this->basetopic."/$device";
        
        if (!isset($this->last_ctrlmode[$device])) $this->last_ctrlmode[$device] = "";

        if ($this->last_ctrlmode[$device]!="on") {
            $this->last_ctrlmode[$device] = "on";
            $this->mqtt_client->publish("$device/in/ctrlmode","On",0);
            schedule_log("$device switch on");
        }
    }
    
    public function off($device) {
        $device = $this->basetopic."/$device";
        
        if (!isset($this->last_ctrlmode[$device])) $this->last_ctrlmode[$device] = "";

        if ($this->last_ctrlmode[$device]!="off") {
            $this->last_ctrlmode[$device] = "off";
            $this->mqtt_client->publish("$device/in/ctrlmode","Off",0);
            schedule_log("$device switch off");
        }
    }
    
    public function timer($device,$s1,$e1,$s2,$e2) {
        $device = $this->basetopic."/$device";
        
        if (!isset($this->last_ctrlmode[$device])) $this->last_ctrlmode[$device] = "";

        if ($this->last_ctrlmode[$device]!="timer") {
            $this->last_ctrlmode[$device] = "timer";
            $this->mqtt_client->publish("$device/in/ctrlmode","Timer",0);
            schedule_log("$device timer mode");
        }
        
        $timer_str = time_conv_dec_str($s1)." ".time_conv_dec_str($e1)." ".time_conv_dec_str($s2)." ".time_conv_dec_str($e2);
        if (!isset($this->last_timer[$device])) $this->last_timer[$device] = "";
        
        if ($timer_str!=$this->last_timer[$device]) {
            $this->last_timer[$device] = $timer_str;
            $this->mqtt_client->publish("$device/in/timer",$timer_str,0);
            schedule_log("$device set timer $timer_str");
        }
    }
    
    public function send_state_request($device) {
        $this->mqtt_client->publish($this->basetopic."/$device/in/state","",0);
    }
    
    public function handle_state_response($schedule,$message,$timezone) {
    
        $device = $schedule->settings->device;
        
        $timeOffset = $this->get_time_offset($timezone);
        
        if ($message->topic==$this->basetopic."/$device/out/state") {
            $p = json_decode($message->payload);

            if (isset($p->ip)) {
                $schedule->settings->ip = $p->ip;
            }
        
            if (isset($p->ctrlmode)) {
                if ($p->ctrlmode=="On") $schedule->settings->ctrlmode = "on";
                if ($p->ctrlmode=="Off") $schedule->settings->ctrlmode = "off";
                if ($p->ctrlmode=="Timer" && $schedule->settings->ctrlmode!="smart") $schedule->settings->ctrlmode = "timer";
            }

            if (isset($p->vout)) {
                $schedule->settings->flowT = ($p->vout*0.0371)+7.14;
                $this->last_flowT[$this->basetopic."/$device"] = $schedule->settings->flowT;
            }
            
            if (isset($p->timer)) {
                $timer = explode(" ",$p->timer);
                $schedule->settings->timer_start1 = time_conv($timer[0],$timeOffset);
                $schedule->settings->timer_stop1 = time_conv($timer[1],$timeOffset);
                $schedule->settings->timer_start2 = time_conv($timer[2],$timeOffset);
                $schedule->settings->timer_stop2 = time_conv($timer[3],$timeOffset);
            }
            
            $schedule->runtime->last_update_from_device = time();
        }
        
        else if ($message->topic==$this->basetopic."/$device/out/ctrlmode") {
            if ($p=="On") $schedule->settings->ctrlmode = "on";
            if ($p=="Off") $schedule->settings->ctrlmode = "off";
            if ($p=="Timer" && $schedule->settings->ctrlmode!="smart") $schedule->settings->ctrlmode = "timer";

        }
        
        else if ($message->topic==$this->basetopic."/$device/out/vout") {
            $schedule->flowT = ($p*0.0371)+7.14;
            $this->last_flowT = $schedule->flowT;
        }
        
        else if ($message->topic==$this->basetopic."/$device/out/timer") {
            $timer = explode(" ",$p);
            $schedule->settings->timer_start1 = time_conv($timer[0],$timeOffset);
            $schedule->settings->timer_stop1 = time_conv($timer[1],$timeOffset);
            $schedule->settings->timer_start2 = time_conv($timer[2],$timeOffset);
            $schedule->settings->timer_stop2 = time_conv($timer[3],$timeOffset);
        
            $schedule->settings->flowT[$this->basetopic."/$device"] = ($timer[4]*0.0371)+7.14;
        }
        
        // ----------------------------------------------------------------------
        
        if ($message->topic==$this->basetopic."/$device/in/ctrlmode") {
            $ctrlmode = strtolower($message->payload);
            if ($ctrlmode!=$schedule->settings->ctrlmode) {
                $schedule->settings->ctrlmode = $ctrlmode;
                schedule_log("$device external script correction of ctrlmode to $ctrlmode");
            }
        }
    
        return $schedule;
    }
    
    public function get_state($mqtt_request,$device,$timezone) {
    
        if ($result = json_decode($mqtt_request->request($this->basetopic."/$device/in/state","",$this->basetopic."/$device/out/state"))) {
            $state = new stdClass;
            $state->ctrl_mode = $result->ctrlmode;
            $timer_parts = explode(" ",$result->timer);
            
            $dateTimeZone = new DateTimeZone($timezone);
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
    }
    
    public function auto_update_timeleft($schedule) {
        return $schedule;
    }
}
