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
            'text' => ucfirst($name),
            'path' => "demandshaper?device=".$name,
            'order'=> $o
        );
        $o++;
    }
    
    $menu['sidebar']['demandshaper'][] = array(
        'icon' => "plus",
        'text' => "Add Device",
        'path' => "demandshaper#add",
        'data' => array("id"=>"add-device"),
        'order'=> $o
    );

}
