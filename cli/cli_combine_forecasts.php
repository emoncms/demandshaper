<?php
// --------------------------------------------------
// Combine solar and agile forecasts & apply weighting
// --------------------------------------------------
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
$params->resolution = 1800;

// 2. Get time now to set starting point
$now = time();
$params->start = floor($now/$params->resolution)*$params->resolution;
$params->end = $params->start + (3600*24);


require_once "../forecasts/octopusagile.php";
$params->gsp_id = "D";
$agile = get_forecast_octopusagile($redis,$params);
$agile_weighting = 1.0;

require_once "../forecasts/solcast.php";
$params->siteid = "8ffd-005c-5686-8116";
$params->api_key = "pBQwdyxbLutCFemv2My1SXdW6aEIAd2K";
$solar = get_forecast_solcast($redis,$params);
$solar_weighting = -5.0;

echo "----------------------------------\n";
echo "Combine Forecasts\n";
echo "----------------------------------\n";
echo "Date:\t\t\tAgile\tSolar\tCombined\n";
$date = new DateTime();
$date->setTimezone(new DateTimeZone($params->timezone));

$n=0;
for ($time=$params->start; $time<$params->end; $time+=$params->resolution) {

    $combined = ($agile->profile[$n][1]*$agile_weighting) + ($solar->profile[$n][1]*$solar_weighting);

    $date->setTimestamp($time);    
    echo $date->format("Y-m-d H:i")."\t".$agile->profile[$n][1]."\t".$solar->profile[$n][1]."\t".$combined."\n";
    
    
    $n++;
}

