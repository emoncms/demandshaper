<?php

/*

All Emoncms code is released under the GNU Affero General Public License.
See COPYRIGHT.txt and LICENSE.txt.

---------------------------------------------------------------------
Emoncms - open source energy visualisation
Part of the OpenEnergyMonitor project:
http://openenergymonitor.org

*/
define("MAX",1);
define("MIN",0);

// default user when mqtt multiuser disabled
$default_userid = 1;

define('EMONCMS_EXEC', 1);

$fp = fopen("/var/lock/demandshaper.lock", "w");
if (! flock($fp, LOCK_EX | LOCK_NB)) { echo "Already running\n"; die; }

$pid = getmypid();

chdir("/var/www/emoncms");
require "process_settings.php";
require "Lib/EmonLogger.php";
require "core.php";
require "$linked_modules_dir/demandshaper/lib/scheduler2.php";
require "$linked_modules_dir/demandshaper/lib/misc.php";

set_error_handler('exceptions_error_handler');
$log = new EmonLogger(__FILE__);
$log->info("Starting demandshaper service");

// -------------------------------------------------------------------------
// MYSQL, REDIS, MQTT
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

$mqtt_client = new Mosquitto\Client();
$connected = false;
$mqtt_client->onConnect('connect');
$mqtt_client->onDisconnect('disconnect');
$mqtt_client->onMessage('message');
// -------------------------------------------------------------------------

// Load user module used to fetch user timezone
require("Modules/user/user_model.php");
$user = new User($mysqli,$redis);

require_once "Modules/device/device_model.php";
$device = new Device($mysqli,$redis);

require "Modules/demandshaper/demandshaper_model.php";
$demandshaper = new DemandShaper($mysqli,$redis,$device);
$forecast_list = $demandshaper->get_forecast_list();
$device_class = $demandshaper->load_device_classes($mqtt_client,$settings['mqtt']['basetopic']);

require_once "Modules/input/input_model.php";
$input = new Input($mysqli,$redis,false);

$redis->del("demandshaper:trigger");

// -------------------------------------------------------------------------
// Control Loop
// -------------------------------------------------------------------------
$last_retry = 0;
$timer = array();
$update_interval = 60;
$last_state_check = 0;
$schedules = array();
$firstrun = true;
$lasttime = time();

