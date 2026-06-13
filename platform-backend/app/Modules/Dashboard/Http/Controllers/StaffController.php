<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Http\Controllers;

use App\Modules\Catalog\Infrastructure\Models\Location;
use App\Modules\Catalog\Infrastructure\Models\Service;
use App\Modules\Scheduling\Application\AvailabilityCacheVersion;
use App\Modules\Staff\Infrastructure\Models\StaffMember;
use App\Modules\TenantManagement\Application\QuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Gestione operatori (Fase 4) + orari settimanali (Fase 5, parte staff):
 * la UI espone le fasce mattina/pomeriggio per giorno; il salvataggio
 * sostituisce atomicamente le regole e invalida la cache disponibilità
 * (stessa semantica del PUT API esistente — nessuna logica duplicata).
 */
final class StaffController extends Controller
{
    public function index(): View
    {
        return view('dashboard.staff.index', [
            'staff' => StaffMember::query()
                ->with('services:id,name')
                ->orderBy('sort_order')->orderBy('display_name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(null);
    }

    public function store(Request $request, QuotaService $quota): RedirectResponse
    {
        $data = $this->validated($request);

        $quota->assertWithinQuota('max_staff', StaffMember::query()->count());

        $staff = StaffMember::query()->create([
            'display_name' => $data['display_name'],
            'role_label' => $data['role_label'] ?? null,
            'is_bookable' => $request->boolean('is_bookable', true),
        ]);

        $this->syncServices($staff, $data['service_ids'] ?? []);
        $this->saveWeeklySchedule($request, $staff);

        return redirect()->route('dashboard.staff.index')
            ->with('status', 'Operatore creato.');
    }

    public function edit(string $uuid): View
    {
        return $this->form(
            StaffMember::query()->where('uuid', $uuid)
                ->with(['services:id', 'schedules'])
                ->firstOrFail(),
        );
    }

    public function update(Request $request, string $uuid): RedirectResponse
    {
        $staff = StaffMember::query()->where('uuid', $uuid)->firstOrFail();
        $data = $this->validated($request);

        $staff->update([
            'display_name' => $data['display_name'],
            'role_label' => $data['role_label'] ?? null,
            'is_bookable' => $request->boolean('is_bookable'),
        ]);

        $this->syncServices($staff, $data['service_ids'] ?? []);
        $this->saveWeeklySchedule($request, $staff);

        return redirect()->route('dashboard.staff.index')
            ->with('status', 'Operatore aggiornato.');
    }

    /** Disattivazione (soft delete): la storia in agenda resta. */
    public function destroy(string $uuid): RedirectResponse
    {
        StaffMember::query()->where('uuid', $uuid)->firstOrFail()->delete();

        return redirect()->route('dashboard.staff.index')
            ->with('status', 'Operatore disattivato.');
    }

    private function form(?StaffMember $staff): View
    {
        return view('dashboard.staff.form', [
            'staff' => $staff,
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
            'schedule' => $this->scheduleMatrix($staff),
        ]);
    }

    /**
     * Matrice weekday → [morning(start,end), afternoon(start,end)] per la
     * UI a fasce. Le regole esistenti vengono distribuite: la prima che
     * inizia prima delle 13 è "mattina", la successiva "pomeriggio".
     *
     * @return array<int, array{morning: array{start:?string,end:?string}, afternoon: array{start:?string,end:?string}}>
     */
    private function scheduleMatrix(?StaffMember $staff): array
    {
        $matrix = [];

        foreach (range(0, 6) as $weekday) {
            $matrix[$weekday] = [
                'morning' => ['start' => null, 'end' => null],
                'afternoon' => ['start' => null, 'end' => null],
            ];
        }

        foreach ($staff?->schedules ?? [] as $rule) {
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

    /** @param list<int|string> $serviceIds */
    private function syncServices(StaffMember $staff, array $serviceIds): void
    {
        $ids = Service::query()->whereIn('id', $serviceIds)->pluck('id');

        $staff->services()->sync(
            $ids->mapWithKeys(fn (int $id): array => [$id => ['tenant_id' => $staff->tenant_id]])->all()
        );
    }

    /**
     * Sostituisce le regole settimanali con le fasce inviate dal form
     * (input: schedule[weekday][band][start|end]) e invalida la cache
     * disponibilità sull'orizzonte di prenotazione.
     */
    private function saveWeeklySchedule(Request $request, StaffMember $staff): void
    {
        $input = (array) $request->input('schedule', []);
        $location = Location::query()->orderBy('id')->firstOrFail();

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
                    abort(redirect()->back()->withErrors([
                        'schedule' => 'Fascia oraria non valida per ' . self::weekdayName($weekday) . '.',
                    ])->withInput());
                }

                $rules[] = ['weekday' => $weekday, 'start' => $start, 'end' => $end];
            }
        }

        DB::transaction(function () use ($staff, $location, $rules): void {
            $staff->schedules()->where('location_id', $location->id)->delete();

            foreach ($rules as $rule) {
                $staff->schedules()->create([
                    'tenant_id' => $staff->tenant_id,
                    'location_id' => $location->id,
                    'weekday' => $rule['weekday'],
                    'start_time' => $rule['start'],
                    'end_time' => $rule['end'],
                ]);
            }
        });

        $cache = app(AvailabilityCacheVersion::class);

        foreach (range(0, min($location->booking_window_days, 60)) as $offset) {
            $cache->bump($staff->tenant_id, $staff->id, now()->addDays($offset)->format('Y-m-d'));
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'role_label' => ['nullable', 'string', 'max:255'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer'],
        ], [], ['display_name' => 'nome']);
    }

    public static function weekdayName(int $weekday): string
    {
        return ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica'][$weekday];
    }
}
