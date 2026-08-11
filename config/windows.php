<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Window Driver
    |--------------------------------------------------------------------------
    |
    | Used by Window::make() when no driver is passed. Companions publish
    | config/windows/<slug>.php and register via extend().
    |
    */

    'default' => env('WINDOW_DRIVER', 'sdl3'),

    /*
    |--------------------------------------------------------------------------
    | Driver Registry
    |--------------------------------------------------------------------------
    |
    | class: WindowHandler concrete (title, width, height)
    | extension: optional PHP extension required before create()
    |
    */

    'drivers' => [
        // Merged from config/windows/*.php and companion extend() calls.
    ],

];
