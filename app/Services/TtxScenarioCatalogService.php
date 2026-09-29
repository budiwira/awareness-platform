<?php

namespace App\Services;

use App\Domain\Tabletop\ExerciseCapabilityCatalog;
use App\Models\TtxExercise;
use App\Models\TtxScenarioInjectTemplate;
use App\Models\TtxScenarioTemplate;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TtxScenarioCatalogService
{
    /** @param Collection<int, TtxScenarioInjectTemplate>|null $injects */
    public function assertPublishable(TtxScenarioTemplate $template, ?Collection $injects = null): void
    {
        $codes = ExerciseCapabilityCatalog::normalize($template->capability_codes, 'capability_codes') ?? [];
        if (trim($template->title) === '' || trim((string) $template->scenario) === '' || $codes === []) {
            throw ValidationException::withMessages(['scenario' => 'Judul, narasi, dan sedikitnya satu capability wajib diisi sebelum terbit.']);
        }

        $injects ??= $template->injects()->where('status', 'active')->orderBy('order')->get();
        if ($injects->isEmpty()) {
            throw ValidationException::withMessages(['injects' => 'Tambahkan sedikitnya satu inject aktif sebelum terbit.']);
        }

        foreach ($injects->values() as $index => $inject) {
            if ($inject->order !== $index + 1 || trim($inject->title) === '') {
                throw ValidationException::withMessages(['injects' => 'Urutan atau judul inject aktif tidak valid.']);
            }
            $injectCodes = ExerciseCapabilityCatalog::normalize($inject->capability_codes, 'injects') ?? [];
            if ($injectCodes === [] || array_diff($injectCodes, $codes) !== []) {
                throw ValidationException::withMessages(['injects' => 'Capability setiap inject aktif wajib terisi dan termasuk capability scenario.']);
            }
        }
    }

    /** @param list<int> $ids */
    public function reorder(TtxScenarioTemplate $template, array $ids): void
    {
        DB::transaction(function () use ($template, $ids) {
            $locked = TtxScenarioTemplate::query()->lockForUpdate()->findOrFail($template->id);
            abort_if($locked->status === 'archived', 422);
            $injects = $locked->injects()->where('status', 'active')->lockForUpdate()->get()->keyBy('id');
            if (count($ids) !== $injects->count() || count(array_unique($ids)) !== count($ids)
                || array_diff($ids, $injects->keys()->all()) !== []) {
                throw ValidationException::withMessages(['inject_ids' => 'Daftar urutan harus memuat semua inject aktif milik scenario ini tepat satu kali.']);
            }
            $temporary = (int) $injects->max('order') + 1;
            foreach ($ids as $id) {
                $injects[$id]->update(['order' => $temporary++]);
            }
            foreach ($ids as $index => $id) {
                $injects[$id]->update(['order' => $index + 1]);
            }
            if ($locked->status === 'published') {
                $this->assertPublishable($locked);
            }
            Audit::log('ttx.scenario_inject_reordered', $locked, ['inject_ids' => $ids]);
        });
    }

    public function normalizeActiveOrders(TtxScenarioTemplate $template): void
    {
        $injects = $template->injects()->where('status', 'active')->lockForUpdate()->get();
        $temporary = (int) $injects->max('order') + 1;
        foreach ($injects as $inject) {
            $inject->update(['order' => $temporary++]);
        }
        foreach ($injects->values() as $index => $inject) {
            $inject->update(['order' => $index + 1]);
        }
    }

    public function instantiate(User $actor, TtxScenarioTemplate $template): TtxExercise
    {
        abort_unless($actor->is_active && $actor->isTenantAdmin() && $actor->tenant_id !== null, 403);

        $startsTransaction = DB::transactionLevel() === 0;

        return DB::transaction(function () use ($actor, $template, $startsTransaction) {
            if ($startsTransaction && DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }
            $locked = TtxScenarioTemplate::query()
                ->select('ttx_scenario_templates.*')
                ->selectRaw(<<<'SQL'
                    (SELECT COALESCE(jsonb_agg(to_jsonb(inject) ORDER BY inject."order"), '[]'::jsonb)
                     FROM ttx_scenario_inject_templates AS inject
                     WHERE inject.scenario_template_id = ttx_scenario_templates.id AND inject.status = 'active') AS active_inject_snapshot
                SQL)
                ->findOrFail($template->id);
            abort_unless($locked->status === 'published', 404);
            $injects = collect(json_decode((string) $locked->getRawOriginal('active_inject_snapshot'), true, 512, JSON_THROW_ON_ERROR))
                ->map(fn (array $attributes): TtxScenarioInjectTemplate => (new TtxScenarioInjectTemplate)->forceFill($attributes));
            $this->assertPublishable($locked, $injects);

            $exercise = TtxExercise::create([
                'tenant_id' => $actor->tenant_id,
                'title' => $locked->title,
                'scenario' => $locked->scenario,
                'objectives' => $locked->objectives,
                'scope' => $locked->scope,
                'phase' => 'planning',
            ]);
            $exercise->source_scenario_template_id = $locked->id;
            $exercise->capability_codes = ExerciseCapabilityCatalog::normalize($locked->capability_codes);
            $exercise->save();

            foreach ($injects as $inject) {
                $copy = $exercise->injects()->create([
                    'tenant_id' => $actor->tenant_id,
                    'order' => $inject->order,
                    'title' => $inject->title,
                    'description' => $inject->description,
                ]);
                $copy->capability_codes = ExerciseCapabilityCatalog::normalize($inject->capability_codes);
                $copy->save();
            }

            Audit::log('ttx.scenario_template_instantiated', $exercise, [
                'scenario_template_id' => $locked->id,
                'exercise_id' => $exercise->id,
            ]);

            return $exercise;
        });
    }
}
