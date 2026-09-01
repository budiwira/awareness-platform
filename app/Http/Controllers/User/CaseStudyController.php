<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CaseParticipation;
use App\Models\CaseStudy;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CaseStudyController extends Controller
{
    private const QUALITY_POINTS = ['best' => 100, 'acceptable' => 60, 'poor' => 0];

    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;
        $entitlement = app(\App\Services\TenantEntitlement::class);

        if (!$tenant || !$entitlement->hasFeature($tenant, 'case_studies')) {
            return Inertia::render('Shared/FeatureLocked', [
                'title' => 'Fitur Studi Kasus Terkunci',
                'message' => 'Organisasi Anda belum mengaktifkan fitur Studi Kasus.',
                'cta' => 'Hubungi admin organisasi untuk upgrade',
            ])->toResponse(request())->setStatusCode(403);
        }

        $participations = CaseParticipation::where('user_id', $request->user()->id)
            ->get()
            ->keyBy('case_study_id');

        $cases = CaseStudy::where('is_active', true)
            ->where('status', 'published')
            ->withCount('scenes')
            ->orderBy('title')
            ->get()
            ->map(function ($c) use ($participations) {
                $p = $participations->get($c->id);

                return [
                    'id' => $c->id,
                    'title' => $c->title,
                    'description' => $c->description,
                    'difficulty' => $c->difficulty,
                    'duration_minutes' => $c->duration_minutes,
                    'scenes_count' => $c->scenes_count,
                    'participation' => $p ? [
                        'id' => $p->id,
                        'status' => $p->status,
                        'score' => $p->score,
                    ] : null,
                ];
            });

        return Inertia::render('User/Cases/Index', ['cases' => $cases]);
    }

    public function start(Request $request, CaseStudy $caseStudy)
    {
        $tenant = $request->user()->tenant;
        $entitlement = app(\App\Services\TenantEntitlement::class);
        if (!$tenant || !$entitlement->hasFeature($tenant, 'case_studies')) {
            abort(403, 'Organisasi Anda belum mengaktifkan fitur Studi Kasus.');
        }

        if (! $caseStudy->is_active || $caseStudy->status !== 'published') {
            return redirect()->route('user.cases.index');
        }

        $participation = CaseParticipation::firstOrCreate(
            ['user_id' => $request->user()->id, 'case_study_id' => $caseStudy->id],
            ['tenant_id' => $request->user()->tenant_id, 'status' => 'in_progress']
        );

        return redirect()->route('user.cases.run', $participation);
    }

    public function run(Request $request, CaseParticipation $participation)
    {
        $tenant = $request->user()->tenant;
        $entitlement = app(\App\Services\TenantEntitlement::class);
        if (!$tenant || !$entitlement->hasFeature($tenant, 'case_studies')) {
            abort(403, 'Organisasi Anda belum mengaktifkan fitur Studi Kasus.');
        }

        $this->ensureOwner($request, $participation);

        if ($participation->status === 'completed') {
            return redirect()->route('user.cases.result', $participation);
        }

        $participation->load('caseStudy.scenes');

        // KEAMANAN: hanya situation + teks opsi yang dikirim ke browser.
        // quality & feedback TIDAK pernah bocor sebelum submit.
        $scenes = $participation->caseStudy->scenes->map(fn ($s) => [
            'id' => $s->id,
            'order' => $s->order,
            'situation' => $s->situation,
            'options' => collect($s->options)->map(fn ($o) => ['text' => $o['text']])->values(),
        ]);

        return Inertia::render('User/Cases/Run', [
            'participation_id' => $participation->id,
            'case_title' => $participation->caseStudy->title,
            'scenes' => $scenes,
        ]);
    }

    public function submit(Request $request, CaseParticipation $participation)
    {
        $tenant = $request->user()->tenant;
        $entitlement = app(\App\Services\TenantEntitlement::class);
        if (!$tenant || !$entitlement->hasFeature($tenant, 'case_studies')) {
            abort(403, 'Organisasi Anda belum mengaktifkan fitur Studi Kasus.');
        }

        $this->ensureOwner($request, $participation);

        if ($participation->status === 'completed') {
            return redirect()->route('user.cases.result', $participation);
        }

        $validated = $request->validate([
            'answers' => ['required', 'array'],
        ]);

        $caseStudy = $participation->caseStudy;
        $scenes = $caseStudy->scenes;

        foreach ($scenes as $scene) {
            $given = $validated['answers'][$scene->id] ?? null;

            if ($given === null) {
                return redirect()->back()->withErrors(['answers' => 'Semua scene harus dijawab.']);
            }

            $given = (int) $given;

            if ($given < 0 || $given >= count($scene->options)) {
                return redirect()->back()->withErrors(['answers' => 'Terdapat pilihan yang tidak valid.']);
            }
        }

        // SCORING SERVER-SIDE
        $decisions = [];
        $total = 0;

        foreach ($scenes as $scene) {
            $idx = (int) $validated['answers'][$scene->id];
            $decisions[$scene->id] = $idx;
            $total += self::QUALITY_POINTS[$scene->options[$idx]['quality']];
        }

        $score = (int) round($total / $scenes->count());

        $participation->update([
            'decisions' => $decisions,
            'score' => $score,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        Audit::log('case.completed', $participation, ['score' => $score]);

        return redirect()->route('user.cases.result', $participation);
    }

    public function result(Request $request, CaseParticipation $participation)
    {
        $tenant = $request->user()->tenant;
        $entitlement = app(\App\Services\TenantEntitlement::class);
        if (!$tenant || !$entitlement->hasFeature($tenant, 'case_studies')) {
            abort(403, 'Organisasi Anda belum mengaktifkan fitur Studi Kasus.');
        }

        $this->ensureOwner($request, $participation);

        $participation->load('caseStudy.scenes');

        // Setelah selesai, feedback boleh ditampilkan (user sudah berkomitmen)
        $breakdown = $participation->caseStudy->scenes->map(function ($scene) use ($participation) {
            $options = collect($scene->options)->values();
            $chosen = $participation->decisions[$scene->id] ?? null;
            $bestIndex = $options->search(fn ($o) => $o['quality'] === 'best');

            return [
                'situation' => $scene->situation,
                'chosen' => $chosen !== null ? [
                    'text' => $options[$chosen]['text'],
                    'quality' => $options[$chosen]['quality'],
                    'feedback' => $options[$chosen]['feedback'],
                ] : null,
                'best' => $bestIndex !== false ? $options[$bestIndex]['text'] : null,
            ];
        });

        return Inertia::render('User/Cases/Result', [
            'case_title' => $participation->caseStudy->title,
            'score' => $participation->score,
            'breakdown' => $breakdown,
        ]);
    }

    private function ensureOwner(Request $request, CaseParticipation $participation): void
    {
        if ($participation->user_id !== $request->user()->id) {
            abort(403, 'Anda tidak berhak mengakses partisipasi ini.');
        }
    }
}