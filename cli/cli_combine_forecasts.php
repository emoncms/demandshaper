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


require_once "../forecasts/octopusagile.php";
$params->gsp_id = "D";
$agile = get_forecast_octopusagile($redis,$params);
$agile_weighting = 1.0;

require_once "../forecasts/solcast.php";
$params->siteid = $solcast_siteid;
$params->api_key = $solcast_apikey;
$solar = get_forecast_solcast($redis,$params);
$solar_weighting = -5.0;

echo "----------------------------------\n";
echo "Combine Forecasts\n";
echo "----------------------------------\n";
echo "Date:\t\t\tAgile\tSolar\tCombined\n";
$date = new DateTime();
$date->setTimezone(new DateTimeZone($params->timezone));

$n=0;
for ($time=$params->start; $time<$params->end; $time+=$params->interval) {

    $combined = ($agile->profile[$n]*$agile_weighting) + ($solar->profile[$n]*$solar_weighting);

    $date->setTimestamp($time);    
    echo $date->format("Y-m-d H:i")."\t".$agile->profile[$n]."\t".$solar->profile[$n]."\t".$combined."\n";
    
    $n++;
}

