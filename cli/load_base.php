<?php
define("MAX",1);
define("MIN",0);

chdir("/var/www/emoncms");
require "process_settings.php";
require "Lib/EmonLogger.php";
require "core.php";
require "$linked_modules_dir/demandshaper/lib/scheduler2.php";
require "$linked_modules_dir/demandshaper/lib/misc.php";
$log = new EmonLogger(__FILE__);

// -------------------------------------------------------------------------
// MYSQL, REDIS
// -------------------------------------------------------------------------
$mysqli = @new mysqli(
    $settings["sql"]["server"],
    $settings["sql"]["username"],
    $settings["sql"]["password"],
    $settings["sql"]["database"],
    $settings["sql"]["port"]
);
if ( $mysqli->connect_error ) {    
    $log->error("Can't connect to database, please verify credentials/configuration in settings.php");
    if ( $display_errors ) $log->error("Error message: ".$mysqli->connect_error);
    die();
}

$redis = new Redis();
if (!$redis->connect($settings['redis']['host'], $settings['redis']['port'])) { $log->error("Can't connect to redis"); die; }
if (!empty($settings['redis']['prefix'])) $redis->setOption(Redis::OPT_PREFIX, $settings['redis']['prefix']);
if (!empty($settings['redis']['auth']) && !$redis->auth($settings['redis']['auth'])) {
    $log->error("Can't connect to redis, autentication failed"); die;
}

// Load user module used to fetch user timezone
require("Modules/user/user_model.php");
$user = new User($mysqli,$redis);

require_once "Modules/device/device_model.php";
$device = new Device($mysqli,$redis);

require "Modules/demandshaper/demandshaper_model.php";
$demandshaper = new DemandShaper($mysqli,$redis,$device);

require_once "Modules/input/input_model.php";
$input = new Input($mysqli,$redis,false);

$device_class = $demandshaper->load_device_classes(false,$settings['mqtt']['basetopic']);
