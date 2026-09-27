<?php

namespace App\Http\Controllers\Staff;

use App\Actions\DecideDispensation;
use App\Enums\DispensationStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\DispensationRequest;
use App\Models\Dispensation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DispensationController extends Controller
{
    private const RELATIONS = ['student:id,name,nis', 'classroom:id,name,homeroom_teacher_id', 'requester:id,name', 'homeroomApprover:id,name', 'dutyApprover:id,name', 'rejecter:id,name'];

    public function index(Request $request): Response
    {
        $user = $request->user();

        $awaiting = Dispensation::query()
            ->with(self::RELATIONS)
            ->awaitingDecisionFrom($user)
            ->orderBy('starts_on')
            ->get();

        $others = $this->visibleTo($user)
            ->with(self::RELATIONS)
            ->whereNotIn('id', $awaiting->pluck('id'))
            ->where(fn (Builder $query) => $query
                ->where('status', DispensationStatus::Pending)
                ->orWhereDate('ends_on', '>=', today()->subDays(60)))
            ->latest()
            ->limit(100)
            ->get();

        return Inertia::render('Staff/Dispensations', [
            'awaiting' => $awaiting->map(fn (Dispensation $dispensation): array => $this->present($dispensation, $user)),
            'others' => $others->map(fn (Dispensation $dispensation): array => $this->present($dispensation, $user)),
            'homeroom' => $user->hasRole(Role::WaliKelas) ? $user->currentHomeroom()?->name : null,
        ]);
    }

    public function store(DispensationRequest $request, DecideDispensation $decideDispensation): RedirectResponse
    {
        $classroom = $request->classroom();

        $dispensation = Dispensation::create([
            'student_id' => $request->integer('student_id'),
            'classroom_id' => $classroom->id,
            'starts_on' => $request->date('from')->toDateString(),
            'ends_on' => $request->date('to')->toDateString(),
            'reason' => $request->validated('reason'),
            'attachment_path' => $request->file('attachment')?->store('dispensasi', 'local'),
            'requested_by' => $request->user()->id,
        ]);

        // Pengaju yang juga salah satu penyetuju langsung dihitung setuju.
        $decideDispensation->recordApproval($dispensation, $request->user());

        return back()->with('success', "Dispensasi {$dispensation->student->name} diajukan. ".$this->waitingText($dispensation->fresh()));
    }

    public function approve(Request $request, Dispensation $dispensation, DecideDispensation $decideDispensation): RedirectResponse
    {
        try {
            $markedDays = $decideDispensation->approve($dispensation, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $dispensation->refresh();

        if ($dispensation->status !== DispensationStatus::Approved) {
            return back()->with('success', 'Persetujuan Anda tercatat. '.$this->waitingText($dispensation));
        }

        return back()->with('success', $markedDays > 0
            ? "Dispensasi {$dispensation->student->name} disetujui. {$markedDays} hari sekolah dicatat Dispensasi."
            : "Dispensasi {$dispensation->student->name} disetujui. Tidak ada hari yang diubah karena siswa sudah tercatat hadir atau tanggalnya bukan hari sekolah.");
    }

    public function reject(Request $request, Dispensation $dispensation, DecideDispensation $decideDispensation): RedirectResponse
    {
        $request->validate(['rejection_reason' => ['required', 'string', 'max:200']], attributes: ['rejection_reason' => 'Alasan penolakan']);

        try {
            $decideDispensation->reject($dispensation, $request->user(), $request->string('rejection_reason')->value());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Dispensasi {$dispensation->student->name} ditolak.");
    }

    /**
     * Withdraw a request that nobody has finished deciding on yet.
     */
    public function destroy(Request $request, Dispensation $dispensation): RedirectResponse
    {
        abort_unless($this->canCancel($dispensation, $request->user()), 403);

        if ($dispensation->attachment_path) {
            Storage::disk('local')->delete($dispensation->attachment_path);
        }

        $dispensation->delete();

        return back()->with('success', 'Pengajuan dispensasi dibatalkan.');
    }

    public function attachment(Request $request, Dispensation $dispensation): StreamedResponse
    {
        abort_if($dispensation->attachment_path === null, 404);
        abort_unless($this->visibleTo($request->user())->whereKey($dispensation->id)->exists(), 403);

        return Storage::disk('local')->response($dispensation->attachment_path);
    }

    /**
     * @return Builder<Dispensation>
     */
    private function visibleTo(User $user): Builder
    {
        return Dispensation::query()->when(
            $user->hasRole(Role::WaliKelas),
            fn (Builder $query) => $query->where('classroom_id', $user->currentHomeroom()?->id ?? 0),
        );
    }

    private function canCancel(Dispensation $dispensation, User $user): bool
    {
        return $dispensation->isPending() && ($user->hasRole(Role::Admin) || $dispensation->requested_by === $user->id);
    }

    private function waitingText(Dispensation $dispensation): string
    {
        $missing = array_filter([
            $dispensation->homeroom_approved_at === null ? 'wali kelas' : null,
            $dispensation->duty_approved_at === null ? 'guru piket' : null,
        ]);

        return 'Menunggu persetujuan '.implode(' dan ', $missing).'.';
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Dispensation $dispensation, User $user): array
    {
        return [
            'id' => $dispensation->id,
            'student' => $dispensation->student->only('id', 'name', 'nis'),
            'classroom' => $dispensation->classroom->name,
            'from' => $dispensation->starts_on->toDateString(),
            'to' => $dispensation->ends_on->toDateString(),
            'reason' => $dispensation->reason,
            'status' => $dispensation->status->value,
            'statusLabel' => $dispensation->status->label(),
            'requester' => $dispensation->requester?->name,
            'createdAt' => $dispensation->created_at->toIso8601String(),
            'homeroomApproval' => $dispensation->homeroom_approved_at ? ['by' => $dispensation->homeroomApprover?->name, 'at' => $dispensation->homeroom_approved_at->toIso8601String()] : null,
            'dutyApproval' => $dispensation->duty_approved_at ? ['by' => $dispensation->dutyApprover?->name, 'at' => $dispensation->duty_approved_at->toIso8601String()] : null,
            'rejection' => $dispensation->rejected_at ? ['by' => $dispensation->rejecter?->name, 'at' => $dispensation->rejected_at->toIso8601String(), 'reason' => $dispensation->rejection_reason] : null,
            'hasAttachment' => $dispensation->attachment_path !== null,
            'hasHomeroomTeacher' => $dispensation->classroom->homeroom_teacher_id !== null,
            'canDecide' => $dispensation->awaitsDecisionFrom($user),
            'canCancel' => $this->canCancel($dispensation, $user),
        ];
    }
}
