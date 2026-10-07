<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\PasswordUpdateRequest;
use App\Http\Requests\Account\UsernameUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/**
 * The signed-in staff member's own account, edited from the profile modal.
 */
class ProfileController extends Controller
{
    public function updateUsername(UsernameUpdateRequest $request): RedirectResponse
    {
        $request->user()->update(['username' => $request->validated('username')]);

        return back()->with('success', "Username diganti menjadi {$request->validated('username')}. Pakai username ini saat masuk berikutnya.");
    }

    /**
     * Rotating the remember token signs out other devices that used "Tetap masuk".
     */
    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();

        return back()->with('success', 'Password berhasil diganti.');
    }
}
