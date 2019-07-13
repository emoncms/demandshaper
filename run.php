<?php

/*

All Emoncms code is released under the GNU Affero General Public License.
See COPYRIGHT.txt and LICENSE.txt.

---------------------------------------------------------------------
Emoncms - open source energy visualisation
Part of the OpenEnergyMonitor project:
http://openenergymonitor.org

*/

$userid = 1;

define('EMONCMS_EXEC', 1);

$fp = fopen("/var/lock/demandshaper.lock", "w");
if (! flock($fp, LOCK_EX | LOCK_NB)) { echo "Already running\n"; die; }

$pid = getmypid();

//$fh = fopen("/home/pi/data/demandshaper.pid","w");
//fwrite($fh,$pid);
//fclose($fh);

chdir("/var/www/emoncms");
require "process_settings.php";
require "Lib/EmonLogger.php";
require "core.php";

set_error_handler('exceptions_error_handler');
$log = new EmonLogger(__FILE__);
$log->info("Starting demandshaper service");

// -------------------------------------------------------------------------
// MQTT Connect
// -------------------------------------------------------------------------
$mqtt_client = new Mosquitto\Client();

$connected = false;
$mqtt_client->onConnect('connect');
$mqtt_client->onDisconnect('disconnect');
$mqtt_client->onMessage('message');


$mysqli = @new mysqli($server,$username,$password,$database,$port);
if ( $mysqli->connect_error ) {
    
    $log->error("Can't connect to database, please verify credentials/configuration in settings.php");
    if ( $display_errors ) {
        $log->error("Error message: ".$mysqli->connect_error);
    }
    die();
}
    
// -------------------------------------------------------------------------
// Redis Connect
// -------------------------------------------------------------------------
$redis = new Redis();
if (!$redis->connect($redis_server['host'], $redis_server['port'])) { $log->error("Can't connect to redis"); die; }

if (!empty($redis_server['prefix'])) $redis->setOption(Redis::OPT_PREFIX, $redis_server['prefix']);
if (!empty($redis_server['auth']) && !$redis->auth($redis_server['auth'])) {
    $log->error("Can't connect to redis, autentication failed"); die;
}

if (file_exists("$linked_modules_dir/demandshaper/scheduler.php")) {
    require "$linked_modules_dir/demandshaper/scheduler.php";
}
require "Modules/demandshaper/demandshaper_model.php";
$demandshaper = new DemandShaper($mysqli,$redis);

// -------------------------------------------------------------------------
// Control Loop
// -------------------------------------------------------------------------
$last_30min = 0;
$last_retry = 0;
$timer = array();
$last_timer = array();
$last_ctrlmode = array();
$last_flowtemp = array();
$update_interval = 60;
$last_state_check = 0;
$schedules = array();

$lasttime = time();

