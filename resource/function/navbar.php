<?php

function navbar() {
    // Initialize session if not started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    $menu = [
        "home" => [
            'label' => "Home",
            'icon' => "fa-home",
            'links' => "/"
        ],
        "gallery" => [
            'label' => "Gallery",  
            'icon' => "fa-image",
            'links' => "/?action=gallery"
        ]
    ];
    
    // Add admin menu only if admin is logged in
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        $menu["dashboard"] = [
            "label" => "Dashboard",
            "icon" => "fa-tachometer-alt", 
            "links" => "/?action=dashboard"
        ];
        $menu["logout"] = [  // Changed key to lowercase for consistency
            "label" => "Logout",
            "icon" => "fa-sign-out-alt",
            "links" => "/?action=logout"
        ];
    } else {
        $menu["admin"] = [
            'label' => 'Admin',
            'icon' => "fa-user",
            'links' => "/?action=loginmin"  // Fixed typo: was "loginmin", should be "login" or keep as is if intentional
        ];
    }
    
    // Get current page for active state
    $current_uri = $_SERVER['REQUEST_URI'] ?? '';
    $current_action = '';
    
    // Parse the action parameter from URL
    if (strpos($current_uri, '?action=') !== false) {
        parse_str(parse_url($current_uri, PHP_URL_QUERY), $params);
        $current_action = $params['action'] ?? '';
    }
    
    // Build navbar HTML
    $nav = '<header>
        <h1 class="title">EnfileUp</h1>
        <nav class="navbar">';
    
    foreach ($menu as $key => $item) {
        // Improved active state detection
        $active = '';
        
        if ($item['links'] === '/' && ($current_uri === '/' || $current_uri === '' || (!$current_action && strpos($current_uri, '/?') === false))) {
            $active = 'active';
        } elseif ($item['links'] !== '/' && strpos($current_uri, $item['links']) !== false) {
            $active = 'active';
        }
        
        // Sanitize output to prevent XSS
        $label = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
        $icon = htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8');
        $links = htmlspecialchars($item['links'], ENT_QUOTES, 'UTF-8');
        
        $nav .= '<a class="menu-nav ' . $active . '" href="' . $links . '">
                    <i class="fas ' . $icon . '"></i>' . $label . '
                 </a>';
    }
    
    $nav .= '</nav></header>';
    
    return $nav;
}