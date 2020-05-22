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
    
    $list_entry = get_list_entry_energylocal();
    if (!isset($list_entry["params"]["club"]["options"][$params->club])) return false;
    
    $key = "demandshaper:energylocal_".$params->club;
    if (!$result = $redis->get($key)) {
        if ($result = http_request("GET","https://dashboard.energylocal.org.uk/".$params->club."/demandshaper&time=".time(),array())) {
            $redis->set($key,$result);
            $redis->expire($key,1800);
        }
    }
    $result = json_decode($result);
    
    $profile = array();
            
    // Validate demand shaper
    if  ($result!=null && isset($result->DATA)) {
        
        $date = new DateTime();
        $date->setTimezone(new DateTimeZone($params->timezone));
        
        $EL_signal = $result->DATA[0];
        // array_shift($EL_signal);
        $len = count($EL_signal);
        
        $value = 0.5;
        $timestamp = $params->start;
        
        $divisions = round(24*3600/$params->resolution);
        for ($i=0; $i<$divisions; $i++) {

            $date->setTimestamp($timestamp);
            $h = 1*$date->format('H');
            $m = 1*$date->format('i')/60;
            $hour = $h + $m;
            $hour_index = 2*$h+2*$m;
            
            if (isset($EL_signal[$hour_index])) $value = $EL_signal[$hour_index];
            
            $profile[] = array($timestamp*1000,$value,$hour);
            $timestamp += $params->resolution; 
        }
    }
    
    $result = new stdClass();
    $result->profile = $profile;
    $result->optimise = MIN;
    return $result;
}
