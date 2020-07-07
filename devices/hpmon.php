<?php 

class hpmon extends emonesp {

    public function default_settings() {
        $defaults = new stdClass();
        $defaults->flowT = 30.0;
        return $defaults;
    }
    
    public function set_flowT($device,$flowT) {
        $vout = round(($flowT-7.14)/0.0371);
        $this->mqtt_client->publish($this->basetopic."/$device/in/vout",$vout,0);
    }
}
