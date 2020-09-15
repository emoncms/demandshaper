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
$params->interval = 1800;

// 2. Get time now to set starting point
$now = time();
$params->start = floor($now/$params->interval)*$params->interval;
$params->end = $params->start + (3600*24);

require_once "../forecasts/octopusagile.php";
$params->gsp_id = "D";
$forecast = get_forecast_octopusagile($redis,$params);

// get max and min values of profile
$forecast->min = 1000000; $forecast->max = -1000000;
for ($i=0; $i<count($forecast->profile); $i++) {
    $val = (float) $forecast->profile[$i];
    if ($val>$forecast->max) $forecast->max = $val;
    if ($val<$forecast->min) $forecast->min = $val;
}

// End time
$date = new DateTime();
$date->setTimezone(new DateTimeZone($params->timezone));
$date->modify("8am");
$date->modify("+5 day");
$end = $date->getTimestamp();

// Period
$period = (3600*3);

// Run schedule
require_once "../scheduler2.php";
$s = microtime(true);
$periods = schedule_interruptible($forecast,$period,$end,$params->timezone);
//$periods = schedule_block($forecast,$period,$end,$params->timezone);
echo round(1000000*(microtime(true)-$s))." us\n";

// Print schedule output
$total_run_time = 0;
foreach ($periods as $p) {
    print json_encode($p)."\n";
    $total_run_time += $p["end"][0] - $p["start"][0];
}

print "Total run time: ".$total_run_time."\n";
if ($total_run_time!=$period) {
    echo "MISMATCH, original = $period\n";
} else {
    echo "MATCH\n";
}
echo "\n";
// -------------------------------------------------------

$date = new DateTime();
$date->setTimezone(new DateTimeZone($params->timezone));
$profile_length = count($forecast->profile);

for ($td=0; $td<$profile_length; $td++) {
    $time = $forecast->start + ($td*$forecast->interval);
    
    $state = "";
    foreach ($periods as $p) {
        if ($time>=$p["start"][0] && $time<$p["end"][0]) $state = "*";
    }
    
    $date->setTimestamp($time);    
    echo $date->format("Y-m-d H:i")."\t".$td."\t".$forecast->profile[$td]."\t".$state."\n";
}
