<?php
// --------------------------------------------------
// Forecast test script
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
// 3. Load forecast
$signal = "nordpool";

// Forecast params
switch ($signal)
{
    case "carbonintensity":
        break;
        
    case "economy7":
        break;
        
    case "octopusagile":
        $params->gsp_id = "D";
        break;

    case "energylocal":
        $params->club = "bethesda";
        break;

    case "nordpool":
        $params->area = "DK1";
        $params->signal_token = $nordpool_token;
        break;

    case "solcast":
        $params->siteid = $solcast_siteid;
        $params->api_key = $solcast_apikey;
        break;

    case "solarclimacell":
        $params->lat = "56.782122";
        $params->lon = "-7.630868";
        $params->apikey = $climacell_apikey;
        break;
}

if (file_exists("../forecasts/$signal.php")) {
    require_once "../forecasts/$signal.php";
    $forecast_fn = "get_forecast_$signal";
    $forecast = $forecast_fn($redis,$params);
} else {
    print "forecast does not exist\n"; die;
}

// 4. Output forecast
// echo json_encode($forecast)."\n";

echo "----------------------------------\n";
echo "Forecast: $signal\n";
echo "----------------------------------\n";

$date = new DateTime();
$date->setTimezone(new DateTimeZone($params->timezone));

$n=0;
for ($time=$params->start; $time<$params->end; $time+=$params->interval) {
    $date->setTimestamp($time);    
    echo $date->format("Y-m-d H:i")."\t".$forecast->profile[$n]."\n";    
    $n++;
}

if ($n!=count($forecast->profile)) {
    echo "MISMATCH profile count\n";
} else {
    echo "MATCH profile count\n";
}

