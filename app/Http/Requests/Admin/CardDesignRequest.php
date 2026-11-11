<?php

namespace App\Http\Requests\Admin;

use App\Support\CardDesign;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The chosen card design and, for the school's own design, where the QR
 * code, name and NIS sit on the card (millimetres).
 */
class CardDesignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $text = fn (string $field): array => [
            "{$field}.x" => ['required', 'numeric', 'min:0'],
            "{$field}.y" => ['required', 'numeric', 'min:0'],
            "{$field}.width" => ['required', 'numeric', 'min:10'],
            "{$field}.size" => ['required', 'numeric', 'between:1.5,10'],
            "{$field}.color" => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            "{$field}.align" => ['required', Rule::in(['left', 'center', 'right'])],
        ];

        return [
            'mode' => ['required', Rule::in(CardDesign::MODES)],
            'orientation' => ['required', Rule::in(CardDesign::ORIENTATIONS)],
            'qr.x' => ['required', 'numeric', 'min:0'],
            'qr.y' => ['required', 'numeric', 'min:0'],
            'qr.size' => ['required', 'numeric', 'min:'.CardDesign::MIN_QR_SIZE],
            ...$text('name'),
            ...$text('nis'),
        ];
    }

    /**
     * Everything must stay inside the card.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $width = CardDesign::widthOf($this->input('orientation'));
                $height = CardDesign::heightOf($this->input('orientation'));
                $qr = $this->input('qr');

                if ($width + 0.01 < $qr['x'] + $qr['size'] || $height + 0.01 < $qr['y'] + $qr['size']) {
                    $validator->errors()->add('qr.size', 'QR keluar dari kartu. Perkecil atau geser ke dalam.');
                }

                foreach (['name' => 'Nama', 'nis' => 'NIS'] as $field => $label) {
                    $box = $this->input($field);

                    if ($width + 0.01 < $box['x'] + $box['width'] || $box['y'] > $height - 2) {
                        $validator->errors()->add("{$field}.x", "{$label} keluar dari kartu. Geser ke dalam atau persempit.");
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'qr.size' => 'Ukuran QR',
            'name.size' => 'Ukuran huruf nama',
            'nis.size' => 'Ukuran huruf NIS',
            'name.color' => 'Warna nama',
            'nis.color' => 'Warna NIS',
        ];
    }
}
