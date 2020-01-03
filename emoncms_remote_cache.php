<?php

$redis = new Redis();
$redis->connect("127.0.0.1");

$timestamp = floor(time()/1800)*1800;
$date = new DateTime();
$date->setTimezone(new DateTimeZone("Europe/London"));

$start = $date->format('Y-m-d\TH:i\Z');
$result = file_get_contents("https://api.carbonintensity.org.uk/intensity/$start/fw24h");
$redis->set("demandshaper:carbonintensity",$result);
print "$start carbonintensity ".strlen($result)."\n";

$result = file_get_contents("https://api.octopus.energy/v1/products/AGILE-18-02-21/electricity-tariffs/E-1R-AGILE-18-02-21-D/standard-unit-rates/");
$redis->set("demandshaper:octopus",$result);
print "$start octopus ".strlen($result)."\n";

$result = file_get_contents("http://tuntihinta.fi/json/hinnat.json");
$redis->set("demandshaper:nordpool_fi",$result);
print "$start nordpool_fi ".strlen($result)."\n";
