<?php

function get_list_entry_solarclimacell()
{
    return array(
        "category"=>"Solar",
        "name"=>"ClimaCell",
        "params"=>array(
            "lat"=>array("name"=>"Latitude","type"=>"text"),
            "lon"=>array("name"=>"Longitude","type"=>"text"),
            "apikey"=>array("name"=>"API key", "type"=>"text")
        )
    );
}

function get_forecast_solarclimacell($redis,$params)
{   
    if (!isset($params->lat)) return false;
    if (!isset($params->lon)) return false;
    if (!isset($params->apikey)) return false;
    
    $date = new DateTime();
    $date->setTimestamp($params->start);
    $start = $date->format("c");
    $date->setTimestamp($params->end);
    $end = $date->format("c");
    
    $forecast_interval = 3600;

    // 1. Load forecast from local cache if it exists
    //    otherwise load from climacell api
    //    expire cache every 1800 seconds to limit API calls       
    $key = "demandshaper:solarclimacell:".$params->lat.":".$params->lon;
    if (!$result = $redis->get($key)) {
        $req_params = array(
            "lat"=>$params->lat,
            "lon"=>$params->lon,
            "unit_system"=>"si",
            "start_time"=>$start,
            "end_time"=>$end,
            "fields"=>"surface_shortwave_radiation",
            "apikey"=>$params->apikey
        );
        if ($result = http_request("GET","https://api.climacell.co/v3/weather/forecast/hourly",$req_params)) {
            $redis->set($key,$result);
            $redis->expire($key,1800);
        }
    }
    $result = json_decode($result);
    
    // 2. Create associative array out of original forecast
    //    format: timestamp:value
    $timevalues = array();
    if ($result!=null) {
        foreach ($result as $hour) {
            $date = new DateTime($hour->observation_time->value);
            // $date->setTimezone($timezone);
            $timestamp = $date->getTimestamp();
            $timevalues[$timestamp] = $hour->surface_shortwave_radiation->value;
        }
    }
    
    // 3. Map forecast to request start, end and interval
    $profile = array();
    for ($time=$params->start; $time<$params->end; $time+=$params->interval) {
        $forecast_time = floor($time / $forecast_interval) * $forecast_interval;
        
        if (isset($timevalues[$forecast_time])) {
            $value = $timevalues[$forecast_time];
        } else if (isset($timevalues[$forecast_time-(24*3600)])) { // if not available try to use value 24h in past
            $value = $timevalues[$forecast_time-(24*3600)]; 
        } else if (isset($timevalues[$forecast_time+(24*3600)])) { // if not available try to use value 24h in future
            $value = $timevalues[$forecast_time+(24*3600)]; 
        }
        
        $profile[] = $value;
    }
    
    $result = new stdClass();
    $result->start = $params->start;
    $result->end = $params->end; 
    $result->interval = $params->interval;
    $result->profile = $profile;
    $result->optimise = MAX;
    return $result;
}
