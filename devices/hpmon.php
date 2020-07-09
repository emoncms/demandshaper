<?php 

class hpmon extends emonesp {

    private $last_flowT = array();

    public function default_settings() {
        $defaults = new stdClass();
        $defaults->flowT = 30.0;
        return $defaults;
    }
    
    public function set_flowT($device,$flowT) {
        
        $device = $this->basetopic."/$device";
        
        if (!isset($this->last_flowT[$device])) $this->last_flowT[$device] = "";
        
        if ($flowT!=$this->last_flowT[$device]) {
            $this->last_flowT[$device] = $flowT;
            $vout = round(($flowT-7.14)/0.0371);
            $this->mqtt_client->publish("$device/in/vout",$vout,0);
            schedule_log("$device set voltage output $vout");
        }
    }
}
