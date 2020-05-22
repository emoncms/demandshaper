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
    
    $result = json_decode($redis->get("demandshaper:$signal"));

    if (!$result || !is_object($result)) {
        
        $params_req = array(
            "token"=>$params->signal_token,
            "bidding_area"=>$params->area,
            "perspective"=>$nordpool[$params->area]["currency"],
            "format"=>"json",
            "t"=>time()
        );
        
        if ($result = http_request("GET","http://datafeed.expektra.se/datafeed.svc/spotprice",$params_req)) {
            $r = json_decode($result);
            if(null!=$r) {
                $redis->set("demandshaper:$signal",$result);
                $redis->expire("demandshaper:$signal",1800);
            }
            $result = $r;
        }
    }
    
    $profile = array();
     
    if ($result!=null && isset($result->data)) {

        $vat = $nordpool[$params->area]["vat"];
        $timestamp = $params->start;
        
        foreach ($result->data as $row) {

            $arrDate = new DateTime($row->utc);
            $arrDate->setTimezone(new DateTimeZone($params->timezone));                
            $arrTs = $arrDate->getTimestamp();

            if ($arrTs>=$params->start) 
            {
                $h = 1*$arrDate->format('H');
                $m = 1*$arrDate->format('i')/60;
                $hour = $h + $m;
                
                $profile[] = array($arrTs*1000,floatval(($row->value*((100+$vat)/100))/10),$hour);
            }

            $timestamp += $params->resolution; 
        }
    }

    $result = new stdClass();
    $result->profile = $profile;
    $result->optimise = MIN;
    return $result;
}
