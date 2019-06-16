<?php

define('EMONCMS_EXEC', 1);
require "MQTTRequest.php";

chdir("/var/www/emoncms");
require "process_settings.php";
require "Lib/EmonLogger.php";

$mqtt_request = new MQTTRequest($mqtt_server);
// print $mqtt_request->request("emon/openevse/rapi/in/\$GS","","emon/openevse/rapi/out");

print $mqtt_request->request("emon/hpmon5/in/state","","emon/hpmon5/out/state");
