<?php


function get_list_entry_carbonintensity()
{
    return array(
        "category"=>"General",
        "name"=>"Carbon Intensity",
        "params"=>array()
    );
}

function get_forecast_carbonintensity($redis,$params)
{
    $forecast_interval = 1800;

    // Original forecast API
    // https://api.carbonintensity.org.uk/intensity/2020-05-21T21:00:00Z/fw24h
    // We fetch instead here the cached copy:
    $key = "demandshaper:carbonintensity";
    if (!$result = $redis->get($key)) {
        if ($result = http_request("GET","https://emoncms.org/demandshaper/carbonintensity&time=".time(),array())) {
            $redis->set($key,$result);
            $redis->expire($key,1800);
        }
    }
    $result = json_decode($result);
    
    // 2. Create associative array out of original forecast
    //    format: timestamp:value
    $timevalues = array();
    if ($result!=null && isset($result->data)) {
        foreach ($result->data as $hour) {
            $date = new DateTime($hour->from);
            $timestamp = $date->getTimestamp();
            $timevalues[$timestamp] = $hour->intensity->forecast;
        }
    }
    
    // 3. Map forecast to request start, end and interval
    $profile = array();
    for ($time=$params->start; $time<$params->end; $time+=$params->resolution) {
        $forecast_time = floor($time / $forecast_interval) * $forecast_interval;

        if (isset($timevalues[$forecast_time])) {
            $value = $timevalues[$forecast_time];
        } else if (isset($timevalues[$forecast_time-(24*3600)])) { // if not available try to use value 24h in past
            $value = $timevalues[$forecast_time-(24*3600)]; 
        } else if (isset($timevalues[$forecast_time+(24*3600)])) { // if not available try to use value 24h in future
            $value = $timevalues[$forecast_time+(24*3600)]; 
        }

        $profile[] = array($time*1000,$value);
    }
    
    $result = new stdClass();
    $result->profile = $profile;
    $result->optimise = MIN;
    return $result;
}
