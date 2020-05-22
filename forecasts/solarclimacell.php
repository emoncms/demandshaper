<?php
global $forecast_list;

$forecast_list["solarclimacell"] = array(
    "category"=>"Solar",
    "name"=>"ClimaCell",
    "params"=>array(
        "lat"=>array("type"=>"text"),
        "lon"=>array("type"=>"text"),
        "apikey"=>array("type"=>"text")
    )
);


function get_forecast_solarclimacell($redis,$params)
{   
    if (!isset($params->lat)) return false;
    if (!isset($params->lon)) return false;
    if (!isset($params->apikey)) return false;
    
    $start = floor(time()/1800)*1800;
    $end = $start + (3600*48);
    
    $date = new DateTime();
    $date->setTimestamp($start);
    $start = $date->format("c");
    $date->setTimestamp($end);
    $end = $date->format("c");
       
    $req_params = array(
        "lat"=>$params->lat,
        "lon"=>$params->lon,
        "unit_system"=>"si",
        "start_time"=>$start,
        "end_time"=>$end,
        "fields"=>"surface_shortwave_radiation",
        "apikey"=>$params->apikey
    );

    $key = "demandshaper:solarclimacell:".$params->lat.":".$params->lon;
    if (!$result = $redis->get($key)) {
        if ($result = http_request("GET","https://api.climacell.co/v3/weather/forecast/hourly",$req_params)) {
            $redis->set($key,$result);
            $redis->expire($key,1800);
        }
    }
    
    $result = json_decode($result);
    
    $profile = array();
    foreach ($result as $hour) {
        $date = new DateTime($hour->observation_time->value);
        $profile[] = array($date->getTimestamp()*1000,$hour->surface_shortwave_radiation->value,$date->format("H")*1);
    }
    
    $result = new stdClass();
    $result->profile = $profile;
    $result->optimise = MAX;
    return $result;
}
