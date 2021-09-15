<?php
class tasmota
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

    // Gets the offset for a timezone in decimal hours (e.g +01:30 returns 1.5)
    public function get_time_offset($timezone) {
        $dateTimeZone = new DateTimeZone($timezone);
        $date = new DateTime("now", $dateTimeZone);
        return $dateTimeZone->getOffset($date) / 3600;
    }

    public function on($device) {
      schedule_log("Tasmota $device switch on");

        if (!isset($this->last_ctrlmode[$device])) $this->last_ctrlmode[$device] = "";

        if ($this->last_ctrlmode[$device]!="on") {
            $this->last_ctrlmode[$device] = "on";
            // user/1/tasmota/cmnd/tasmota_AABB3F/POWER -m 'OFF'
            $this->mqtt_client->publish("$this->basetopic/$device/cmnd/timers","0",0);
            $this->mqtt_client->publish("$this->basetopic/$device/cmnd/POWER","ON",0);
            schedule_log("Tasmota $device switch on");
        }
    }

    public function off($device) {
      schedule_log("Tasmota $device switch off");

        if (!isset($this->last_ctrlmode[$device])) $this->last_ctrlmode[$device] = "";

        if ($this->last_ctrlmode[$device]!="off") {
            $this->last_ctrlmode[$device] = "off";
            $this->mqtt_client->publish("$this->basetopic/$device/cmnd/timers","0",0);
            $this->mqtt_client->publish("$this->basetopic/$device/cmnd/POWER","OFF",0);
            schedule_log("Tasmota $device switch off");
        }
    }


    public function timer($device,$s1,$e1,$s2,$e2) {

        // TODO - doesn't work with somevalues e.g. close to midnight during BST.
        schedule_log("Tasmota $device timer mode $s1 $e1 $s2 $e2");

        $payloads = [];
        $UTC = new DateTimeZone("UTC");
        $now = new DateTime("now", $UTC);
        foreach ([$s1, $e1, $s2, $e2] as $i => $t) {
          $dt = DateTime::createFromFormat("Hi", time_conv_dec_str($t), $UTC);
          schedule_log($dt->format('H:i'));

          $action = 1;
          if ($i % 2) {
            $action = 0;
          }
          schedule_log($now->format("Y-m-d\TH:i:s\Z")." - ".$dt->format('Y-m-d\TH:i:s\Z'));

          $dt->setTimezone(new DateTimeZone(date_default_timezone_get())); // TODO should get the remote timezone rather than assume it is the same as the server
          schedule_log($dt->format('H:i'));

          array_push($payloads, json_encode(array(
            "Enable" => 1,
            "Mode" => 0,
            "Time" => $dt->format("H:i"),
            "Days" => "SMTWTFS",
            "Repeat" => 1,
            "Action" => $action
          )));
        }

        schedule_log($payloads[0]);
        schedule_log($payloads[1]);
        schedule_log($payloads[2]);
        schedule_log($payloads[3]);

        if (!isset($this->last_ctrlmode[$device])) $this->last_ctrlmode[$device] = "";

        if ($this->last_ctrlmode[$device]!="timer") {
          $this->last_ctrlmode[$device] = "timer";
          foreach($payloads as $i => $payload) {
            $this->mqtt_client->publish("$this->basetopic/$device/cmnd/Timer".($i+1), $payload, 0);
          }
          $this->mqtt_client->publish("$this->basetopic/$device/cmnd/Timers", 1 ,0); // Enable timers
        }

       // $timer_str = time_conv_dec_str($s1)." ".time_conv_dec_str($e1)." ".time_conv_dec_str($s2)." ".time_conv_dec_str($e2);
       // if (!isset($this->last_timer[$device])) $this->last_timer[$device] = "";

        /* if ($timer_str!=$this->last_timer[$device]) {
            $this->last_timer[$device] = $timer_str;
            $this->mqtt_client->publish("$device/in/timer",$timer_str,0);
            schedule_log("Tasmota $device set timer $timer_str");
        } */
    }

    public function send_state_request($device) {
        schedule_log("Tasmota $device send state request");
        $this->mqtt_client->publish($this->basetopic."/$device/cmnd/Status8","",0);
        $this->mqtt_client->publish($this->basetopic."/$device/cmnd/Timers","",0);
    }

    public function handle_state_response($schedule,$message,$timezone) {

        $device = $schedule->settings->device;

        schedule_log("Tasmota $device handle_state_response $message->topic: $message->payload");
        schedule_log("$message->topic - $this->basetopic/$device/stat/STATUS0");
        if ($message->topic==$this->basetopic."/$device/stat/STATUS0") {
            $p = json_decode($message->payload);

            schedule_log("stat result: $message->payload");


            if (isset($p->StatusNET)) {
              if (isset($p->StatusNET->IPAddress)) {
                $schedule->settings->ip = $p->StatusNET->IPAddress;
              }
            }

            if (isset($p->POWER)) {
              if ($p->StatusSTS->POWER=="ON") {
                $schedule->settings->ctrlmode = "on";
                schedule_log("$device is on");
              }
              if ($p->StatusSTS->POWER=="OFF") {
                $schedule->settings->ctrlmode = "off";
                schedule_log("$device is off");
              }

             //   if ($p->POWER=="Timer" && $schedule->settings->ctrlmode!="smart") $schedule->settings->ctrlmode = "timer";
            }

            if (isset($p->Timers)) {
              if ($p->Timers == "ON") {
//                if ($timer->Enable == 1) { // check for mismatch on enabled, action etc.
                  $timeOffset = $this->get_time_offset($timezone);
                  if ($p->Timer1->Enable == 1) {
                    $schedule->settings->timer_start1 = time_conv($p->Timer1, $timeOffset);
                  }
                  if ($p->Timer1->Enable == 1) {
                    $schedule->settings->timer_stop1 = time_conv($p->Timer2, $timeOffset);
                  }
                  if ($p->Timer1->Enable == 1) {
                    $schedule->settings->timer_start2 = time_conv($p->Timer3, $timeOffset);
                  }
                  if ($p->Timer1->Enable == 1) {
                    $schedule->settings->timer_stop2 = time_conv($p->Timer4, $timeOffset);
                  }
              }
            }

            $schedule->runtime->last_update_from_device = time();
            return $schedule;
        } else if ($message->topic==$this->basetopic."/$device/stat/RESULT") {
          if (isset($p->Timers)) {
            if ($p->Timers == "ON") {
              //                if ($timer->Enable == 1) { // check for mismatch on enabled, action etc.
              $timeOffset = $this->get_time_offset($timezone);
              if ($p->Timer1->Enable == 1) {
                $schedule->settings->timer_start1 = time_conv($p->Timer1, $timeOffset);
              }
              if ($p->Timer1->Enable == 1) {
                $schedule->settings->timer_stop1 = time_conv($p->Timer2, $timeOffset);
              }
              if ($p->Timer1->Enable == 1) {
                $schedule->settings->timer_start2 = time_conv($p->Timer3, $timeOffset);
              }
              if ($p->Timer1->Enable == 1) {
                $schedule->settings->timer_stop2 = time_conv($p->Timer4, $timeOffset);
              }
            }
          }

          $schedule->runtime->last_update_from_device = time();
          return $schedule;
        }
        return false;
    }

    public function get_state($mqtt_request,$device,$timezone) {

      schedule_log("Tasmota $device switch on");

        $result = json_decode($mqtt_request->request($this->basetopic."/$device/cmnd/Status0","",$this->basetopic."/$device/stat/STATUS0"));
        if (!$result) {
          schedule_log("No result fetching state for $device");
          return false;
        }
      /*  $timers_result = json_decode($mqtt_request->request($this->basetopic."/$device/cmnd/Timers","",$this->basetopic."/$device/stat/RESULT"));
        if (!$result) {
          schedule_log("No result fetching timers for $device");
          return false;
        }
        $timezone_result = json_decode($mqtt_request->request($this->basetopic."/$device/cmnd/Timezone","",$this->basetopic."/$device/stat/RESULT"));
        if (!$timezone_result) {
          schedule_log("No result fetching timers for $device");
          return false;
        }
        $timezone_result->Timezone; */

        $state = new stdClass;
        if ($result->StatusSTS->POWER == "ON") {
          $state->ctrl_mode = "on";
        } else if ($result->StatusSTS->POWER == "OFF") {
          $state->ctrl_mode = "off";
        }


           /* $timer_parts = explode(" ",$result->timer);

            $dateTimeZone = new DateTimeZone($timezone);
            $date = new DateTime("now", $dateTimeZone);
            $timeOffset = $dateTimeZone->getOffset($date) / 3600;
            */

        /*
          "Timer1": {
          "Enable": 1,
            "Mode": 0,
            "Time": "03:00",
            "Window": 0,
            "Days": "1111111",
            "Repeat": 1,
            "Output": 1,
            "Action": 1
        },
        */




        $state->timer_start1 = conv_time($timer_parts[0],$timeOffset);
        $state->timer_stop1 = conv_time($timer_parts[1],$timeOffset);
        $state->timer_start2 = conv_time($timer_parts[2],$timeOffset);
        $state->timer_stop2 = conv_time($timer_parts[3],$timeOffset);
//        $state->voltage_output = $result->vout*1;*/
        return $state;
    }

    public function auto_update_timeleft($schedule) {
        return $schedule;
    }
}
