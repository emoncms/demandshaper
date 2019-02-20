<?php
    global $demandshaper_users,$session;
    $domain = "messages";
    if (($session["write"]) && in_array($session["userid"],$demandshaper_users)) {
    $menu_left[] = array(
        'id'=>"demandshaper_menu",
        'name'=>"Devices", 
        'path'=>"demandshaper" , 
        'session'=>"write", 
        'order' => 4,
        'icon'=>'icon-calendar icon-white',
        'hideinactive'=>0
    );
    }

    /*
    $menu_dropdown[] = array(
        'id'=>"demandshaper_menu_extras",
        'name'=>"My Devices", 
        'path'=>"demandshaper" , 
        'session'=>"write", 
        'order' => 0,
        'icon'=>'icon-home'
    );
    */
    
    

