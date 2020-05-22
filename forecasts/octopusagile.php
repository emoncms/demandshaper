<?php

function get_list_entry_octopusagile()
{
    return array(
        "category"=>"Octopus Agile",
        "name"=>"Octopus Agile",
        "params"=>array(
            "gsp_id"=>array(
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
    
    $list_entry = get_list_entry_octopusagile();
    if (!isset($list_entry["params"]["gsp_id"]["options"][$params->gsp_id])) return false;
    
    // Request directly from Octopus API
    // $result = json_decode(file_get_contents("https://api.octopus.energy/v1/products/AGILE-18-02-21/electricity-tariffs/E-1R-AGILE-18-02-21-D/standard-unit-rates/"));

    // Fetch from emoncms.org cache
    $key = "demandshaper:octopusagile:".$params->gsp_id;
    if (!$result = $redis->get($key)) {
        if ($result = http_request("GET","https://emoncms.org/demandshaper/octopus?gsp=".$params->gsp_id."&time=".time(),array())) {
            $redis->set($key,$result);
            $redis->expire($key,1800);
        }
    }
    
    $result = json_decode($result);

    $profile = array();

    // if forecast is valid
    if ($result!=null && isset($result->results)) {
        
        // sort octopus forecast into time => price associative array
        $octopus = array();
        foreach ($result->results as $row) {
            $date = new DateTime($row->valid_from);
            $date->setTimezone(new DateTimeZone($params->timezone));
            $octopus[$date->getTimestamp()] = $row->value_inc_vat;
        }
        
        $divisions = round(24*3600/$params->resolution);
        $timestamp = $params->start;
        for ($i=0; $i<$divisions; $i++) {

            $date->setTimestamp($timestamp);
            $h = 1*$date->format('H');
            $m = 1*$date->format('i')/60;
            $hour = $h + $m;
            
            if (isset($octopus[$timestamp])) {
                $price = $octopus[$timestamp]; 
            } else if (isset($octopus[$timestamp-(24*3600)])) {
                $price = $octopus[$timestamp-(24*3600)]; 
            } else {
                $price = 12.0;
            }
            
            $profile[] = array($timestamp*1000,$price,$hour);
            $timestamp += $params->resolution; 
        }
    }
    
    $result = new stdClass();
    $result->profile = $profile;
    $result->optimise = MIN;
    return $result;
}
