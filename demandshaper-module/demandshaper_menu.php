<?php
$menu['tabs'][] = array(
    'icon'=>'calendar',
    'title'=> _("Apps"),
    'path'=> 'demandshaper',
    'data'=> array(
        'sidebar' => '#sidebar_demandshaper',
        'is-link' => 'true'
    )
);

$menu['sidebar']['demandshaper'][] = array(
    'active'=>'demandshaper',
    'text' => '<ul class="sidenav-menu nav sidebar-menu sub-nav"></ul>',
);
