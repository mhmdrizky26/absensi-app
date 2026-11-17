<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tiap menit supaya absen selesai tepat pada jam yang diatur admin; kalau
// tidak ada yang perlu diselesaikan, perintah ini hanya beberapa query ringan.
Schedule::command('attendance:finalize-days')->everyMinute()->withoutOverlapping();
Schedule::command('risiko:kirim-wa')->everyMinute()->withoutOverlapping();

// Shared hosting tidak bisa menjalankan queue worker terus-menerus: setiap menit cron
// mengosongkan antrean (pesan WA orang tua) lalu berhenti sebelum menit berikutnya.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=1')->everyMinute()->withoutOverlapping();