while(true) 
{
    $now = time();
    
    // demandshaper trigger
    $trigger = $redis->llen("demandshaper:trigger");
    if ($trigger>0) {
        $log->info("trigger");
    }
    
    // ---------------------------------------------------------------------
    // Control Loop
    // ---------------------------------------------------------------------
    if ($now%$update_interval==0 || $trigger || $firstrun) {
        $firstrun = false;
        
        $users = array();
        
        for ($i=0; $i<$trigger; $i++) $users[] = $redis->lpop("demandshaper:trigger");
        
        // Get list of users to process
        if (!$trigger) {
            $result = $mysqli->query("SELECT `userid` FROM demandshaper");
            while ($row = $result->fetch_object()) {
                if ($row->userid) $users[] = $row->userid;
            }
        }
        
        foreach($users as $userid)
        {            
            $log->info("processing:$userid");
            // Get time of start of day
            $timezone = $user->get_timezone($userid);
            $date = new DateTime();
            $date->setTimezone(new DateTimeZone($timezone));
            $date->setTimestamp($now);
            $date->modify("midnight");
            $daystart = $date->getTimestamp();
            $second_in_day = $now - $daystart;

            // Schedule definition
            $schedules = $demandshaper->get($userid);
         
            foreach ($schedules as $sid=>$schedule)
            {                   
                $device = $schedule->settings->device;
                $device_type = $schedule->settings->device_type;

                if (isset($settings['mqtt']['multiuser']) && $settings['mqtt']['multiuser']) {
                    $device_class[$device_type]->set_basetopic($settings['mqtt']['basetopic']."/".$userid);
                }
                
                $log->info(date("Y-m-d H:i:s")." Schedule:$device ".$schedule->settings->ctrlmode);
                $log->info("  end timestamp: ".$schedule->settings->end_timestamp);
                
                // -----------------------------------------------------------------------
                // Work out if schedule is running, status and decrease timeleft
                // -----------------------------------------------------------------------  
                $status = 0;
                $active_period = 0;
                if ($schedule->runtime->timeleft>0 || $schedule->settings->ctrlmode=="timer") {
                    foreach ($schedule->runtime->periods as $pid=>$period) {
                        $start = $period->start[0];
                        $end = $period->end[0];
                        if ($now>=$start && $now<$end) {
                            $status = 1;
                            $active_period = $pid;
                        }
                    }
                }
                if ($schedule->settings->ctrlmode=="on") $status = 1;
                if ($schedule->settings->ctrlmode=="off") $status = 0;

                if ($status) {
                    $log->info("  status: ON");
                    $schedule->runtime->started = true;
                    $time_elapsed = $now - $lasttime;
                    $log->info("  time elapsed: $time_elapsed");
                    $schedule->runtime->timeleft -= $time_elapsed; // $update_interval;
                    $log->info("  timeleft: ".$schedule->runtime->timeleft."s");
                    if ($schedule->runtime->timeleft<0) $schedule->runtime->timeleft = 0;
                } else {
                    $log->info("  status: OFF");
                }
                
                // -----------------------------------------------------------------------
                // Publish control commands
                // -----------------------------------------------------------------------
                if ($connected) {
                    
                    // Timezone correction e.g conversion to UTC for applicable devices
                    $timeOffset = $device_class[$device_type]->get_time_offset($timezone);

                    // ----------------------------------------------------------------------------
                    // Set Timer
                    // ----------------------------------------------------------------------------
                    $s1 = 0.0; $e1 = 0.0; $s2 = 0.0; $e2 = 0.0;
                    
                    // Smart or regular timer
                    if ($schedule->settings->ctrlmode=="smart" || $schedule->settings->ctrlmode=="timer") {
                        if (count($schedule->runtime->periods)) {
                            $s1 = time_offset($schedule->runtime->periods[$active_period]->start[1],-$timeOffset);
                            $e1 = time_offset($schedule->runtime->periods[$active_period]->end[1],-$timeOffset);
                            $device_class[$device_type]->timer($device,$s1,$e1,$s2,$e2);
                        }
                    }
                    else if ($schedule->settings->ctrlmode=="on") $device_class[$device_type]->on($device);
                    else if ($schedule->settings->ctrlmode=="off") $device_class[$device_type]->off($device);
                }
                
                // -----------------------------------------------------------------------
                // Recalculate schedule
                // -----------------------------------------------------------------------
                // If we are beyond the end_timestamp, set the next end timestamp +1 day, reset the runtime and remove the started flag
                if ($now>$schedule->settings->end_timestamp) {
                    $date->setTimestamp($schedule->settings->end_timestamp);
                    $date->modify("+1 day");
                    $schedule->settings->end_timestamp = $date->getTimestamp();
                                                
                    $schedule->runtime->timeleft = $schedule->settings->period * 3600;
                    unset($schedule->runtime->started);
                                                
                    schedule_log("$device schedule complete");
                }
                
                // If the schedule has not yet started it is ok to recalculate the schedule periods to find a more optimum time
                if (!isset($schedule->runtime->started) || $schedule->settings->interruptible) {
                    
                    if ($schedule->settings->ctrlmode=="smart") {
                        // 1. Compile combined forecast
                        $combined = $demandshaper->get_combined_forecast($schedule->settings->forecast_config);
                        // 2. Calculate forecast min/max 
                        $combined = forecast_calc_min_max($combined);
                        // 3. Calculate schedule
                        if ($schedule->settings->interruptible) {
                            $schedule->runtime->periods = schedule_interruptible($combined,$schedule->runtime->timeleft,$schedule->settings->end,$timezone);
                        } else {
                            $schedule->runtime->periods = schedule_block($combined,$schedule->runtime->timeleft,$schedule->settings->end,$timezone);
                        }
                    } 
                    $schedule = json_decode(json_encode($schedule));
                    $log->info("  reschedule ".json_encode($schedule->runtime->periods));
                }
                $schedules->$sid = $schedule;
                
            } // foreach schedules 
            $demandshaper->set($userid,$schedules);
        } // user list
        sleep(1.0);
        $lasttime = $now;
    } // 10s update
    

    // -----------------------------------------------------------------------
    // Send a request for device state every 5 mins
    // -----------------------------------------------------------------------   
    if ($connected && (time()-$last_state_check)>300) {
        $last_state_check = time();
        
        $result = $mysqli->query("SELECT `userid` FROM demandshaper");
        while ($row = $result->fetch_object()) {
            $userid = $row->userid;
            
            $schedules = $demandshaper->get($userid);
            foreach ($schedules as $schedule) {
                if ($schedule->settings->device) {
                    $device_type = $schedule->settings->device_type;

                    if (isset($settings['mqtt']['multiuser']) && $settings['mqtt']['multiuser']) {
                        $device_class[$device_type]->set_basetopic($settings['mqtt']['basetopic']."/".$userid);
                    }
                
                    $device_class[$device_type]->send_state_request($schedule->settings->device);
                }
            }
        }
    }    
    
    // MQTT Connect or Reconnect
    if (!$connected && (time()-$last_retry)>5.0) {
        $last_retry = time();
        try {
            $mqtt_client->setCredentials($settings['mqtt']['user'],$settings['mqtt']['password']);
            $mqtt_client->connect($settings['mqtt']['host'], $settings['mqtt']['port'], 5);
        } catch (Exception $e) { }
    }
    try { $mqtt_client->loop(); } catch (Exception $e) { }
    
    // Dont loop to fast
    usleep(100000);
}

function connect($r, $message) {
    global $connected, $mqtt_client, $settings; 
    $connected = true;
    $mqtt_client->subscribe($settings['mqtt']['basetopic']."/#",2);
}

function disconnect() {
    global $connected; $connected = false;
}

// -------------------------------------------------------------------------
// Update demand shaper state with state from device
// -------------------------------------------------------------------------
function message($message) 
{
    global $demandshaper, $schedules, $log, $user, $settings, $default_userid, $device_class;
    
    $basetopic = $settings['mqtt']['basetopic'];
    
    $topic_parts = explode("/",$message->topic);
    
    $userid = false;
    $device = false;
    
    if (isset($settings['mqtt']['multiuser']) && $settings['mqtt']['multiuser']) {
        if (isset($topic_parts[1]) && isset($topic_parts[2])) {
            $userid = $topic_parts[1];
            $device = $topic_parts[2];
            $basetopic .= "/".$userid;
        }
    } else {
        if (isset($topic_parts[1])) {
            $userid = $default_userid;
            $device = $topic_parts[1];
        }
    }
    
    if ($userid && $device && isset($schedules->$device)) {
        $device_type = $schedules->$device->settings->device_type;
        $schedules->$device = $device_class[$device_type]->handle_state_response($schedules->$device,$message,$user->get_timezone($userid));
        $demandshaper->set($userid,$schedules);
    }
}
