<?php
global $forecast_list;

$forecast_list["solcast"] = array(
    "category"=>"Solar",
    "name"=>"Solcast",
    "params"=>array(
        "api_key"=>array("type"=>"text"),
        "siteid"=>array("type"=>"text")
    )
);

function get_forecast_solcast($redis,$params)
{
    if (!isset($params->siteid)) return false;
    if (!isset($params->api_key)) return false;
    
    $req_params = array(
        "format"=>"json",
        "api_key"=>$params->api_key
    );
    
    $key = "demandshaper:solcast:".$params->siteid;
    if (!$result = $redis->get($key)) {
        if ($result = http_request("GET","https://api.solcast.com.au/rooftop_sites/".$params->siteid."/forecasts",$req_params)) {
            $redis->set($key,$result);
            $redis->expire($key,1800);
        }
    }
    
    $profile = array();
    
    $result = json_decode($result);
    
    if ($result!=null && isset($result->forecasts)) {
        $n = 0;
        foreach ($result->forecasts as $hour) {
            $date = new DateTime($hour->period_end);
            $timestamp = $date->getTimestamp()-1800;
            $profile[] = array($timestamp*1000,$hour->pv_estimate,$date->format("H"));
            $n++;
            if ($n>48*2) break;
        }
    }
    
    $result = new stdClass();
    $result->profile = $profile;
    $result->optimise = MAX;
    return $result;
}
