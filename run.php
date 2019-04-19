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

if (file_exists("$homedir/demandshaper/scheduler.php")) {
    require "$homedir/demandshaper/scheduler.php";
}
else if (file_exists("$homedir/modules/demandshaper/scheduler.php")) {
    require "$homedir/modules/demandshaper/scheduler.php";
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
//$last_state_check = 0;
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
                if (isset($schedule->device)) $device = $schedule->device;
                $device_type = false;
                if (isset($schedule->device_type)) $device_type = $schedule->device_type;
                $ctrlmode = false;
                if (isset($schedule->ctrlmode)) $ctrlmode = $schedule->ctrlmode;
                                
                if ($device_type && $ctrlmode)
                {
                    $log->info(date("Y-m-d H:i:s")." Schedule:$device ".$schedule->ctrlmode);
                    $log->info("  end timestamp: ".$schedule->end_timestamp);
                    // -----------------------------------------------------------------------
                    // Work out if schedule is running
                    // -----------------------------------------------------------------------  
                    $status = 0;
                    $active_period = 0;
                    if ($schedule->timeleft>0 || $ctrlmode=="timer") {
                        foreach ($schedule->periods as $pid=>$period) {
                            $start = $period->start[0];
                            $end = $period->end[0];
                            if ($now>=$start && $now<$end) {
                                $status = 1;
                                $active_period = $pid;
                            }
                        }
                    }
                    
                    // If runonce is true, check if within 24h period
                    if ($schedule->runonce!==false) {
                        if (($now-$schedule->runonce)>(24*3600)) $status = 0;
                    } else {
                        // Check if schedule should be ran on this day
                        if (!$schedule->repeat[$date->format("N")-1]) $status = 0;
                    }
                    
                    if ($schedule->ctrlmode=="on") $status = 1;
                    if ($schedule->ctrlmode=="off") $status = 0;

                    if ($status) {
                        $log->info("  status: ON");
                        $schedule->started = true;
                        $time_elapsed = $now - $lasttime;
                        $log->info("  time elapsed: $time_elapsed");
                        $schedule->timeleft -= $time_elapsed; // $update_interval;
                        $log->info("  timeleft: ".$schedule->timeleft."s");
                        if ($schedule->timeleft<0) $schedule->timeleft = 0;
                    } else {
                        $log->info("  status: OFF");
                    }
                    
                    // $connected = true; $device = "openevse";
                    
                    // Publish to MQTT
                    if ($connected) {
                        // SmartPlug and WIFI Relay

                        if ($device_type=="openevse" || $device_type=="smartplug" || $device_type=="hpmon") {
                            
                            if (count($schedule->periods)) {
                                $s1 = $schedule->periods[$active_period]->start[1];
                                $e1 = $schedule->periods[$active_period]->end[1];
                                $sh = floor($s1); $sm = round(($s1-$sh)*60);
                                $eh = floor($e1); $em = round(($e1-$eh)*60);
                                
                                if ($sh<10) $sh = "0".$sh;
                                if ($sm<10) $sm = "0".$sm;
                                if ($eh<10) $eh = "0".$eh;
                                if ($em<10) $em = "0".$em;
                                
                                if (!isset($timer[$device])) $timer[$device] = "";
                                $last_timer[$device] = $timer[$device];
                                
                                // Slight difference in API format
                                if ($device_type=="smartplug" || $device_type=="hpmon") {
                                    $api = "in/timer";
                                    $timer[$device] = $sh.$sm." ".$eh.$em;
                                }
                                if ($device_type=="openevse") {
                                    $api = "rapi/in/\$ST";
                                    $timer[$device] = "$sh $sm $eh $em";
                                }
                                
                                if ($timer[$device]!=$last_timer[$device] && ("$sh $sm"!="$eh $em")) {
                                    $log->info("  emon/$device/$api"." $timer[$device]");
                                    $mqtt_client->publish("emon/$device/$api",$timer[$device],0);
                                    
                                    // Log temporarily
                                    // $fh = fopen("/home/pi/$device.log","a");
                                    // fwrite($fh,date("Y-m-d H:i:s",time())." emon/$device/$api ".$timer[$device]."\n");
                                    // fclose($fh);
                                }
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
                            }
                        }
                        $last_ctrlmode[$device] = $ctrlmode;
                        
                        // Flow temperature target used with heatpump
                        if (isset($schedule->flowT)) {
                            if (!isset($last_flowT[$device])) $last_flowT[$device] = false;
                            if ($schedule->flowT!=$last_flowT[$device]) {
                                if ($device_type=="smartplug" || $device_type=="hpmon") {
                                    $vout = round(($schedule->flowT-7.14)/0.0371);
                                    $log->info("emon/$device/vout ".$vout);
                                    $mqtt_client->publish("emon/$device/in/vout",$vout,0);
                                }
                            }
                            $last_flowT[$device] = $schedule->flowT;
                        }
                    }
                    
                    // -----------------------------------------------------------------------
                    // Recalculate schedule
                    // -----------------------------------------------------------------------
                    if ($now>$schedule->end_timestamp) {
                        $log->info("  SET timeleft to schedule period");
                        $schedule->timeleft = $schedule->period * 3600;
                        unset($schedule->started);
                    }
                    
                    if (!isset($schedule->started) || $schedule->interruptible) {
                        $r = schedule($redis,$schedule);
                        $schedule->periods = $r["periods"];
                        $schedule->probability = $r["probability"];
                        $schedule = json_decode(json_encode($schedule));
                        $log->info("  reschedule ".json_encode($schedule->periods));
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
    
    // if ($connected && (time()-$last_state_check)>300) {
    //     $last_state_check = time();
    //     foreach ($schedules as $schedule) {
    //         $device = false;
    //         if (isset($schedule->device)) $device = $schedule->device;
    //         $log->info("emon/$device/in/state");
    //         if ($device) $mqtt_client->publish("emon/$device/in/state","",0);
    //     }
    // }
    
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
    global $demandshaper, $userid, $schedules;
    
    $topic_parts = explode("/",$message->topic);
    if (isset($topic_parts[1])) {
        $device = $topic_parts[1];
        
        if (isset($schedules->$device)) {
            $p = $message->payload;
            
            if ($message->topic=="emon/$device/out/state") {
                $p = json_decode($p);
                
                if (isset($p->ip)) {
                    $schedules->$device->ip = $p->ip;
                }
            
                if (isset($p->ctrlmode)) {
                    if ($p->ctrlmode=="On") $schedules->$device->ctrlmode = "on";
                    if ($p->ctrlmode=="Off") $schedules->$device->ctrlmode = "off";
                    if ($p->ctrlmode=="Timer" && $schedules->$device->ctrlmode!="smart") $schedules->$device->ctrlmode = "timer";
                }
  
                if (isset($p->vout)) {
                    $schedules->$device->flowT = ($p->vout*0.0371)+7.14;
                }
                
                if (isset($p->timer)) {
                    $timer = explode(" ",$p->timer);
                    $schedules->$device->timer_start1 = time_conv($timer[0]);
                    $schedules->$device->timer_stop1 = time_conv($timer[1]);
                    $schedules->$device->timer_start2 = time_conv($timer[2]);
                    $schedules->$device->timer_stop2 = time_conv($timer[3]);
                }
                $demandshaper->set($userid,$schedules);
            }
            
            else if ($message->topic=="emon/$device/out/ctrlmode") {
                if ($p=="On") $schedules->$device->ctrlmode = "on";
                if ($p=="Off") $schedules->$device->ctrlmode = "off";
                if ($p=="Timer" && $schedules->$device->ctrlmode!="smart") $schedules->$device->ctrlmode = "timer";
                $demandshaper->set($userid,$schedules);
            }
            
            else if ($message->topic=="emon/$device/out/vout") {
                $schedules->$device->flowT = ($p*0.0371)+7.14;
                $demandshaper->set($userid,$schedules);
            }
            
            else if ($message->topic=="emon/$device/out/timer") {
                $timer = explode(" ",$p);
                $schedules->$device->timer_start1 = time_conv($timer[0]);
                $schedules->$device->timer_stop1 = time_conv($timer[1]);
                $schedules->$device->timer_start2 = time_conv($timer[2]);
                $schedules->$device->timer_stop2 = time_conv($timer[3]);
                $schedules->$device->flowT = ($timer[4]*0.0371)+7.14;
                $demandshaper->set($userid,$schedules);
            }
        }
    }
}

function time_conv($t){
    return floor($t*0.01) + ($t*0.01 - floor($t*0.01))/0.6;
}

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    if (error_reporting() == 0) {
        return;
    }
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $filename, $lineno);
    }
}
