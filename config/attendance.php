<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Gate Hours
    |--------------------------------------------------------------------------
    |
    | When the gate opens and when it closes (jam masuk), 24-hour "HH:MM" in
    | the school timezone. Only defaults: the admin sets the hours of each
    | school day in Pengaturan.
    |
    */

    'opens_at' => env('ATTENDANCE_OPENS_AT', '06:30'),

    'closes_at' => env('ATTENDANCE_CLOSES_AT', '08:00'),

    /*
    |--------------------------------------------------------------------------
    | School Days
    |--------------------------------------------------------------------------
    |
    | ISO weekday numbers: 1 = Monday … 7 = Sunday. Only a default until the
    | admin saves the weekly schedule in Pengaturan.
    |
    */

    'school_days' => array_map('intval', explode(',', env('ATTENDANCE_SCHOOL_DAYS', '1,2,3,4,5'))),

    /*
    |--------------------------------------------------------------------------
    | Automatic Finish
    |--------------------------------------------------------------------------
    |
    | If the guru piket forgot "Selesaikan absen", the day is finished at this
    | time: students without a mark become Alpa. Admins change it in
    | Pengaturan.
    |
    */

    'finalize_at' => env('ATTENDANCE_FINALIZE_AT', '23:00'),

    /*
    |--------------------------------------------------------------------------
    | Enforce Window
    |--------------------------------------------------------------------------
    |
    | Set to false only for demos and development, to scan outside school
    | hours and on weekends. Admins switch this with "Mode demo" in
    | Pengaturan, which overrules this value.
    |
    */

    'enforce_window' => (bool) env('ATTENDANCE_ENFORCE_WINDOW', true),

];
