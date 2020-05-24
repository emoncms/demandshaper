<?php

function get_list_entry_nordpool()
{
    return array(
        "category"=>"Nordpool Spot",
        "name"=>"Nordpool Spot",
        "currency"=>"DKK",
        "vat"=>"25",
        "params"=>array(
            "area"=>array(
                "type"=>"select",
                "options"=>array(
                    "DK1"=>"DK1",
                    "DK2"=>"DK2",
                    "EE"=>"EE",
                    "FI"=>"FI",
                    "LT"=>"LT",
                    "NO1"=>"NO1",
                    "NO2"=>"NO2",
                    "NO3"=>"NO3",
                    "NO4"=>"NO4",
                    "NO5"=>"NO5",
                    "SE1"=>"SE1",
                    "SE2"=>"SE2",
                    "SE3"=>"SE3",
                    "SE4"=>"SE4"
                )
            )
        )
    );
}

function get_forecast_nordpool($redis,$params)
{
    if (!isset($params->area)) return false;
    if (!isset($params->signal_token)) return false;
    $timezone = new DateTimeZone($params->timezone);
    $forecast_interval = 3600;
    
    $list_entry = get_list_entry_nordpool();
    if (!isset($list_entry["params"]["area"]["options"][$params->area])) return false;
    
    $nordpool = array(
        "DK1"=>array("currency"=>"DKK","vat"=>"25"),
        "DK2"=>array("currency"=>"DKK","vat"=>"25"),
        "EE"=>array("currency"=>"EUR","vat"=>"20"),
        "FI"=>array("currency"=>"EUR","vat"=>"24"),
        "LT"=>array("currency"=>"EUR","vat"=>"21"),
        "NO1"=>array("currency"=>"NOK","vat"=>"25"),
        "NO2"=>array("currency"=>"NOK","vat"=>"25"),
        "NO3"=>array("currency"=>"NOK","vat"=>"25"),
        "NO4"=>array("currency"=>"NOK","vat"=>"25"),
        "NO5"=>array("currency"=>"NOK","vat"=>"25"),
        "SE1"=>array("currency"=>"SEK","vat"=>"25"),
        "SE2"=>array("currency"=>"SEK","vat"=>"25"),
        "SE3"=>array("currency"=>"SEK","vat"=>"25"),
        "SE4"=>array("currency"=>"SEK","vat"=>"25")
    );

    $list_entry = get_list_entry_nordpool();

    // 1. Load forecast from local cache if it exists
    //    otherwise load from nordpool API
    //    expire cache every 3600 seconds to limit API calls
    $key = "demandshaper:nordpool:".$params->area;
    if (!$result = $redis->get($key)) {
        $req_params = array(
            "token"=>$params->signal_token,
            "bidding_area"=>$params->area,
            "perspective"=>$nordpool[$params->area]["currency"],
            "format"=>"json",
            "t"=>time()
        );
        if ($result = http_request("GET","http://datafeed.expektra.se/datafeed.svc/spotprice",$req_params)) {
            $redis->set($key,$result);
            $redis->expire($key,3600);
        }
    }
    $result = json_decode($result);
    
    // 2. Create associative array out of original forecast
    //    format: timestamp:value
    $timevalues = array();
    if ($result!=null && isset($result->data)) {
        $vat = (100.0+$nordpool[$params->area]["vat"])/100.0;
        foreach ($result->data as $row) {
            $date = new DateTime($row->utc);
            $date->setTimezone($timezone);
            $timestamp = $date->getTimestamp();
            $timevalues[$timestamp] = number_format($row->value*$vat*0.1,3,'.','');
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
    $result->profile = $profile;
    $result->start = $params->start;
    $result->end = $params->end; 
    $result->interval = $params->interval;
    $result->optimise = MIN;
    return $result;
}
