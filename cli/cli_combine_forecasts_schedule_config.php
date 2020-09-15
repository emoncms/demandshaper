<?php
// --------------------------------------------------
// Combine solar and agile forecasts & apply weighting
// --------------------------------------------------
require "settings.php";

define("MAX",1);
define("MIN",0);

// Forecasts use http_request function from core.php
define('EMONCMS_EXEC', 1);
require "/var/www/emoncms/core.php";
$redis = new Redis();
$redis->connect("127.0.0.1");

$params = new stdClass();
$params->timezone = "Europe/London";

// 1. Set desired forecast interval
// This will downsample or upsample original forecast
$params->interval = 1800;

// 2. Get time now to set starting point
$now = time();
$params->start = floor($now/$params->interval)*$params->interval;
$params->end = $params->start + (3600*24);

// Config copied from demandshaper/forecastviewer 
$config = json_decode('[{"name":"octopusagile","gsp_id":"D","weight":1},{"name":"solcast","weight":-5,"siteid":"'.$solcast_siteid.'","api_key":"'.$solcast_apikey.'"}]');

// --------------------------------------------------
// 1. Load forecasts
// --------------------------------------------------
$profile_length = ($params->end-$params->start)/$params->interval;
$combined = false;
foreach ($config as $config_item) {
    $name = $config_item->name;
    if (file_exists("../forecasts/$name.php")) {
        require_once "../forecasts/$name.php";
        
        // Copy over params
        $fn = "get_list_entry_$name";
        $list_entry = $fn();
        foreach ($list_entry["params"] as $param_key=>$param) {
            if (isset($config_item->$param_key)) {
                $params->$param_key = $config_item->$param_key;
            }
        }
        
        // Fetch forecast
        $fn = "get_forecast_$name";
        if ($forecast = $fn($redis,$params)) {
            // Clone first, combine 2nd, 3rd etc
            if ($combined==false) {
                $combined = clone $forecast;
                for ($td=0; $td<$profile_length; $td++) $combined->profile[$td] = 0;
            }
            
            // Combine
            for ($td=0; $td<$profile_length; $td++) {
                $combined->profile[$td] += ($forecast->profile[$td]*$config_item->weight);
            }
        }
    }
}

// --------------------------------------------------
// 3. Schedule
// --------------------------------------------------
// End time
$date = new DateTime();
$date->setTimezone(new DateTimeZone($params->timezone));
$date->modify("4pm");
$date->modify("+1 day");
$end = $date->getTimestamp();

// Period
$period = (3600*3);

// Run schedule
require_once "../scheduler2.php";
$combined = forecast_calc_min_max($combined);

$s = microtime(true);
$periods = schedule_interruptible($combined,$period,$end,$params->timezone);
//$periods = schedule_block($forecast,$period,$end,$params->timezone);
echo round(1000000*(microtime(true)-$s))." us\n";

// --------------------------------------------------

echo "----------------------------------\n";
echo "Combine Forecasts\n";
echo "----------------------------------\n";
echo "Date:\t\t\tCombined\n";
$date = new DateTime();
$date->setTimezone(new DateTimeZone($params->timezone));

for ($td=0; $td<$profile_length; $td++) {
    $time = $combined->start + ($td*$combined->interval);
    
    $state = "";
    foreach ($periods as $p) {
        if ($time>=$p["start"][0] && $time<$p["end"][0]) $state = "*";
    }
    
    $date->setTimestamp($time);    
    echo $date->format("Y-m-d H:i")."\t".$combined->profile[$td]."\t".$state."\n";
}

