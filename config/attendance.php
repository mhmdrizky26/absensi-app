<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Class Scan Window
    |--------------------------------------------------------------------------
    |
    | The first-period teacher can open a class with its code only between
    | these times on school days (24-hour "HH:MM", school timezone).
    |
    */

    'class_scan_opens_at' => env('ATTENDANCE_OPENS_AT', '06:30'),

    'class_scan_closes_at' => env('ATTENDANCE_CLOSES_AT', '08:00'),

    /*
    |--------------------------------------------------------------------------
    | Class Code Prefix
    |--------------------------------------------------------------------------
    |
    | Standard class codes are this prefix + grade number + section, e.g.
    | "ESSAR" + "7" + "A" = ESSAR7A. Admins can still set a random code.
    |
    */

    'class_code_prefix' => env('CLASS_CODE_PREFIX', 'ESSAR'),

    /*
    |--------------------------------------------------------------------------
    | School Days
    |--------------------------------------------------------------------------
    |
    | ISO weekday numbers: 1 = Monday … 7 = Sunday.
    |
    */

    'school_days' => array_map('intval', explode(',', env('ATTENDANCE_SCHOOL_DAYS', '1,2,3,4,5'))),

    /*
    |--------------------------------------------------------------------------
    | Enforce Window
    |--------------------------------------------------------------------------
    |
    | Set to false only for demos and development, to scan outside school
    | hours and on weekends. Keep it true on the school's server.
    |
    */

    'enforce_window' => (bool) env('ATTENDANCE_ENFORCE_WINDOW', true),

];
