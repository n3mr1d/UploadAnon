<?php
function navbar() {
    $menu = [
        "home" => [
            'label' => "home",
            "icon" => "fa-home",
            "links" => "/"
        ],
        "gallery" => [
            'label' => "gallery",
            "icon" => "fa-image",
            "links" => "/?action=gallery"
        ],
        "admin" => [
            'label' => 'admin',
            'icon' => "fa-user",
            "links" => "/?action=admin"
        ]
    ];

    $current = basename($_SERVER['REQUEST_URI']);
    $nav = '<header>
        <h1 class="title">EnfileUp</h1>
        <nav class="navbar">';

    foreach ($menu as $key => $sub) {
        $active = ($current == basename($sub['links'])) ? 'active' : '';
        $nav .= '<a class="menu-nav ' . $active . '" href="' . $sub['links'] . '"><i class="fas ' . $sub['icon'] . '"></i>' . $sub['label'] . '</a>';
    }

    $nav .= '</nav></header>';

    return $nav;
}