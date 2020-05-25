<?php
// ---------------------------------------------------------------
// Fetch rooftop solar forecast from Solcast
// Create an account on Solcast & configure a site
// ---------------------------------------------------------------
function get_list_entry_solcast()
{
    return array(
        "category"=>"Solar",
        "name"=>"Solcast",
        "params"=>array(
            "api_key"=>array("name"=>"API key", "type"=>"text"),
            "siteid"=>array("name"=>"Site ID", "type"=>"text")
        )
    );
}

function get_forecast_solcast($redis,$params)
{
    if (!isset($params->siteid)) return false;
    if (!isset($params->api_key)) return false;
    $timezone = new DateTimeZone($params->timezone);
    $forecast_interval = 1800;
        
    // 1. Load forecast from local cache if it exists
    //    otherwise load from solcast server
    //    expire cache every 8640 seconds to limit API calls to 10x per day
    //    max of 10 API calls per day allowed on free tier
    $key = "demandshaper:solcast:".$params->siteid;
    if (!$result = $redis->get($key)) {
        $req_params = array(
            "format"=>"json",
            "api_key"=>$params->api_key
        );
        if ($result = http_request("GET","https://api.solcast.com.au/rooftop_sites/".$params->siteid."/forecasts",$req_params)) {
            $redis->set($key,$result);
            $redis->expire($key,8640); 
        }
    }
    $result = json_decode($result);
    
    // 2. Create associative array out of original forecast
    //    format: timestamp:pv_estimate
    $timevalues = array();
    if ($result!=null && isset($result->forecasts)) {
        foreach ($result->forecasts as $hour) {
            $date = new DateTime($hour->period_end);
            $date->setTimezone($timezone);
            $date->modify("-1800 seconds");
            $timestamp = $date->getTimestamp();
            $timevalues[$timestamp] = $hour->pv_estimate;
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
