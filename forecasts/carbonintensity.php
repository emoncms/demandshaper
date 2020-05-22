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
    // $optimise = MIN;
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
    
    $profile = array();
     
    if ($result!=null && isset($result->data)) {

        $datetimestr = $result->data[0]->from;
        $date = new DateTime($datetimestr);
        $date->setTimezone(new DateTimeZone($params->timezone));
        $start = $date->getTimestamp();
        
        $datetimestr = $result->data[count($result->data)-1]->from;
        $date = new DateTime($datetimestr);
        $end = $date->getTimestamp();

        for ($timestamp=$start; $timestamp<$end; $timestamp+=$params->resolution) {
        
            $i = floor(($timestamp - $start)/1800);
            if (isset($result->data[$i])) {
                $co2intensity = $result->data[$i]->intensity->forecast;
                
                $date->setTimestamp($timestamp);
                $h = 1*$date->format('H');
                $m = 1*$date->format('i')/60;
                $hour = $h + $m;
                
                if ($timestamp>=$params->start) $profile[] = array($timestamp*1000,$co2intensity,$hour);
            }
        }
    }
    
    $result = new stdClass();
    $result->profile = $profile;
    $result->optimise = MIN;
    return $result;
}
