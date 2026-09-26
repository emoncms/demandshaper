<?php
class openevse
{
    private $mqtt_client = false;
    private $basetopic = "";
    private $last_ctrlmode = array();
    private $last_timer = array();
    private $last_soc_update = 0;
    private $host = "openevse.local";
    private $device_host = array();

    public function __construct($mqtt_client,$basetopic) {
        $this->mqtt_client = $mqtt_client;
        $this->basetopic = $basetopic;
    }

    // Set the OpenEVSE IP address or hostname (from the device settings) used by the http api calls
    public function set_host($device,$host) {
        $host = preg_replace('#^https?://#i','',trim($host));
        $host = rtrim($host,'/');
        if (!preg_match('/^[A-Za-z0-9.\-]+(:[0-9]{1,5})?$/',$host)) $host = "openevse.local";

        // Resend control state if the host has changed
        $device = $this->basetopic."/$device";
        if (isset($this->device_host[$device]) && $this->device_host[$device]!=$host) {
            unset($this->last_ctrlmode[$device]);
            unset($this->last_timer[$device]);
        }
        $this->device_host[$device] = $host;
        $this->host = $host;
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
            $result = $this->http_post('/override',array(
              'state' => 'active'
            ));
            schedule_log("$device switch on $result");
        }
    }
    
    public function off($device) {
        $device = $this->basetopic."/$device";

        if (!isset($this->last_ctrlmode[$device])) $this->last_ctrlmode[$device] = "";
        $this->last_timer[$device] = "00 00 00 00";
        
        if ($this->last_ctrlmode[$device]!="off") {
            $this->last_ctrlmode[$device] = "off";
            $result = $this->http_post('/override',array(
              'state' => 'disabled'
            ));
            schedule_log("$device switch off $result");
        }
    }
    
    public function timer($device,$s1,$e1,$s2,$e2) {
        $device = $this->basetopic."/$device";
        $this->last_ctrlmode[$device] = "timer";
        
        $timer_str = time_conv_dec_str($s1," ")." ".time_conv_dec_str($e1," ");
        if (!isset($this->last_timer[$device])) $this->last_timer[$device] = "";
        
        if ($timer_str!=$this->last_timer[$device]) {
            $this->last_timer[$device] = $timer_str;

            // clear override
            $this->http_delete('/override');

            // get list of events
            $result = $this->http_get('/schedule');
            $events = json_decode($result);

            // if there are more than 2 events, delete the rest
            if (count($events) > 2) {
                foreach ($events as $event) {
                    if ($event->id > 2) {
                        $result = $this->http_delete('/schedule/'.$event->id);
                    }
                }
            }

            // set event 1
            $active_time_str = time_conv_dec_str($s1,":").":00";
            $result = $this->http_post('/schedule/1',array(
              'state' => 'active',
              'time' => $active_time_str,
              'days' => ['sunday','monday','tuesday','wednesday','thursday','friday','saturday']
            ));

            // set event 2
            $disabled_time_str = time_conv_dec_str($e1,":").":00";
            $result = $this->http_post('/schedule/2',array(
              'state' => 'disabled',
              'time' => $disabled_time_str,
              'days' => ['sunday','monday','tuesday','wednesday','thursday','friday','saturday']
            ));

            schedule_log("$device set timer active ".$active_time_str." disabled ".$disabled_time_str);
        }
    }
    
    public function set_divert_mode($device,$mode) {
        $device = $this->basetopic."/$device";
        
        $mode = (int) $mode;
        $mode += 1;
        
        if (!isset($this->last_divert_mode[$device])) $this->last_divert_mode[$device] = "";

        if ($this->last_divert_mode[$device]!=$mode) {
            $this->last_divert_mode[$device] = $mode;
            // $this->mqtt_client->publish("$device/divertmode/set",$mode,0);
            schedule_log("$device divert mode not implemented");
        }
    }

    public function send_state_request($device) {
        return false;
    }
    
    public function handle_state_response($schedule,$message,$timezone) {
        return false;
    }

    public function get_state($mqtt_request,$device,$timezone) {
        $valid = true;
        $state = new stdClass;

        // Get OpenEVSE timer state using curl
        $result = $this->http_get('/schedule');
        $events = json_decode($result);

        // there should be 2 events
        if (count($events) == 2) {
            // split by :
            $parts = explode(":",$events[0]->time);
            $state->timer_start1 = ((int)$parts[0])+((int)$parts[1]/60);
            $parts = explode(":",$events[1]->time);
            $state->timer_stop1 = ((int)$parts[0])+((int)$parts[1]/60);
            $state->timer_start2 = 0;
            $state->timer_stop2 = 0;
        } else {
            $valid = false;
        }

        // Get OpenEVSE state 
        $result = $this->http_get('/override');
        $override = json_decode($result);
        if (isset($override->state)) {
            if ($override->state == "active") {
                $state->ctrl_mode = "on";
            } else if ($override->state == "disabled") {
                $state->ctrl_mode = "off";
            } else {
                $state->ctrl_mode = "timer";
            }
        } else {
            $state->ctrl_mode = "timer";
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
                    schedule_log("Recalculating EVSE schedule based on emoncms current soc input: ".$schedule->settings->current_soc);
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

    // curl post
    public function http_post($url,$data) {
        $url = "http://".$this->host.$url;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $headers = array(
          'Accept: application/json',
          'Content-Type: application/json',
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
          echo 'Error:' . curl_error($ch);
        }
        curl_close ($ch);
        return $result;
    }

    // curl get
    public function http_get($url) {
        $url = "http://".$this->host.$url;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
          echo 'Error:' . curl_error($ch);
        }
        curl_close ($ch);
        return $result;
    }

    // curl delete
    public function http_delete($url) {
        $url = "http://".$this->host.$url;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
          echo 'Error:' . curl_error($ch);
        }
        curl_close ($ch);
        return $result;
    }
}
