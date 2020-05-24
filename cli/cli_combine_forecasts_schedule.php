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

// --------------------------------------------------
// 1. Load forecasts
// --------------------------------------------------
require_once "../forecasts/octopusagile.php";
$params->gsp_id = "D";
$agile = get_forecast_octopusagile($redis,$params);
$agile_weighting = 1.0;

require_once "../forecasts/solcast.php";
$params->siteid = $solcast_siteid;
$params->api_key = $solcast_apikey;
$solar = get_forecast_solcast($redis,$params);
$solar_weighting = -5.0;

// --------------------------------------------------
// 2. Combine forecasts
// --------------------------------------------------
$combined = $agile;
$profile_length = count($combined->profile);
for ($td=0; $td<$profile_length; $td++) {
    $combined->profile[$td] = ($agile->profile[$td]*$agile_weighting) + ($solar->profile[$td]*$solar_weighting);
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

$agile = forecast_calc_min_max($agile);
$solar = forecast_calc_min_max($solar);
$combined = forecast_calc_min_max($combined);

$s = microtime(true);
$periods = schedule_interruptible($combined,$period,$end,$params->timezone);
//$periods = schedule_block($forecast,$period,$end,$params->timezone);
echo round(1000000*(microtime(true)-$s))." us\n";

// --------------------------------------------------

echo "----------------------------------\n";
echo "Combine Forecasts\n";
echo "----------------------------------\n";
echo "Date:\t\t\tAgile\tSolar\tCombined\n";
$date = new DateTime();
$date->setTimezone(new DateTimeZone($params->timezone));

for ($td=0; $td<$profile_length; $td++) {
    $time = $combined->start + ($td*$combined->interval);
    
    $state = "";
    foreach ($periods as $p) {
        if ($time>=$p["start"][0] && $time<$p["end"][0]) $state = "*";
    }
    
    $date->setTimestamp($time);    
    echo $date->format("Y-m-d H:i")."\t".$agile->profile[$td]."\t".$solar->profile[$td]."\t".$combined->profile[$td]."\t".$state."\n";
}

