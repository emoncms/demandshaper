<?php
class openevse
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
    
    public function get_time_offset() {
        return 0;
    }

    public function on($device) {
        $this->mqtt_client->publish($this->basetopic."/$device/rapi/in/\$ST","00 00 00 00",0);
        $this->mqtt_client->publish($this->basetopic."/$device/rapi/in/\$FE","",0);
    }
    
    public function off($device) {
        $this->mqtt_client->publish($this->basetopic."/$device/rapi/in/\$ST","00 00 00 00",0);
        $this->mqtt_client->publish($this->basetopic."/$device/rapi/in/\$FS","",0);
    }
    
    public function timer($device,$s1,$e1,$s2,$e2) {
        $this->mqtt_client->publish($this->basetopic."/$device/rapi/in/\$ST",time_conv_dec_str($s1," ")." ".time_conv_dec_str($e1," "),0);
    }
    
    public function send_state_request($device) {
        $this->mqtt_client->publish($this->basetopic."/$device/in/state","",0);
    }
    
    public function handle_state_response($schedule,$message,$timezone) {
        return $schedule;
    }
    
    /*
    public function timeleft_based_on_soc($schedule) {
        if ((time()-$last_soc_update)>600 && isset($schedule->settings->openevsecontroltype) && $schedule->settings->openevsecontroltype!='time') {
            $last_soc_update = time();
            
            if ($schedule->settings->openevsecontroltype=='socinput') {
                if ($feedid = $input->exists_nodeid_name($userid,$device,"soc")) {
                    $schedule->settings->ev_soc = $input->get_last_value($feedid)*0.01;
                    $log->error("Recalculating EVSE schedule based on emoncms input: ".$schedule->settings->ev_soc);
                }
            }
            else if ($schedule->settings->openevsecontroltype=='socovms') {
                if ($schedule->settings->ovms_vehicleid!='' && $schedule->settings->ovms_carpass!='') {
                    $ovms = $demandshaper->fetch_ovms_v2($schedule->settings->ovms_vehicleid,$schedule->settings->ovms_carpass);
                    if (isset($ovms['soc'])) $schedule->settings->ev_soc = $ovms['soc']*0.01;
                    $log->error("Recalculating EVSE schedule based on ovms: ".$schedule->settings->ev_soc);

                }
            }
            $kwh_required = ($schedule->settings->ev_target_soc-$schedule->settings->ev_soc)*$schedule->settings->batterycapacity;
            $schedule->settings->period = $kwh_required/$schedule->settings->chargerate;      
            
            if (isset($schedule->settings->balpercentage) && $schedule->settings->balpercentage < $schedule->settings->ev_target_soc) {
                $schedule->settings->period += $schedule->settings->baltime;
            }
                    
            $schedule->runtime->timeleft = $schedule->settings->period * 3600;
            $log->error("EVSE timeleft: ".$schedule->runtime->timeleft);                                    
        }
        return $schedule;
    }*/    
}
