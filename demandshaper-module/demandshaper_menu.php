<?php

global $mysqli,$redis,$session;

if ($session['write']) {

    $menu["demandshaper"] = array("name"=>"DemandShaper", "order"=>3, "icon"=>"calendar", "default"=>"demandshaper/add-device", "l2"=>array());

    require_once "Modules/device/device_model.php";
    $device = new Device($mysqli,$redis);

    require_once "Modules/demandshaper/demandshaper_model.php";
    $demandshaper = new DemandShaper($mysqli,$redis,$device);
    
    $devices = $demandshaper->get_list($session['userid']);
    
    $o=0;
    foreach ($devices as $name=>$d) {        
        $menu["demandshaper"]['l2'][] = array(
            "name"=>ucfirst($devices[$name]['device_name']),
            "title"=>ucfirst($devices[$name]['device_name']),
            "href"=>"demandshaper?device=".$name,
            "icon"=>$d["type"],
            "order"=>$o
        );
        $menu["demandshaper"]["default"] = "demandshaper?device=".$name;
        $o++;
    }
    
    $menu["demandshaper"]['l2'][] = array(
        "name"=>"Add Device",
        "href"=>"demandshaper/add-device",
        "icon"=>"plus", 
        "order"=>$o
    );
}
