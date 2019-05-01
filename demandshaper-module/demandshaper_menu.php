<?php
$menu['sidebar']['emoncms'][] = array(
    'text' => _("DemandShaper"),
    'path' => 'demandshaper',
    'icon' => 'calendar',
    'data'=> array('sidebar' => '#sidebar_demandshaper')
);
$menu['sidebar']['includes']['emoncms']['demandshaper'] = view('Modules/demandshaper/Views/sidebar.php',array());