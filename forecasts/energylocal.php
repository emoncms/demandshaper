<?php

function get_list_entry_energylocal()
{
    return array(
        "category"=>"Energy Local",
        "name"=>"Energy Local",
        "params"=>array(
            "club"=>array(
                "name"=>"Club",
                "type"=>"select",
                "options"=>array(
                    "bethesda"=>"Bethesda",
                    "corwen"=>"Corwen",
                    "crickhowell"=>"Crickhowell",
                    "bethesda_solar"=>"Solar",
                    "repower"=>"Repower",
                ),
                "default"=>"bethesda"
            )
        )
    );
}

function get_forecast_energylocal($redis,$params)
{
    if (!isset($params->club)) return false;
    $forecast_interval = 1800;
    
    $list_entry = get_list_entry_energylocal();
    if (!isset($list_entry["params"]["club"]["options"][$params->club])) return false;

    // 1. Load forecast from local cache if it exists
    //    otherwise load from emoncms.org cache
    //    expire cache every 3600 seconds to limit API calls
    $key = "demandshaper:energylocal_".$params->club;
    //if (!$result = $redis->get($key)) {
        if ($result = http_request("GET","https://dashboard.energylocal.org.uk/club/forecast?name=".$params->club."&time=".time(),array())) {
            $redis->set($key,$result);
            $redis->expire($key,1800);
        }
    //}
    $result = json_decode($result);
    
    // The format of the profile does not really change here
    // instead the implementation allows for the cached forecast to be out of date
    
    $profile = array();
    if  ($result!=null && isset($result->profile)) {
    
        $energylocal = array();
        $i = 0;
        for ($time=$result->start; $time<$result->end; $time+=$result->interval) {
            $energylocal[$time] = $result->profile[$i];
            $i++;
        }
        
        for ($time=$params->start; $time<$params->end; $time+=$params->interval) {
        
            if (isset($energylocal[$time])) {
                $value = $energylocal[$time];
            } else if (isset($energylocal[$time-(24*3600)])) {
                $value = $energylocal[$time-(24*3600)]; 
            } else if (isset($energylocal[$time+(24*3600)])) {
                $value = $energylocal[$time+(24*3600)]; 
            } else {
                $value = 2.0;
            }
            
            $profile[] = $value;
        }
    }
    
    $result = new stdClass();
    $result->start = $params->start;
    $result->end = $params->end; 
    $result->interval = $params->interval;
    $result->profile = $profile;
    $result->optimise = MIN;
    return $result;
}
