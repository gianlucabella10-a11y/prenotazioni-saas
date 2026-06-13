<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Http\Controllers;

use App\Modules\Catalog\Infrastructure\Models\Location;
use App\Modules\Scheduling\Application\AvailabilityCacheVersion;
use App\Modules\Scheduling\Infrastructure\Models\ScheduleException;
use App\Modules\Staff\Infrastructure\Models\StaffMember;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Disponibilità (Fase 5): orari della sede + eccezioni (ferie, chiusure,
 * blocchi). Gli orari per OPERATORE — quelli che governano il motore di
 * prenotazione (docs/30) — si gestiscono nella scheda operatore; qui la UI
 * lo dichiara esplicitamente. Ogni eccezione invalida la cache slot dei
 * giorni/operatori coinvolti.
 */
final class AvailabilityController extends Controller
{
    public function index(): View
    {
        $location = Location::query()->orderBy('id')->with('schedules')->firstOrFail();

        return view('dashboard.availability.index', [
            'location' => $location,
            'exceptions' => ScheduleException::query()
                ->where('date_end', '>=', now()->subDay()->format('Y-m-d'))
                ->with('staffMember:id,uuid,display_name')
                ->orderBy('date_start')
                ->get(),
            'staff' => StaffMember::query()->orderBy('display_name')->get(['id', 'uuid', 'display_name']),
            'locationSchedule' => $this->locationMatrix($location),
        ]);
    }

    /** Orari sede (informativi/default — il motore usa gli orari staff). */
    public function updateLocationHours(Request $request): RedirectResponse
    {
        $location = Location::query()->orderBy('id')->firstOrFail();
        $input = (array) $request->input('schedule', []);

        $rules = [];

        foreach (range(0, 6) as $weekday) {
            foreach (['morning', 'afternoon'] as $band) {
                $start = trim((string) ($input[$weekday][$band]['start'] ?? ''));
                $end = trim((string) ($input[$weekday][$band]['end'] ?? ''));

                if ($start === '' && $end === '') {
                    continue;
                }

                if (! preg_match('/^\d{2}:\d{2}$/', $start)
                    || ! preg_match('/^\d{2}:\d{2}$/', $end)
                    || $end <= $start
                ) {
                    return back()->withErrors([
                        'schedule' => 'Fascia non valida per '
                            . StaffController::weekdayName($weekday) . '.',
                    ])->withInput();
                }

                $rules[] = ['weekday' => $weekday, 'start' => $start, 'end' => $end];
            }
        }

        DB::transaction(function () use ($location, $rules): void {
            $location->schedules()->delete();

            foreach ($rules as $rule) {
                $location->schedules()->create([
                    'tenant_id' => $location->tenant_id,
                    'weekday' => $rule['weekday'],
                    'start_time' => $rule['start'],
                    'end_time' => $rule['end'],
                ]);
            }
        });

        return back()->with('status', 'Orari della sede aggiornati.');
    }

    /** Crea un'eccezione: ferie/chiusura, di sede o di singolo operatore. */
    public function storeException(Request $request, AvailabilityCacheVersion $cache): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', 'in:location,staff'],
            'staff_uuid' => ['required_if:scope,staff', 'nullable', 'uuid'],
            'date_start' => ['required', 'date_format:Y-m-d'],
            'date_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_start'],
            'time_start' => ['nullable', 'date_format:H:i', 'required_with:time_end'],
            'time_end' => ['nullable', 'date_format:H:i', 'after:time_start'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [], ['date_start' => 'data inizio', 'date_end' => 'data fine']);

        $location = Location::query()->orderBy('id')->firstOrFail();

        $staff = $data['scope'] === 'staff'
            ? StaffMember::query()->where('uuid', $data['staff_uuid'])->firstOrFail()
            : null;

        ScheduleException::query()->create([
            'scope' => $data['scope'],
            'location_id' => $data['scope'] === 'location' ? $location->id : null,
            'staff_member_id' => $staff?->id,
            'date_start' => $data['date_start'],
            'date_end' => $data['date_end'],
            'time_start' => $data['time_start'] ?? null,
            'time_end' => $data['time_end'] ?? null,
            'kind' => ScheduleException::KIND_CLOSED,
            'reason' => $data['reason'] ?? null,
        ]);

        $this->bumpRange($cache, $location, $staff, $data['date_start'], $data['date_end']);

        return back()->with('status', 'Chiusura registrata.');
    }

    public function destroyException(Request $request, AvailabilityCacheVersion $cache, string $uuid): RedirectResponse
    {
        $exception = ScheduleException::query()->where('uuid', $uuid)->firstOrFail();
        $location = Location::query()->orderBy('id')->firstOrFail();

        $staff = $exception->staff_member_id === null
            ? null
            : StaffMember::query()->whereKey($exception->staff_member_id)->first();

        $exception->delete();

        $this->bumpRange($cache, $location, $staff, $exception->date_start, $exception->date_end);

        return back()->with('status', 'Chiusura rimossa.');
    }

    /** Invalida la cache slot per i giorni e gli operatori toccati. */
    private function bumpRange(
        AvailabilityCacheVersion $cache,
        Location $location,
        ?StaffMember $staff,
        string $dateStart,
        string $dateEnd,
    ): void {
        $staffIds = $staff !== null
            ? [$staff->id]
            : StaffMember::query()->pluck('id')->all();

        $cursor = new DateTimeImmutable($dateStart);
        $end = new DateTimeImmutable($dateEnd);
        $guard = 0;

        while ($cursor <= $end && $guard < 120) {
            foreach ($staffIds as $staffId) {
                $cache->bump($location->tenant_id, (int) $staffId, $cursor->format('Y-m-d'));
            }

            $cursor = $cursor->modify('+1 day');
            $guard++;
        }
    }

    /** @return array<int, array{morning: array{start:?string,end:?string}, afternoon: array{start:?string,end:?string}}> */
    private function locationMatrix(Location $location): array
    {
        $matrix = [];

        foreach (range(0, 6) as $weekday) {
            $matrix[$weekday] = [
                'morning' => ['start' => null, 'end' => null],
                'afternoon' => ['start' => null, 'end' => null],
            ];
        }

        foreach ($location->schedules as $rule) {
            $start = substr((string) $rule->start_time, 0, 5);
            $end = substr((string) $rule->end_time, 0, 5);
            $band = $start < '13:00' ? 'morning' : 'afternoon';

            if ($matrix[$rule->weekday][$band]['start'] !== null) {
                $band = $band === 'morning' ? 'afternoon' : 'morning';
            }

            $matrix[$rule->weekday][$band] = ['start' => $start, 'end' => $end];
        }

        return $matrix;
    }
}
