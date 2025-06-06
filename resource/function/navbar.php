<?php
function navbar() {
    $menu = [
        "home" => [
            'label' => "Home",
            "icon" => "fa-home",
            "links" => "/"
        ],
        "gallery" => [
            'label' => "Gallery",
            "icon" => "fa-image",
            "links" => "/?action=gallery"
        ]
    ];

    // Add admin menu only if admin is logged in
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
        $menu["dashboard"] = [
            "label" => "Dashboard",
            "icon" => "fa-tachometer-alt",
            "links" => "/?action=dashboard"
        ];
        $menu["Logout"] = [
            "label" => "Logout",
            "icon" => "fa-sign-out-alt",
            "links" => "/?action=logout"
        ];
    } else {
        $menu["admin"] = [
            'label' => 'Admin',
            'icon' => "fa-user",
            "links" => "/?action=loginmin"
        ];
    }

    $current = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    $nav = '<header>
        <h1 class="title">EnfileUp</h1>
        <nav class="navbar">';

    foreach ($menu as $key => $sub) {
        $active = (strpos($_SERVER['REQUEST_URI'], $sub['links']) !== false) ? 'active' : '';
        $nav .= '<a class="menu-nav ' . $active . '" href="' . $sub['links'] . '"><i class="fas ' . $sub['icon'] . '"></i>' . $sub['label'] . '</a>';
    }

    $nav .= '</nav></header>';

    return $nav;
}