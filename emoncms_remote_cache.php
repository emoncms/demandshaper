<?php
// ----------------------------------------------------
// Load emoncms context: mysql, redis and feed model
// ----------------------------------------------------
define('EMONCMS_EXEC', 1);
chdir("/var/www/emoncms");
require "process_settings.php";
require "Lib/EmonLogger.php";

$mysqli = @new mysqli(
    $settings["sql"]["server"],
    $settings["sql"]["username"],
    $settings["sql"]["password"],
    $settings["sql"]["database"],
    $settings["sql"]["port"]
);
if ( $mysqli->connect_error ) {
    $log->error("Can't connect to database, please verify credentials/configuration in settings.php");
    if ( $display_errors ) {
        $log->error("Error message: ".$mysqli->connect_error);
    }
    die();
}
$redis = new Redis();
if (!$redis->connect($settings['redis']['host'], $settings['redis']['port'])) { $log->error("Can't connect to redis"); die; }

if (!empty($settings['redis']['prefix'])) $redis->setOption(Redis::OPT_PREFIX, $settings['redis']['prefix']);
if (!empty($settings['redis']['auth']) && !$redis->auth($settings['redis']['auth'])) {
    $log->error("Can't connect to redis, autentication failed"); die;
}

$redis = new Redis();
$redis->connect("127.0.0.1");

include "Modules/feed/feed_model.php";
$feed = new Feed($mysqli,$redis,$settings["feed"]);

// ----------------------------------------------------
// Grid Carbon
// ----------------------------------------------------

$timestamp = floor(time()/1800)*1800;
$date = new DateTime();
$date->setTimezone(new DateTimeZone("Europe/London"));

$start = $date->format('Y-m-d\TH:i\Z');
$result = file_get_contents("https://api.carbonintensity.org.uk/intensity/$start/fw24h");
$redis->set("demandshaper:carbonintensity",$result);
print "$start carbonintensity ".strlen($result)."\n";

// ----------------------------------------------------
// Octopus Agile
// ----------------------------------------------------

// Option:
// Enter feed id's here that price signals are to be written to
// or leave as zero to just cache latest price signals in redis
$regions = array(
  "A"=>0,
  "B"=>0,
  "C"=>0,
  "D"=>0,
  "E"=>0,
  "F"=>0,
  "G"=>0,
  "H"=>0,
  "J"=>0,
  "K"=>0,
  "L"=>0,
  "M"=>0,
  "N"=>0,
  "P"=>0
);

// Uncomment to load in history
// for ($i=465; $i>0; $i--) {
foreach ($regions as $gsp_id=>$feedid) {
    if ($result = json_decode(file_get_contents("https://api.octopus.energy/v1/products/AGILE-18-02-21/electricity-tariffs/E-1R-AGILE-18-02-21-$gsp_id/standard-unit-rates/"))) {
        if ($result!=null && isset($result->results)) {
            $redis->set("demandshaper:octopus:$gsp_id",json_encode($result));

            if ($feedid>0) {
                // sort octopus forecast into time => price associative array
                $octopus = array();
                foreach ($result->results as $row) {
                    $date = new DateTime($row->valid_from);
                    $date->setTimezone(new DateTimeZone("Europe/London"));
                    $octopus[$date->getTimestamp()] = $row->value_exc_vat;
                }

                ksort($octopus);

                $data = array();
                foreach ($octopus as $time=>$value) {
                    // print $feedid." ".$time." ".$value."\n";
                    $feed->insert_data($feedid,$time,$time,$value);
                }
            }
        }
        print "$gsp_id ok\n";
    }
}
//}
