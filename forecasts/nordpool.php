<?php
global $forecast_list;

$forecast_list["nordpool_dk1"] = array("category"=>"Nordpool Spot","name"=>"DK1","currency"=>"DKK","vat"=>"25");
$forecast_list["nordpool_dk2"] = array("category"=>"Nordpool Spot","name"=>"DK2","currency"=>"DKK","vat"=>"25");
$forecast_list["nordpool_ee"] = array("category"=>"Nordpool Spot","name"=>"EE","currency"=>"EUR","vat"=>"20");
$forecast_list["nordpool_fi"] = array("category"=>"Nordpool Spot","name"=>"FI","currency"=>"EUR","vat"=>"24");
$forecast_list["nordpool_lt"] = array("category"=>"Nordpool Spot","name"=>"LT","currency"=>"EUR","vat"=>"21");
$forecast_list["nordpool_no1"] = array("category"=>"Nordpool Spot","name"=>"NO1","currency"=>"NOK","vat"=>"25");
$forecast_list["nordpool_no2"] = array("category"=>"Nordpool Spot","name"=>"NO2","currency"=>"NOK","vat"=>"25");
$forecast_list["nordpool_no3"] = array("category"=>"Nordpool Spot","name"=>"NO3","currency"=>"NOK","vat"=>"25");
$forecast_list["nordpool_no4"] = array("category"=>"Nordpool Spot","name"=>"NO4","currency"=>"NOK","vat"=>"25");
$forecast_list["nordpool_no5"] = array("category"=>"Nordpool Spot","name"=>"NO5","currency"=>"NOK","vat"=>"25");
$forecast_list["nordpool_se1"] = array("category"=>"Nordpool Spot","name"=>"SE1","currency"=>"SEK","vat"=>"25");
$forecast_list["nordpool_se2"] = array("category"=>"Nordpool Spot","name"=>"SE2","currency"=>"SEK","vat"=>"25");
$forecast_list["nordpool_se3"] = array("category"=>"Nordpool Spot","name"=>"SE3","currency"=>"SEK","vat"=>"25");
$forecast_list["nordpool_se4"] = array("category"=>"Nordpool Spot","name"=>"SE4","currency"=>"SEK","vat"=>"25");

function get_forecast_nordpool($redis,$signal,$start_timestamp,$resolution,$timezone)
{   
    global $forecast_list;
    // Invalid signal
    if (!isset($forecast_list[$signal])) return array();
    
    $result = json_decode($redis->get("demandshaper:$signal"));

    if (!$result || !is_object($result)) {
        $area = $forecast_list[$signal]["name"];
        $currency = $forecast_list[$signal]["currency"];
        $time = time();
        
        if ($result = http_request("GET","http://datafeed.expektra.se/datafeed.svc/spotprice?token=$signal_token&bidding_area=$area&format=json&perspective=$currency&$time",array())) {
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

        $vat = $forecast_list[$signal]["vat"];
        $timestamp = $start_timestamp;
        
        foreach ($result->data as $row) {

            $arrDate = new DateTime($row->utc);
            $arrDate->setTimezone(new DateTimeZone($timezone));                
            $arrTs = $arrDate->getTimestamp();

            if ($arrTs>=$start_timestamp) 
            {
                $h = 1*$arrDate->format('H');
                $m = 1*$arrDate->format('i')/60;
                $hour = $h + $m;
                
                $profile[] = array($arrTs*1000,floatval(($row->value*((100+$vat)/100))/10),$hour);
            }

            $timestamp += $resolution; 
        }
    }

    $result = new stdClass();
    $result->profile = $profile;
    $result->optimise = MIN;
    return $result;
}
