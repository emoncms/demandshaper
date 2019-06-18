<?php

global $mysqli,$redis,$session;
    
$menu['tabs'][] = array(
    'icon'=>'calendar',
    'title'=> _("Demandshaper"),
    'path'=> 'demandshaper',
    'data'=> array('sidebar' => '#sidebar_demandshaper')
);

if ($session['read']) {

    require_once "Modules/demandshaper/demandshaper_model.php";
    $demandshaper = new DemandShaper($mysqli,$redis);

    require_once "Modules/device/device_model.php";
    $device = new Device($mysqli,$redis);

    $devices = $demandshaper->get_list($device,$session['userid']);
    
    $o=0;
    foreach ($devices as $name=>$d) {
        $menu['sidebar']['demandshaper'][] = array(
            'icon' => "icon-".$d["type"],
            'title' => $name,
            'text' => ucfirst($name),
            'path' => "demandshaper?device=".$name,
            'data' => array(),
            'order'=> $o
        );
        $o++;
    }
    
    $menu['sidebar']['demandshaper'][] = array(
        'icon' => "plus",
        'title' => "Add Device",
        'text' => "Add Device",
        'path' => "demandshaper#add",
        'data' => array("id"=>"add-device", "is-link"=>false),
        'order'=> $o
    );
}
