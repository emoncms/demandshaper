<?php
    $domain = "messages";
    $menu_left[] = array(
        'id'=>"demandshaper_menu",
        'name'=>"DemandShaper", 
        'path'=>"demandshaper" , 
        'session'=>"write", 
        'order' => 4,
        'icon'=>'icon-calendar icon-white',
        'hideinactive'=>1
    );
    $menu_dropdown[] = array(
        'id'=>"demandshaper_menu_extras",
        'name'=>"DemandShaper", 
        'path'=>"demandshaper" , 
        'session'=>"write", 
        'order' => 0,
        'icon'=>'icon-home'
    );
