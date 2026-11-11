<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\GateNetwork;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GateNetworkController extends Controller
{
    /**
     * The school network the gate tablets must use. Empty switches the lock off.
     */
    public function update(Request $request, GateNetwork $network): RedirectResponse
    {
        $request->validate(['allowed_ips' => ['nullable', 'string', 'max:1000']]);
        $ips = GateNetwork::parse((string) $request->input('allowed_ips'));

        foreach ($ips as $ip) {
            [$address, $mask] = array_pad(explode('/', $ip, 2), 2, null);

            if (filter_var($address, FILTER_VALIDATE_IP) === false || ($mask !== null && ! ctype_digit($mask))) {
                throw ValidationException::withMessages(['allowed_ips' => "\"{$ip}\" bukan alamat IP yang benar."]);
            }
        }

        $network->save($ips);

        return back()->with('success', $ips === []
            ? 'Batas jaringan dimatikan: tab bisa login dari jaringan mana saja.'
            : 'Tab gerbang sekarang hanya bisa dipakai dari jaringan sekolah.');
    }
}
