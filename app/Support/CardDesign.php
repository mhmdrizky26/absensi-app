<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * How student cards look when printed. Three choices: the built-in
 * landscape card, the built-in portrait card, or the school's own design: a
 * background image made by the school (portrait or landscape) with the QR
 * code, name and NIS placed on it. Positions are millimetres from the
 * card's top-left corner. A card is always CR80 (85.6 × 54 mm, like an ID card).
 */
class CardDesign
{
    public const SETTING = 'card_design';

    public const MODES = ['landscape', 'portrait', 'custom'];

    public const ORIENTATIONS = ['landscape', 'portrait'];

    public const LONG_SIDE = 85.6;

    public const SHORT_SIDE = 54.0;

    /**
     * QR codes smaller than this are not read reliably by the gate tablet.
     */
    public const MIN_QR_SIZE = 25;

    public const BACKGROUND_DISK = 'local';

    /**
     * @return array{mode: string, orientation: string, background: ?string, backgroundVersion: int, qr: array{x: float, y: float, size: float}, name: array{x: float, y: float, width: float, size: float, color: string, align: string}, nis: array{x: float, y: float, width: float, size: float, color: string, align: string}}
     */
    public function get(): array
    {
        $stored = json_decode((string) Setting::value(self::SETTING), true);

        if (! is_array($stored)) {
            return self::defaults('landscape');
        }

        $orientation = in_array($stored['orientation'] ?? null, self::ORIENTATIONS, true) ? $stored['orientation'] : 'landscape';

        return array_replace_recursive(self::defaults($orientation), $stored, [
            'mode' => in_array($stored['mode'] ?? null, self::MODES, true) ? $stored['mode'] : 'landscape',
            'orientation' => $orientation,
        ]);
    }

    /**
     * @param  array<string, mixed>  $design
     */
    public function save(array $design): void
    {
        Setting::put([self::SETTING => json_encode($design)]);
    }

    /**
     * The design as the card screens need it, with the background's URL.
     *
     * @return array<string, mixed>
     */
    public function forFrontend(): array
    {
        $design = $this->get();
        $hasBackground = $design['background'] !== null && Storage::disk(self::BACKGROUND_DISK)->exists($design['background']);

        return [
            ...collect($design)->except('background')->all(),
            'backgroundUrl' => $hasBackground ? route('cards.design.background', ['v' => $design['backgroundVersion']]) : null,
            'width' => self::widthOf($design['orientation']),
            'height' => self::heightOf($design['orientation']),
        ];
    }

    public static function widthOf(string $orientation): float
    {
        return $orientation === 'portrait' ? self::SHORT_SIDE : self::LONG_SIDE;
    }

    public static function heightOf(string $orientation): float
    {
        return $orientation === 'portrait' ? self::LONG_SIDE : self::SHORT_SIDE;
    }

    /**
     * Sensible starting positions for the school's own design.
     *
     * @return array{mode: string, orientation: string, background: ?string, backgroundVersion: int, qr: array{x: float, y: float, size: float}, name: array{x: float, y: float, width: float, size: float, color: string, align: string}, nis: array{x: float, y: float, width: float, size: float, color: string, align: string}}
     */
    public static function defaults(string $orientation): array
    {
        $positions = $orientation === 'portrait'
            ? [
                'qr' => ['x' => 7.0, 'y' => 16.0, 'size' => 40.0],
                'name' => ['x' => 4.0, 'y' => 60.0, 'width' => 46.0, 'size' => 4.2, 'color' => '#000000', 'align' => 'center'],
                'nis' => ['x' => 4.0, 'y' => 72.0, 'width' => 46.0, 'size' => 2.8, 'color' => '#000000', 'align' => 'center'],
            ]
            : [
                'qr' => ['x' => 49.0, 'y' => 10.0, 'size' => 34.0],
                'name' => ['x' => 4.0, 'y' => 20.0, 'width' => 42.0, 'size' => 4.4, 'color' => '#000000', 'align' => 'left'],
                'nis' => ['x' => 4.0, 'y' => 34.0, 'width' => 42.0, 'size' => 2.8, 'color' => '#000000', 'align' => 'left'],
            ];

        return ['mode' => 'landscape', 'orientation' => $orientation, 'background' => null, 'backgroundVersion' => 0, ...$positions];
    }
}
