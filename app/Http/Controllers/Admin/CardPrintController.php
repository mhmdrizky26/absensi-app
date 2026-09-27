<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CardPrintController extends Controller
{
    /**
     * Upper bound on cards per print sheet, so a mistyped request cannot ask
     * the browser to render the whole school at once.
     */
    private const MAX_CARDS = 200;

    /**
     * A printable A4 sheet of cards, for either a whole classroom
     * (?classroom=ID) or chosen students (?students=1,2,3). Revoked cards and
     * inactive students are skipped.
     */
    public function show(Request $request): Response
    {
        $query = Student::query()
            ->where('status', StudentStatus::Active)
            ->whereNull('qr_revoked_at')
            ->orderBy('name');

        $title = 'Kartu terpilih';

        if ($request->filled('classroom')) {
            $classroom = Classroom::findOrFail($request->integer('classroom'));
            $query->whereHas('classrooms', fn (Builder $query) => $query->whereKey($classroom->id));
            $title = "Kelas {$classroom->name}";
        } else {
            $ids = array_filter(array_map('intval', explode(',', $request->string('students'))));
            $query->whereIn('id', $ids ?: [0]);
        }

        return Inertia::render('Admin/Cards/Print', [
            'title' => $title,
            'cards' => $query->limit(self::MAX_CARDS)->get()->map(fn (Student $student): array => $student->toCard()),
        ]);
    }
}
