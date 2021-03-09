<?php

function get_list_entry_demandprofile()
{
    return array(
        "category"=>"Demand Profile",
        "name"=>"Demand Profile",
        "params"=>array(
            "device_name"=>array("name"=>"Device name","type"=>"text")
        )
    );
}

function get_forecast_demandprofile($redis,$params)
{
    $timezone = new DateTimeZone($params->timezone);
    $forecast_interval = 1800;

    if ($demandshaper_devices_json = $redis->get("demandshaper:schedules:".$params->userid)) {
        $demandshaper_devices = json_decode($demandshaper_devices_json);
    }
    
    $device_schedules = array();    
    foreach ($demandshaper_devices as $name=>$device) {
        if ($name!=$params->device_name) {
            $device_schedules[] = $device->runtime->periods;
        }
    }

    $profile = array();
    for ($time=$params->start; $time<$params->end; $time+=$params->interval) {
        $forecast_time = floor($time / $forecast_interval) * $forecast_interval;
        
        $status = 0;
        foreach ($device_schedules as $device_schedule) {
            foreach ($device_schedule as $period) {
                $start = $period->start[0];
                $end = $period->end[0];
                if ($time>=$start && $time<$end) {
                    $status += 10;
                }
            }
        }
        
        $profile[] = $status;
    }
    
    $result = new stdClass();
    $result->start = $params->start;
    $result->end = $params->end; 
    $result->interval = $params->interval;
    $result->profile = $profile;
    $result->optimise = MIN;
    return $result;
}
