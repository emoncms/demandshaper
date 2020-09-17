<?php

function get_list_entry_octopusagile()
{
    return array(
        "category"=>"Octopus Agile",
        "name"=>"Octopus Agile",
        "params"=>array(
            "gsp_id"=>array(
                "name"=>"Region",
                "type"=>"select",
                "options"=>array(
                    "A"=>"Eastern England",
                    "B"=>"East Midlands",
                    "C"=>"London",
                    "D"=>"Merseyside and Northern Wales",
                    "E"=>"West Midlands",
                    "F"=>"North Eastern England",
                    "G"=>"North Western England",
                    "H"=>"Southern England",
                    "J"=>"South Eastern England",
                    "K"=>"Southern Wales",
                    "L"=>"South Western England",
                    "M"=>"Yorkshire",
                    "N"=>"Southern Scotland",
                    "P"=>"Northern Scotland"
                )
            )
        )
    );
}

function get_forecast_octopusagile($redis,$params)
{ 
    if (!isset($params->gsp_id)) return false;
    $timezone = new DateTimeZone($params->timezone);
    $forecast_interval = 1800;
    
    $list_entry = get_list_entry_octopusagile();
    if (!isset($list_entry["params"]["gsp_id"]["options"][$params->gsp_id])) return false;
    
    // Request directly from Octopus API
    // $result = json_decode(file_get_contents("https://api.octopus.energy/v1/products/AGILE-18-02-21/electricity-tariffs/E-1R-AGILE-18-02-21-D/standard-unit-rates/"));

    // 1. Load forecast from local cache if it exists
    //    otherwise load from emoncms.org cache
    //    expire cache every 3600 seconds to limit API calls
    $key = "demandshaper:octopusagile:".$params->gsp_id;
    if (!$result = $redis->get($key)) {
        $req_params = array(
            "gsp"=>$params->gsp_id,
            "time"=>time() // force reload
        );
        if ($result = http_request("GET","https://emoncms.org/demandshaper/octopus",$req_params)) {
            $redis->set($key,$result);
            $redis->expire($key,3600);
        }
    }
    $result = json_decode($result);

    // 2. Create associative array out of original forecast
    //    format: timestamp:value_inc_vat
    $octopus = array();
    if ($result!=null && isset($result->results)) {
        foreach ($result->results as $row) {
            $date = new DateTime($row->valid_from);
            $date->setTimezone($timezone);
            $timestamp = $date->getTimestamp();
            $octopus[$timestamp] = $row->value_inc_vat;
        }
    }

    // 3. Map forecast to request start, end and interval
    $profile = array();
    for ($time=$params->start; $time<$params->end; $time+=$params->interval) {
        $forecast_time = floor($time / $forecast_interval) * $forecast_interval;
        if (isset($octopus[$forecast_time])) {
            $price = $octopus[$forecast_time];
        } else if (isset($octopus[$forecast_time-(24*3600)])) {
            $price = $octopus[$forecast_time-(24*3600)]; 
        } else {
            $price = 12.0;
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
