<?php

namespace App\Http\Controllers\Recap;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recap\GuardianContactRequest;
use App\Models\GuardianContact;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;

class GuardianContactController extends Controller
{
    /**
     * Guru BK adds a parent's WhatsApp number straight from a student's risk card.
     */
    public function store(GuardianContactRequest $request, Student $student): RedirectResponse
    {
        $contact = $student->guardianContacts()->create([
            'name' => $request->validated('name'),
            'relation' => $request->validated('relation') ?: null,
            'phone' => $request->validated('phone'),
            'consented_at' => $request->boolean('consented') ? now() : null,
        ]);

        $note = $contact->consented_at ? 'Pesan risiko sekarang bisa dikirim ke nomor ini.' : 'Nomor belum ditandai setuju, jadi belum akan dikirimi pesan.';

        return back()->with('success', 'Nomor '.GuardianContact::displayPhone($contact->phone)." ditambahkan untuk orang tua {$student->name}. {$note}");
    }
}
