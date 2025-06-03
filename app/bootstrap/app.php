<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withSchedule(function (Schedule $schedule) { // <-- BAGIAN UNTUK PENJADWALAN
        $schedule->command('app:send-loan-due-date-reminders --days=3')->dailyAt('08:00')->timezone('Asia/Jakarta'); // Kirim H-3
        $schedule->command('app:send-loan-due-date-reminders --days=1')->dailyAt('08:05')->timezone('Asia/Jakarta'); // Kirim H-1
        // Anda bisa menambahkan perintah terjadwal lainnya di sini
    })->create();
