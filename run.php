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

$fh = fopen("/home/pi/data/demandshaper.pid","w");
fwrite($fh,$pid);
fclose($fh);

chdir("/var/www/emoncms");
require "process_settings.php";
require "Lib/EmonLogger.php";
require "core.php";

// -------------------------------------------------------------------------
// MQTT Connect
// -------------------------------------------------------------------------
$mqtt_client = new Mosquitto\Client();

$connected = false;
$mqtt_client->onConnect('connect');
$mqtt_client->onDisconnect('disconnect');


$mysqli = @new mysqli($server,$username,$password,$database,$port);
if ( $mysqli->connect_error ) {
    echo "Can't connect to database, please verify credentials/configuration in settings.php<br />";
    if ( $display_errors ) {
        echo "Error message: <b>" . $mysqli->connect_error . "</b>";
    }
    die();
}
    
// -------------------------------------------------------------------------
// Redis Connect
// -------------------------------------------------------------------------
$redis = new Redis();
if (!$redis->connect($redis_server['host'], $redis_server['port'])) { echo "Can't connect to redis"; die; }

if (!empty($redis_server['prefix'])) $redis->setOption(Redis::OPT_PREFIX, $redis_server['prefix']);
if (!empty($redis_server['auth']) && !$redis->auth($redis_server['auth'])) {
    echo "Can't connect to redis, autentication failed"; die;
}

require "$homedir/demandshaper/scheduler.php";
require "Modules/demandshaper/demandshaper_model.php";
$demandshaper = new DemandShaper($mysqli,$redis);

// -------------------------------------------------------------------------
// Control Loop
// -------------------------------------------------------------------------
$last_30min = 0;
$last_retry = 0;
$timer = array();
$last_timer = array();
$update_interval = 60;

$lasttime = array();

while(true) 
{
    $now = time();
    
    // demandshaper trigger
    if ($trigger = $redis->get("demandshaper:trigger")) {
        print "trigger\n";
    }
    
    
    // ---------------------------------------------------------------------
    // Load demand shaper and cache locally every hour
    // ---------------------------------------------------------------------
    if (($now-$last_30min)>=3600) {
        $last_30min = $now;

        // Energy Local Bethesda demand shaper
        if ($result = http_request("GET","https://cydynni.org.uk/bethesda/demandshaper",array())) {
            $redis->set("demandshaper:bethesda",$result);
            print "load: demandshaper:bethesda (".strlen($result).")\n";
        }
        
        // Uk Grid carbon intensity
        if ($result = http_request("GET","https://emoncms.org/demandshaper/carbonintensity",array())) {
            $redis->set("demandshaper:carbonintensity",$result);
            print "load: demandshaper:carbonintensity (".strlen($result).")\n";
        }
        
        // Octopus agile
        if ($result = http_request("GET","https://emoncms.org/demandshaper/octopus",array())) {
            $redis->set("demandshaper:octopus",$result);
            print "load: demandshaper:octopus (".strlen($result).")\n";
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
                
                if ($schedule->active && $device_type)
                {
                    print date("Y-m-d H:i:s")." Schedule:$device\n";
                    print "  timeleft: ".$schedule->timeleft."s\n";
                    print "  end timestamp: ".$schedule->end_timestamp."\n";
                    // -----------------------------------------------------------------------
                    // Work out if schedule is running
                    // -----------------------------------------------------------------------  
                    $status = 0;
                    if ($schedule->timeleft>0) {
                        foreach ($schedule->periods as $pid=>$period) {
                            $start = $period->start[0];
                            $end = $period->end[0];
                            if ($now>=$start && $now<$end) $status = 1;
                        }
                    }
                    
                    // If runonce is true, check if within 24h period
                    if ($schedule->runonce!==false) {
                        if (($now-$schedule->runonce)>(24*3600)) $status = 0;
                    } else {
                        // Check if schedule should be ran on this day
                        if (!$schedule->repeat[$date->format("N")-1]) $status = 0;
                    }

                    if ($status) {
                        print "  status: ON\n";
                        $schedule->started = true;
                        $schedule->timeleft -= $update_interval;
                        if ($schedule->timeleft<0) $schedule->timeleft = 0;
                    } else {
                        print "  status: OFF\n";
                    }
                    
                    // $connected = true; $device = "openevse";
                    
                    // Publish to MQTT
                    if ($connected) {
                        // SmartPlug and WIFI Relay

                        if ($device_type=="openevse" || $device_type=="smartplug") {
                            
                            $s1 = $schedule->periods[0]->start[1];
                            $e1 = $schedule->periods[0]->end[1];
                            $sh = floor($s1); $sm = round(($s1-$sh)*60);
                            $eh = floor($e1); $em = round(($e1-$eh)*60);
                            
                            if ($sh<10) $sh = "0".$sh;
                            if ($sm<10) $sm = "0".$sm;
                            if ($eh<10) $eh = "0".$eh;
                            if ($em<10) $em = "0".$em;
                            
                            if (!isset($timer[$device])) $timer[$device] = "";
                            $last_timer[$device] = $timer[$device];
                            
                            // Slight difference in API format
                            if ($device_type=="smartplug") {
                                $api = "timer";
                                $timer[$device] = $sh.$sm." ".$eh.$em;
                            }
                            if ($device_type=="openevse") {
                                $api = "rapi/in/\$ST";
                                $timer[$device] = "$sh $sm $eh $em";
                            }
                            
                            if ($timer[$device]!=$last_timer[$device] && ("$sh $sm"!="$eh $em")) {
                                print "  emon/$device/$api"." $timer[$device]\n";
                                $mqtt_client->publish("emon/$device/$api",$timer[$device],0);
                                
                                // Log temporarily
                                // $fh = fopen("/home/pi/$device.log","a");
                                // fwrite($fh,date("Y-m-d H:i:s",time())." emon/$device/$api ".$timer[$device]."\n");
                                // fclose($fh);
                            }
                        } else {
                            $mqtt_client->publish("emon/$device/status",$status,0);
                        }
                    }
                    
                    // -----------------------------------------------------------------------
                    // Recalculate schedule
                    // -----------------------------------------------------------------------
                    if ($now>$schedule->end_timestamp) {
                        print "  SET timeleft to schedule period\n";
                        $schedule->timeleft = $schedule->period * 3600;
                        unset($schedule->started);
                    }
                    
                    if (!isset($schedule->started) || $schedule->interruptible) {
                        $r = schedule($redis,$schedule);
                        $schedule->periods = $r["periods"];
                        $schedule->probability = $r["probability"];
                        $schedule = json_decode(json_encode($schedule));
                        print "  reschedule ".json_encode($schedule->periods)."\n";
                    }
                    
                } // if active
                $schedules->$sid = $schedule;
            } // foreach schedules 
            $demandshaper->set($userid,$schedules);
        } // valid schedules
        sleep(1.0);
    } // 10s update
    
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
    global $connected; $connected = true;
}

function disconnect() {
    global $connected; $connected = false;
}
