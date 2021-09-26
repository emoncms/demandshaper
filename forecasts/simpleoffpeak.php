<?php

function get_list_entry_simpleoffpeak()
{
    return array(
        "category"=>"General",
        "name"=>"Simple Off-peak",
        "params"=>array(
            "peak"=>array("name"=>"Peak rate","type"=>"text","default"=>16.67),
            "offpeak"=>array("name"=>"Off peak rate","type"=>"text","default"=>5.0),
            "offpeakstart"=>array("name"=>"Off peak start","type"=>"text","default"=>0.5),
            "offpeakend"=>array("name"=>"Off peak end","type"=>"text","default"=>4.5),
        )
    );
}

function get_forecast_simpleoffpeak($redis,$params)
{
    $profile = array();
            
    $date = new DateTime();
    $date->setTimezone(new DateTimeZone($params->timezone));
    
    $profile = array();
    for ($time=$params->start; $time<$params->end; $time+=$params->interval) {
        $date->setTimestamp($time);
        $h = 1*$date->format('H');
        $m = 1*$date->format('i')/60;
        $h += $m;
        
        if ($h>=1*$params->offpeakstart && $h<1*$params->offpeakend) {
            $price = 1*$params->offpeak; 
        } else {
            $price = 1*$params->peak;
        }

        $profile[] = $price;
    }
    
    $result = new stdClass();
    $result->start = $params->start;
    $result->end = $params->end; 
    $result->interval = $params->interval;
    $result->profile = $profile;
    $result->optimise = MIN;
    return $result;
}
