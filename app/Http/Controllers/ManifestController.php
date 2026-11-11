<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ManifestController extends Controller
{
    /**
     * The web app manifest, so a gate tablet or phone can add the app to its
     * home screen and open it full screen, under the name set in Pengaturan.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'name' => config('app.name').' · '.config('app.school_name'),
            'short_name' => config('app.name'),
            'description' => 'Absensi siswa dengan kartu QR.',
            'lang' => 'id',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#2360e8',
            'icons' => [
                ['src' => '/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_SLASHES);
    }
}
