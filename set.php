<?php

/*

All Emoncms code is released under the GNU Affero General Public License.
See COPYRIGHT.txt and LICENSE.txt.

---------------------------------------------------------------------
Emoncms - open source energy visualisation
Part of the OpenEnergyMonitor project:
http://openenergymonitor.org

*/

define('EMONCMS_EXEC', 1);

chdir("/var/www/emoncms");
require "process_settings.php";
require "Lib/EmonLogger.php";

// -------------------------------------------------------------------------
// MQTT Connect
// -------------------------------------------------------------------------
$mqtt_client = new Mosquitto\Client();
$connected = false;

$mqtt_client->onConnect( function() use ($mqtt_client) {
    global $connected; $connected = true;
    echo "Connected to server\n";
    $mqtt_client->subscribe("emon/openevse/rapi/#",2);
});

$mqtt_client->onDisconnect( function() {
    global $connected; $connected = false;
    echo "Disconnected cleanly\n";
});

$mqtt_client->onMessage( function($message) { 
    print json_encode($message)."\n";
});

$lasttime = 0;
$last_retry = 0;

while(true) 
{
    $now = time();

    if (($now-$lasttime)>=10) {
        $lasttime = $now;
                    
        // Publish to MQTT
        if ($connected) {
            print "publish\n";
            $mqtt_client->publish("emon/openevse/rapi/in/\$ST","4 0 5 30",0); 
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
