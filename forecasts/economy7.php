<?php

function get_list_entry_economy7()
{
    return array("category"=>"General","name"=>"Economy7");
}

function get_forecast_economy7($redis,$start_timestamp,$resolution,$timezone)
{
    $profile = array();
            
    $date = new DateTime();
    $date->setTimezone(new DateTimeZone($timezone));
    
    $divisions = round(24*3600/$resolution);
    for ($i=0; $i<$divisions; $i++) {

        $date->setTimestamp($timestamp);
        $h = 1*$date->format('H');
        $m = 1*$date->format('i')/60;
        $hour = $h + $m;
        
        if ($hour>=0.0 && $hour<7.0) $economy7 = 0.07; else $economy7 = 0.15;
        
        $profile[] = array($timestamp*1000,$economy7,$hour);
        $timestamp += $resolution; 
    }
    
    $result = new stdClass();
    $result->profile = $profile;
    $result->optimise = MIN;
    return $result;
}
