<?php
// --------------------------------------------------
// Forecast test script
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

$forecast_list = array();

// 3. Load forecast
$signal = "carbonintensity";

// Forecast params
switch ($signal)
{
    case "octopusagile":
        $params->gsp_id = "D";
        break;

    case "energylocal":
        $params->club = "bethesda";
        break;

    case "solcast":
        $params->siteid = "";
        $params->api_key = "";
        break;

    case "solarclimacell":
        $params->lat = "";
        $params->lon = "";
        $params->apikey = "";
        break;
}

if (file_exists("forecasts/$signal.php")) {
    require_once "forecasts/$signal.php";
    $forecast_fn = "get_forecast_$signal";
    $forecast = $forecast_fn($redis,$params);
}

// 4. Output forecast
echo json_encode($forecast)."\n";
