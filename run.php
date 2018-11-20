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
$lasttime = 0;
$last_retry = 0;

while(true) 
{
    $now = time();

    if (($now-$lasttime)>=10) {
        $lasttime = $now;

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
                if ($schedule->active)
                {
                    $device = $schedule->device;
                    print date("Y-m-d H:i:s")." Schedule:$device\n";
                    print "  timeleft: ".number_format($schedule->timeleft,3)."\n";
                    
                    // -----------------------------------------------------------------------
                    // 1) Recalculate schedule
                    // -----------------------------------------------------------------------
                    print "  end timestamp: ".$schedule->end_timestamp."\n";
                    
                    if ($now>=$schedule->end_timestamp) {
                        print "  SET timeleft to schedule period\n";
                        $schedule->timeleft = $schedule->period;
                    }
                    
                    $r = schedule($redis,$schedule);
                    $schedule->periods = $r["periods"];
                    $schedule->probability = $r["probability"];
                    $schedule = json_decode(json_encode($schedule));
                    
                    print "  reschedule ".json_encode($schedule->periods)."\n";

                    // -----------------------------------------------------------------------
                    // 2) Work out if schedule is running
                    // -----------------------------------------------------------------------  
                    $status = 0;
                    foreach ($schedule->periods as $pid=>$period) {
                        $start = $period->start[0];
                        $end = $period->end[0];
                        
                        if ($now>=$start && $now<$end) $status = 1;
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
                        $schedule->timeleft -= 10.0/3600.0;
                    } else {
                        print "  status: OFF\n";
                    }
                    
                    // Publish to MQTT
                    if ($connected) {
                        // SmartPlug and WIFI Relay

                        if ($device=="openevse") {
                            // $charge_current = 0; if ($status) $charge_current = 13;
                            // $mqtt_client->publish("openevse/rapi/in/\$SC",$charge_current,0);
                            
                            //$last_sh = $sh;
                            //$last_sm = $sm;
                            //$last_eh = $eh;
                            //$last_em = $em;
                            
                            //$sh = floor($schedule->periods[0]->start);
                            //$sm = round(($period->start-$sh)*60);
                            //$eh = floor($period->end);
                            //$em = round(($period->end-$eh)*60);
                                                        
                            //$mqtt_client->publish("emon/openevse/rapi/in/\$ST","$sh $sm $eh $em",0); 
                        } else {
                            $mqtt_client->publish("emon/$device/status",$status,0);
                        }
                    }
                } // if active
                $schedules->$sid = $schedule;
            } // foreach schedules 
            $demandshaper->set($userid,$schedules);
        } // valid schedules
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
    sleep(1);
}

function connect($r, $message) {
    global $connected; $connected = true;
}

function disconnect() {
    global $connected; $connected = false;
}