while(true) 
{
    $now = time();
    
    // demandshaper trigger
    if ($trigger = $redis->get("demandshaper:trigger")) {
        $log->info("trigger");
    }
    
    // ---------------------------------------------------------------------
    // Load demand shaper and cache locally every hour
    // ---------------------------------------------------------------------
    if (($now-$last_30min)>=3600) {
        $last_30min = $now;

        // Energy Local Bethesda demand shaper
        if ($result = http_request("GET","https://dashboard.energylocal.org.uk/cydynni/demandshaper",array())) {
            $redis->set("demandshaper:bethesda",$result);
            $log->info("load: demandshaper:bethesda (".strlen($result).")");
        }
        
        // Uk Grid carbon intensity
        if ($result = http_request("GET","https://emoncms.org/demandshaper/carbonintensity",array())) {
            $redis->set("demandshaper:carbonintensity",$result);
            $log->info("load: demandshaper:carbonintensity (".strlen($result).")");
        }
        
        // Octopus agile
        if ($result = http_request("GET","https://emoncms.org/demandshaper/octopus",array())) {
            $redis->set("demandshaper:octopus",$result);
            $log->info("load: demandshaper:octopus (".strlen($result).")");
        }
    }

    // ---------------------------------------------------------------------
    // Control Loop
    // ---------------------------------------------------------------------
    if ($now%$update_interval==0 || $trigger) {

        $redis->set("demandshaper:trigger",0);

        // Get time of start of day
        $date = new DateTime();
        $date->setTimezone(new DateTimeZone("Europe/London"));
        $date->setTimestamp($now);
        $date->modify("midnight");
        $daystart = $date->getTimestamp();
        $second_in_day = $now - $daystart;

        // Schedule definition
        $schedules = $demandshaper->get($userid);
        if ($schedules!=null)
        {
            foreach ($schedules as $sid=>$schedule)
            {   
                $device = false;
                if (isset($schedule->settings->device)) $device = $schedule->settings->device;
                $device_type = false;
                if (isset($schedule->settings->device_type)) $device_type = $schedule->settings->device_type;
                $ctrlmode = false;
                if (isset($schedule->settings->ctrlmode)) $ctrlmode = $schedule->settings->ctrlmode;
                                
                if ($device_type && $ctrlmode)
                {
                    $log->info(date("Y-m-d H:i:s")." Schedule:$device ".$schedule->settings->ctrlmode);
                    $log->info("  end timestamp: ".$schedule->settings->end_timestamp);
                    // -----------------------------------------------------------------------
                    // Work out if schedule is running
                    // -----------------------------------------------------------------------  
                    $status = 0;
                    $active_period = 0;
                    if ($schedule->runtime->timeleft>0 || $ctrlmode=="timer") {
                        foreach ($schedule->runtime->periods as $pid=>$period) {
                            $start = $period->start[0];
                            $end = $period->end[0];
                            if ($now>=$start && $now<$end) {
                                $status = 1;
                                $active_period = $pid;
                            }
                        }
                    }
                    
                    // If runonce is true, check if within 24h period
                    if ($schedule->settings->runonce!==false) {
                        if (($now-$schedule->settings->runonce)>(24*3600)) $status = 0;
                    } else {
                        // Check if schedule should be ran on this day
                        if (!$schedule->settings->repeat[$date->format("N")-1]) $status = 0;
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
                    
                    // $connected = true; $device = "openevse";
                    
                    // Publish to MQTT
                    if ($connected) {
                        // SmartPlug and WIFI Relay
                        if ($device_type=="openevse" || $device_type=="smartplug" || $device_type=="hpmon") {
                        
                            // Timezone correction to UTC for smartplug and hpmon
                            $timeOffset = 0;
                            if ($device_type=="smartplug" || $device_type=="hpmon") {
                                $dateTimeZone = new DateTimeZone("Europe/London");
                                $date = new DateTime("now", $dateTimeZone);
                                $timeOffset = $dateTimeZone->getOffset($date) / 3600;
                            }

                            // ----------------------------------------------------------------------------
                            // Set Timer
                            // ----------------------------------------------------------------------------
                            $s1 = 0.0; $e1 = 0.0; $s2 = 0.0; $e2 = 0.0;
                            
                            // Smart timer
                            if ($schedule->settings->ctrlmode=="smart") {
                                if (count($schedule->runtime->periods)) {
                                    $s1 = $schedule->runtime->periods[$active_period]->start[1] - $timeOffset;
                                    $e1 = $schedule->runtime->periods[$active_period]->end[1] - $timeOffset;
                                }
                            // Standard timer
                            } else if ($schedule->settings->ctrlmode=="timer") {
                                $s1 = $schedule->settings->timer_start1 - $timeOffset;
                                $e1 = $schedule->settings->timer_stop1 - $timeOffset;
                                $s2 = $schedule->settings->timer_start2 - $timeOffset;
                                $e2 = $schedule->settings->timer_stop2 - $timeOffset;
                            }
                                    
                            if (!isset($timer[$device])) $timer[$device] = "";
                            $last_timer[$device] = $timer[$device];
                            
                            // Slight difference in API format
                            if ($device_type=="smartplug" || $device_type=="hpmon") {
                                $api = "in/timer";
                                $timer[$device] = time_conv_dec_str($s1)." ".time_conv_dec_str($e1)." ".time_conv_dec_str($s2)." ".time_conv_dec_str($e2);
                            }
                            if ($device_type=="openevse") {
                                $api = "rapi/in/\$ST";
                                $timer[$device] = time_conv_dec_str($s1," ")." ".time_conv_dec_str($e1," ");
                            }
                            
                            if ($timer[$device]!=$last_timer[$device]) {  //  && (time_conv_dec_str($s1)!=time_conv_dec_str($e1))
                                $log->info("  emon/$device/$api"." $timer[$device]");
                                $mqtt_client->publish("emon/$device/$api",$timer[$device],0);
                                schedule_log($device." set timer ".$timer[$device]);
                            }
                        } else {
                            $mqtt_client->publish("emon/$device/status",$status,0);
                        }

                        if (!isset($last_ctrlmode[$device])) $last_ctrlmode[$device] = false;
                        if ($ctrlmode!=$last_ctrlmode[$device]) {
                        
                            $ctrlmode_status = "Off";
                            if ($ctrlmode=="on") $ctrlmode_status = "On";
                            if ($ctrlmode=="smart") $ctrlmode_status = "Timer";
                            if ($ctrlmode=="timer") $ctrlmode_status = "Timer";
                            
                            if ($device_type=="smartplug" || $device_type=="hpmon") {
                                $mqtt_client->publish("emon/$device/in/ctrlmode",$ctrlmode_status,0);
                                schedule_log("$device set ctrlmode $ctrlmode_status");
                            }

                            if ($device_type=="openevse") {
                                if ($ctrlmode=="on" || $ctrlmode=="off") {
                                    $mqtt_client->publish("emon/$device/rapi/in/\$ST","00 00 00 00",0);
                                }
                                if ($ctrlmode=="on") {
                                    $mqtt_client->publish("emon/$device/rapi/in/\$FE","",0);
                                    schedule_log("$device turning ON");
                                }
                                if ($ctrlmode=="off") {
                                    $mqtt_client->publish("emon/$device/rapi/in/\$FS","",0);
                                    schedule_log("$device turning OFF");
                                }
                            }
                        }
                        $last_ctrlmode[$device] = $ctrlmode;
                        
                        // Flow temperature target used with heatpump
                        if (isset($schedule->settings->flowT)) {
                            if (!isset($last_flowT[$device])) $last_flowT[$device] = false;
                            if ($schedule->settings->flowT!=$last_flowT[$device]) {
                                if ($device_type=="smartplug" || $device_type=="hpmon") {
                                    $vout = round(($schedule->settings->flowT-7.14)/0.0371);
                                    $log->info("emon/$device/vout ".$vout);
                                    $mqtt_client->publish("emon/$device/in/vout",$vout,0);
                                    schedule_log("$device set vout $vout");
                                }
                            }
                            $last_flowT[$device] = $schedule->settings->flowT;
                        }
                    }
                    
                    // -----------------------------------------------------------------------
                    // Recalculate schedule
                    // -----------------------------------------------------------------------
                    if ($now>$schedule->settings->end_timestamp) {

                        $date->setTimestamp($schedule->settings->end_timestamp);
                        $date->modify("+1 day");
                        $schedule->settings->end_timestamp = $date->getTimestamp();
                        
                        $schedule->runtime->timeleft = $schedule->settings->period * 3600;
                        unset($schedule->runtime->started);
                        
                        schedule_log("$device schedule complete");
                    }
                    
                    if (!isset($schedule->runtime->started) || $schedule->settings->interruptible) {
                        
                        if ($schedule->settings->ctrlmode=="smart") {
                            $forecast = get_forecast($redis,$schedule->settings->signal);
                            $schedule->runtime->periods = schedule_smart($forecast,$schedule->runtime->timeleft,$schedule->settings->end,$schedule->settings->interruptible,900);
                            
                        } else if ($schedule->settings->ctrlmode=="timer") {
                            $forecast = get_forecast($redis,$schedule->settings->signal);
                            $schedule->runtime->periods = schedule_timer(
                                $forecast, 
                                $schedule->settings->timer_start1,$schedule->settings->timer_stop1,$schedule->settings->timer_start2,$schedule->settings->timer_stop2,
                                900
                            );
                        } 
                        $schedule = json_decode(json_encode($schedule));
                        $log->info("  reschedule ".json_encode($schedule->runtime->periods));
                    }
                } // if active
                $schedules->$sid = $schedule;
                
                if ($device_type===false)
                {
                    $log->info("DELETE: ".$sid);
                    unset($schedules->$sid);
                }
                
            } // foreach schedules 
            $demandshaper->set($userid,$schedules);
        } // valid schedules
        sleep(1.0);
        
        $lasttime = $now;
    } // 10s update
    
    if ($connected && (time()-$last_state_check)>300) {
        $last_state_check = time();
        foreach ($schedules as $schedule) {
            $device = false;
            if (isset($schedule->settings->device)) $device = $schedule->settings->device;
            $log->info("emon/$device/in/state");
            if ($device) $mqtt_client->publish("emon/$device/in/state","",0);
        }
    }
    
    // MQTT Connect or Reconnect
    if (!$connected && (time()-$last_retry)>5.0) {
        $last_retry = time();
        try {
            $mqtt_client->setCredentials($mqtt_server['user'],$mqtt_server['password']);
            $mqtt_client->connect($mqtt_server['host'], $mqtt_server['port'], 5);
        } catch (Exception $e) { }
    }
    try { $mqtt_client->loop(); } catch (Exception $e) { }
    
    // Dont loop to fast
    sleep(0.1);
}

function connect($r, $message) {
    global $connected, $mqtt_client; 
    $connected = true;
    $mqtt_client->subscribe("emon/#",2);
}

function disconnect() {
    global $connected; $connected = false;
}

// -------------------------------------------------------------------------
// Update demand shaper state with state from device
// -------------------------------------------------------------------------
function message($message) 
{
    global $demandshaper, $userid, $schedules, $log;
    
    $topic_parts = explode("/",$message->topic);
    if (isset($topic_parts[1])) {
        $device = $topic_parts[1];
        
        if (isset($schedules->$device)) {
            // timezone offset for smartplug and hpmon which use UTC time
            $device_type = $schedules->$device->settings->device_type;
            $timeOffset = 0;
            if ($device_type=="smartplug" || $device_type=="hpmon") {
                $dateTimeZone = new DateTimeZone("Europe/London");
                $date = new DateTime("now", $dateTimeZone);
                $timeOffset = $dateTimeZone->getOffset($date) / 3600;
            }
            
            $p = $message->payload;
                 
            if ($message->topic=="emon/$device/out/state") {
                
                $p = json_decode($p);
                
                if (isset($p->ip)) {
                    $schedules->$device->settings->ip = $p->ip;
                }
            
                if (isset($p->ctrlmode)) {
                    if ($p->ctrlmode=="On") $schedules->$device->settings->ctrlmode = "on";
                    if ($p->ctrlmode=="Off") $schedules->$device->settings->ctrlmode = "off";
                    if ($p->ctrlmode=="Timer" && $schedules->$device->settings->ctrlmode!="smart") $schedules->$device->settings->ctrlmode = "timer";
                }
  
                if (isset($p->vout)) {
                    $schedules->$device->settings->flowT = ($p->vout*0.0371)+7.14;
                }
                
                if (isset($p->timer)) {
                    $timer = explode(" ",$p->timer);
                    $schedules->$device->settings->timer_start1 = time_conv($timer[0]) + $timeOffset;
                    $schedules->$device->settings->timer_stop1 = time_conv($timer[1]) + $timeOffset;
                    $schedules->$device->settings->timer_start2 = time_conv($timer[2]) + $timeOffset;
                    $schedules->$device->settings->timer_stop2 = time_conv($timer[3]) + $timeOffset;
                }
                
                $schedules->$device->runtime->last_update_from_device = time();
                $demandshaper->set($userid,$schedules);
            }
            
            else if ($message->topic=="emon/$device/out/ctrlmode") {
                if ($p=="On") $schedules->$device->settings->ctrlmode = "on";
                if ($p=="Off") $schedules->$device->settings->ctrlmode = "off";
                if ($p=="Timer" && $schedules->$device->settings->ctrlmode!="smart") $schedules->$device->settings->ctrlmode = "timer";
                $demandshaper->set($userid,$schedules);
            }
            
            else if ($message->topic=="emon/$device/out/vout") {
                $schedules->$device->flowT = ($p*0.0371)+7.14;
                $demandshaper->set($userid,$schedules);
            }
            
            else if ($message->topic=="emon/$device/out/timer") {
                $timer = explode(" ",$p);
                $schedules->$device->settings->timer_start1 = time_conv($timer[0]) + $timeOffset;
                $schedules->$device->settings->timer_stop1 = time_conv($timer[1]) + $timeOffset;
                $schedules->$device->settings->timer_start2 = time_conv($timer[2]) + $timeOffset;
                $schedules->$device->settings->timer_stop2 = time_conv($timer[3]) + $timeOffset;
                $schedules->$device->settings->flowT = ($timer[4]*0.0371)+7.14;
                $demandshaper->set($userid,$schedules);
            }
        }
    }
}

function time_conv($t){
    return floor($t*0.01) + ($t*0.01 - floor($t*0.01))/0.6;
}

function time_conv_dec_str($t,$div="") {
    $h = floor($t); 
    $m = round(($t-$h)*60);
    if ($h<10) $h = "0".$h;
    if ($m<10) $m = "0".$m;
    return $h.$div.$m;
}

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    if (error_reporting() == 0) {
        return;
    }
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $filename, $lineno);
    }
}

function schedule_log($message){
    if ($fh = @fopen("/var/log/emoncms/demandshaper.log","a")) {
        $now = microtime(true);
        $micro = sprintf("%03d",($now - ($now >> 0)) * 1000);
        $now = DateTime::createFromFormat('U', (int)$now); // Only use UTC for logs
        $now = $now->format("Y-m-d H:i:s").".$micro";
        @fwrite($fh,$now." | ".$message."\n");
        @fclose($fh);
    }
}
