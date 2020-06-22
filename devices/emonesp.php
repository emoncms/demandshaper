<?php
class emonesp
{
    private $mqtt_client = false;
    private $basetopic = "";

    public function __construct($mqtt_client,$basetopic) {
        $this->mqtt_client = $mqtt_client;
        $this->basetopic = $basetopic;
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
        $this->mqtt_client->publish($this->basetopic."/$device/in/ctrlmode","On",0);
    }
    
    public function off($device) {
        $this->mqtt_client->publish($this->basetopic."/$device/in/ctrlmode","Off",0);
    }
    
    public function timer($device,$s1,$e1,$s2,$e2) {
        $this->mqtt_client->publish($this->basetopic."/$device/in/ctrlmode","Timer",0);
        
        $timer_str = time_conv_dec_str($s1)." ".time_conv_dec_str($e1)." ".time_conv_dec_str($s2)." ".time_conv_dec_str($e2);
        $this->mqtt_client->publish($this->basetopic."/$device/in/timer",$timer_str,0);
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
        }
        
        else if ($message->topic==$this->basetopic."/$device/out/timer") {
            $timer = explode(" ",$p);
            $schedule->settings->timer_start1 = time_conv($timer[0],$timeOffset);
            $schedule->settings->timer_stop1 = time_conv($timer[1],$timeOffset);
            $schedule->settings->timer_start2 = time_conv($timer[2],$timeOffset);
            $schedule->settings->timer_stop2 = time_conv($timer[3],$timeOffset);
        
            $schedule->settings->flowT = ($timer[4]*0.0371)+7.14;
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
}
