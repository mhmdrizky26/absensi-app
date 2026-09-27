<?php

namespace App\Http\Controllers\Staff;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Show the parent's letter attached to a Sakit/Izin mark. Letters are
     * kept on the private disk; a wali kelas sees only their own class's.
     */
    public function show(Request $request, Attendance $attendance): StreamedResponse
    {
        $user = $request->user();

        abort_if($attendance->attachment_path === null, 404);
        abort_if(
            $user->hasRole(Role::WaliKelas) && $user->currentHomeroom()?->id !== $attendance->classroom_id,
            403,
        );

        return Storage::disk('local')->response($attendance->attachment_path);
    }
}
