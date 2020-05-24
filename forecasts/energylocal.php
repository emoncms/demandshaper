<?php

function get_list_entry_energylocal()
{
    return array(
        "category"=>"Energy Local",
        "name"=>"Energy Local",
        "params"=>array(
            "club"=>array(
                "type"=>"select",
                "options"=>array(
                    "bethesda"=>"Bethesda",
                    "repower"=>"Repower"
                )
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
    if (!$result = $redis->get($key)) {
        if ($result = http_request("GET","https://dashboard.energylocal.org.uk/".$params->club."/demandshaper&time=".time(),array())) {
            $redis->set($key,$result);
            $redis->expire($key,1800);
        }
    }
    $result = json_decode($result);
    
    $profile = array();
    if  ($result!=null && isset($result->DATA)) {
        
        $date = new DateTime();
        $date->setTimezone(new DateTimeZone($params->timezone));
        
        $EL_signal = $result->DATA[0];
        
        $value = 0.5;
        for ($time=$params->start; $time<$params->end; $time+=$params->resolution) {
            $date->setTimestamp($time);
            $h = 1*$date->format('H');
            $m = 1*$date->format('i')/60;
            $hour = $h + $m;
            $hour_index = 2*$h+2*$m;
            
            if (isset($EL_signal[$hour_index])) $value = $EL_signal[$hour_index];
            
            $profile[] = $value;
        }
    }
    
    $result = new stdClass();
    $result->profile = $profile;
    $result->start = $params->start;
    $result->end = $params->end; 
    $result->interval = $params->resolution;
    $result->optimise = MIN;
    return $result;
}
