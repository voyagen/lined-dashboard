<?php

spl_autoload_register(function ($className) {
    // Define search paths in order of preference
    $directories = [
        __DIR__ . '/classes/',
        __DIR__ . '/classes/models/',
        __DIR__ . '/classes/repositories/'
    ];
    
    foreach ($directories as $directory) {
        $classFile = $directory . $className . '.php';
        if (file_exists($classFile)) {
            require_once $classFile;
            return true;
        }
    }
    
    return false;
});