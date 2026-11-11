<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CardDesignRequest;
use App\Models\Student;
use App\Support\CardDesign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The look of printed student cards: a built-in design, or the school's own
 * background image with the QR code, name and NIS placed on it.
 */
class CardDesignController extends Controller
{
    public function edit(CardDesign $cardDesign): Response
    {
        $student = Student::query()->where('status', StudentStatus::Active)->whereNull('qr_revoked_at')->orderBy('name')->first();

        return Inertia::render('Admin/Cards/Design', [
            'design' => $cardDesign->forFrontend(),
            'defaults' => [
                'landscape' => CardDesign::defaults('landscape'),
                'portrait' => CardDesign::defaults('portrait'),
            ],
            'minQrSize' => CardDesign::MIN_QR_SIZE,
            'sample' => $student?->toCard() ?? [
                'id' => 0, 'name' => 'Ayu Lestari Rahmawati', 'nis' => '260001', 'nisn' => null,
                'payload' => 'ABS-CONTOHKARTU1', 'version' => 1, 'issuedAt' => null, 'isRevoked' => false,
            ],
        ]);
    }

    public function update(CardDesignRequest $request, CardDesign $cardDesign): RedirectResponse
    {
        $current = $cardDesign->get();

        if ($request->validated('mode') === 'custom' && $current['background'] === null) {
            return back()->with('error', 'Unggah gambar latar desain sekolah dulu sebelum memakai desain sekolah.');
        }

        $cardDesign->save([
            ...$current,
            'mode' => $request->validated('mode'),
            'orientation' => $request->validated('orientation'),
            'qr' => array_map('floatval', $request->validated('qr')),
            'name' => $this->textBox($request->validated('name')),
            'nis' => $this->textBox($request->validated('nis')),
        ]);

        return back()->with('success', 'Desain kartu disimpan. Kartu yang dicetak berikutnya memakai desain ini; kartu lama tetap bisa dipakai absen.');
    }

    /**
     * The school's background image, at the card's size (e.g. 1011 × 638 px
     * for landscape or 638 × 1011 px for portrait at 300 dpi).
     */
    public function storeBackground(Request $request, CardDesign $cardDesign): RedirectResponse
    {
        $request->validate([
            'background' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:5120'],
        ], [], ['background' => 'Gambar latar']);

        $design = $cardDesign->get();

        if ($design['background']) {
            Storage::disk(CardDesign::BACKGROUND_DISK)->delete($design['background']);
        }

        $path = $request->file('background')->storeAs('kartu', 'latar.'.$request->file('background')->extension(), CardDesign::BACKGROUND_DISK);

        $cardDesign->save([...$design, 'background' => $path, 'backgroundVersion' => $design['backgroundVersion'] + 1]);

        return back()->with('success', 'Gambar latar diunggah. Atur posisi QR, nama, dan NIS lalu simpan.');
    }

    public function destroyBackground(CardDesign $cardDesign): RedirectResponse
    {
        $design = $cardDesign->get();

        if ($design['background']) {
            Storage::disk(CardDesign::BACKGROUND_DISK)->delete($design['background']);
        }

        $cardDesign->save([
            ...$design,
            'background' => null,
            'mode' => $design['mode'] === 'custom' ? 'landscape' : $design['mode'],
        ]);

        return back()->with('success', 'Gambar latar dihapus. Kartu kembali memakai desain bawaan.');
    }

    public function background(CardDesign $cardDesign): StreamedResponse
    {
        $path = $cardDesign->get()['background'];
        abort_if($path === null || ! Storage::disk(CardDesign::BACKGROUND_DISK)->exists($path), 404);

        return Storage::disk(CardDesign::BACKGROUND_DISK)->response($path, null, ['Cache-Control' => 'private, max-age=31536000']);
    }

    /**
     * @param  array<string, mixed>  $box
     * @return array{x: float, y: float, width: float, size: float, color: string, align: string}
     */
    private function textBox(array $box): array
    {
        return [
            'x' => (float) $box['x'],
            'y' => (float) $box['y'],
            'width' => (float) $box['width'],
            'size' => (float) $box['size'],
            'color' => strtolower($box['color']),
            'align' => $box['align'],
        ];
    }
}
