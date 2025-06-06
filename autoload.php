<?php

/**
 * Autoload function to include PHP files from the resource/function directory
 * 
 * @param string $fun The name of the function file to load (without .php extension)
 * @return void
 */
$autoload = function(string $fun) {
    global $db, $errors,$success;
    $path = __DIR__ . '/resource/function/' . $fun . '.php';
    if (file_exists($path)) {
        require_once $path;
    } else {
        trigger_error("Function file '$fun.php' not found", E_USER_WARNING);
    }
};

// Contoh pemakaian:
$autoload('setting');
$autoload("navbar");
$autoload("form");
$autoload("preview");
$autoload("api");
$autoload("route");
$autoload("validate");