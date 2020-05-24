<?php

function get_list_entry_economy7()
{
    return array("category"=>"General","name"=>"Economy7");
}

function get_forecast_economy7($redis,$params)
{
    $profile = array();
            
    $date = new DateTime();
    $date->setTimezone(new DateTimeZone($params->timezone));
    
    $profile = array();
    for ($time=$params->start; $time<$params->end; $time+=$params->resolution) {
        $date->setTimestamp($time);
        $hour = 1*$date->format('H');
        if ($hour>=0.0 && $hour<7.0) $economy7 = 0.07; else $economy7 = 0.15;

        $profile[] = $economy7;
    }
    
    $result = new stdClass();
    $result->profile = $profile;
    $result->start = $params->start;
    $result->end = $params->end; 
    $result->interval = $params->resolution;
    $result->optimise = MIN;
    return $result;
}
