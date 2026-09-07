<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\CaseParticipation;
use App\Models\CaseStudy;
use App\Models\CaseScene;
use App\Models\CtfChallenge;
use App\Models\CtfSolve;
use App\Models\ModuleAssignment;
use App\Models\Package;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\TtxExercise;
use App\Models\TtxScore;
use App\Models\TtxTeam;
use App\Models\TtxTeamMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Seeder bertindak sebagai platform agar lolos RLS
        DB::statement("SELECT set_config('app.role', 'super_admin', false)");

        // ---------- TENANTS ----------
        $acme = Tenant::updateOrCreate(['slug' => 'acme'], ['name' => 'Acme Corporation', 'status' => 'active']);
        $beta = Tenant::updateOrCreate(['slug' => 'beta'], ['name' => 'Beta Mandiri', 'status' => 'active']);

        // ---------- SUPER ADMIN ----------
        User::firstOrCreate(
            ['email' => 'superadmin@platform.local'],
            ['name' => 'Super Admin', 'password' => 'password', 'role' => UserRole::SuperAdmin, 'tenant_id' => null, 'is_active' => true]
        );

        // ---------- USERS ----------
        $mkUser = function (Tenant $t, string $name, string $email, UserRole $role) {
            return User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => 'password', 'tenant_id' => $t->id, 'role' => $role, 'is_active' => true]
            );
        };

        $acmeAdmin = $mkUser($acme, 'Admin Acme', 'admin@acme.local', UserRole::TenantAdmin);
        $mkUser($beta, 'Admin Beta', 'admin@beta.local', UserRole::TenantAdmin);

        $acmeUsers = collect([
            ['Budi Santoso', 'budi@acme.local'],
            ['Sari Wijaya', 'sari@acme.local'],
            ['Andi Pratama', 'andi@acme.local'],
            ['Dewi Lestari', 'dewi@acme.local'],
            ['Rizky Hakim', 'rizky@acme.local'],
            ['Maya Putri', 'maya@acme.local'],
        ])->map(fn ($u) => $mkUser($acme, $u[0], $u[1], UserRole::User));

        $betaUser = $mkUser($beta, 'User Beta', 'user@beta.local', UserRole::User);

        // ---------- MODULES + QUIZZES ----------
        $mkModule = function (string $title, string $desc) {
            $m = TrainingModule::firstOrCreate(['title' => $title], [
                'description' => $desc,
                'content' => "Materi {$title}: pahami konsep, kenali tanda bahaya, terapkan praktik terbaik.",
                'duration_minutes' => 15,
                'is_active' => true,
            ]);

            $quiz = Quiz::firstOrCreate(['training_module_id' => $m->id], [
                'title' => 'Quiz: ' . $title,
                'passing_score' => 70,
                'is_active' => true,
            ]);

            if ($quiz->questions()->count() === 0) {
                QuizQuestion::create([
                    'quiz_id' => $quiz->id,
                    'question' => "Manakah tindakan paling tepat terkait {$title}?",
                    'options' => ['Verifikasi sebelum bertindak', 'Abaikan prosedur', 'Bagikan kredensial', 'Nonaktifkan keamanan'],
                    'correct_index' => 0,
                ]);
                QuizQuestion::create([
                    'quiz_id' => $quiz->id,
                    'question' => 'Saat menemukan kejanggalan, langkah pertama adalah...',
                    'options' => ['Melapor ke tim keamanan', 'Teruskan ke semua orang', 'Hapus bukti', 'Diam saja'],
                    'correct_index' => 0,
                ]);
            }

            return $m;
        };

        $modPhishing = $mkModule('Phishing Awareness', 'Mengenali email phishing.');
        $modPassword = $mkModule('Password Hygiene', 'Menjaga password tetap kuat.');
        $modSocial = $mkModule('Social Engineering', 'Menghadapi manipulasi sosial.');

        // ---------- CASE STUDY ----------
        $case = CaseStudy::firstOrCreate(['title' => 'Kasus: Email Mencurigakan'], [
            'description' => 'Anda menerima email yang meminta reset password mendesak.',
            'difficulty' => 'beginner',
            'duration_minutes' => 10,
            'is_active' => true,
        ]);

        if ($case->scenes()->count() === 0) {
            CaseScene::create([
                'case_study_id' => $case->id,
                'order' => 1,
                'situation' => 'Email masuk mengaku dari IT, meminta password untuk "verifikasi mendesak".',
                'options' => [
                    ['text' => 'Membalas dan memberikan password', 'quality' => 'poor', 'feedback' => 'Jangan pernah membagikan password.'],
                    ['text' => 'Meneruskan ke tim keamanan tanpa klik tautan', 'quality' => 'best', 'feedback' => 'Tepat! Eskalasi ke tim keamanan.'],
                    ['text' => 'Mengklik tautan untuk memastikan', 'quality' => 'poor', 'feedback' => 'Berisiko â€” tautan bisa berbahaya.'],
                ],
            ]);
            CaseScene::create([
                'case_study_id' => $case->id,
                'order' => 2,
                'situation' => 'Rekan mengaku butuh "bantuan mendesak" via chat dan meminta kode OTP.',
                'options' => [
                    ['text' => 'Memberikan OTP karena rekan sendiri', 'quality' => 'poor', 'feedback' => 'OTP jangan dibagikan ke siapa pun.'],
                    ['text' => 'Konfirmasi via kanal lain (telepon langsung)', 'quality' => 'best', 'feedback' => 'Benar â€” verifikasi via kanal terpisah.'],
                ],
            ]);
        }

        // ---------- CTF ----------
        $ctf1 = CtfChallenge::firstOrCreate(['title' => 'Email Misterius'], [
            'description' => 'Temukan flag di email phishing ini.', 'category' => 'phishing',
            'difficulty' => 'beginner', 'points' => 100, 'flag' => 'FLAG{jangan_klik_link}',
            'hint' => 'Periksa alamat pengirim.', 'is_active' => true,
        ]);
        $ctf2 = CtfChallenge::firstOrCreate(['title' => 'Password Lemah'], [
            'description' => 'Flag dari kebiasaan password yang buruk.', 'category' => 'password',
            'difficulty' => 'beginner', 'points' => 100, 'flag' => 'FLAG{password_kuat}',
            'hint' => 'Gunakan passphrase.', 'is_active' => true,
        ]);
        $ctf3 = CtfChallenge::firstOrCreate(['title' => 'Web Jebakan'], [
            'description' => 'Flag tersembunyi di halaman login palsu.', 'category' => 'web',
            'difficulty' => 'intermediate', 'points' => 200, 'flag' => 'FLAG{cek_url}',
            'hint' => 'Periksa domain.', 'is_active' => true,
        ]);

        // ---------- SUBSCRIPTIONS ----------
        $pro = Package::where('slug', 'pro')->first();
        $starter = Package::where('slug', 'starter')->first();

        if ($pro && ! Subscription::where('tenant_id', $acme->id)->where('status', 'active')->exists()) {
            Subscription::create(['tenant_id' => $acme->id, 'package_id' => $pro->id, 'status' => 'active', 'started_at' => now()->subMonths(2)]);
        }
        if ($starter && ! Subscription::where('tenant_id', $beta->id)->where('status', 'active')->exists()) {
            Subscription::create(['tenant_id' => $beta->id, 'package_id' => $starter->id, 'status' => 'active', 'started_at' => now()->subMonth()]);
        }

        // ---------- ACTIVITY PER USER ----------
        $profiles = [
            ['user' => $acmeUsers[0], 'quiz' => [90, 85, 100], 'case' => 100, 'ctf' => 3, 'ttx' => 90],
            ['user' => $acmeUsers[1], 'quiz' => [80, 75, 0], 'case' => 80, 'ctf' => 2, 'ttx' => 80],
            ['user' => $acmeUsers[2], 'quiz' => [50, 40, 0], 'case' => null, 'ctf' => 0, 'ttx' => null],
            ['user' => $acmeUsers[3], 'quiz' => [70, 80, 60], 'case' => 60, 'ctf' => 1, 'ttx' => 70],
            ['user' => $acmeUsers[4], 'quiz' => [null, null, null], 'case' => null, 'ctf' => 0, 'ttx' => null],
            ['user' => $acmeUsers[5], 'quiz' => [100, 90, 80], 'case' => 100, 'ctf' => 2, 'ttx' => 85],
        ];

        $modules = collect([$modPhishing, $modPassword, $modSocial]);
        $scenes = $case->scenes()->orderBy('order')->get();

        foreach ($profiles as $p) {
            $u = $p['user'];

            foreach ($modules as $i => $m) {
                $quizScore = $p['quiz'][$i];

                ModuleAssignment::firstOrCreate(
                    ['user_id' => $u->id, 'training_module_id' => $m->id],
                    [
                        'tenant_id' => $u->tenant_id,
                        'status' => $quizScore === null ? 'assigned' : 'completed',
                        'score' => $quizScore,
                        'completed_at' => $quizScore !== null ? now()->subDays(rand(1, 20)) : null,
                    ]
                );

                if ($quizScore !== null) {
                    $quiz = Quiz::where('training_module_id', $m->id)->first();
                    QuizAttempt::firstOrCreate(
                        ['quiz_id' => $quiz->id, 'user_id' => $u->id],
                        [
                            'tenant_id' => $u->tenant_id,
                            'score' => $quizScore,
                            'passed' => $quizScore >= ($quiz->passing_score ?? 70),
                            'answers' => [],
                        ]
                    );
                }
            }

            if ($p['case'] !== null) {
                CaseParticipation::firstOrCreate(
                    ['user_id' => $u->id, 'case_study_id' => $case->id],
                    [
                        'tenant_id' => $u->tenant_id,
                        'status' => 'completed',
                        'score' => $p['case'],
                        'decisions' => $scenes->mapWithKeys(fn ($s) => [$s->id => 1])->all(),
                        'completed_at' => now()->subDays(rand(1, 15)),
                    ]
                );
            }

            foreach (collect([$ctf1, $ctf2, $ctf3])->take($p['ctf']) as $c) {
                CtfSolve::firstOrCreate(
                    ['user_id' => $u->id, 'challenge_id' => $c->id],
                    ['tenant_id' => $u->tenant_id, 'points' => $c->points, 'solved_at' => now()->subDays(rand(1, 10))]
                );
            }
        }

        // ---------- TTX + SCORES ----------
        $exercise = TtxExercise::firstOrCreate(
            ['tenant_id' => $acme->id, 'title' => 'TTX: Ransomware Rumah Sakit (Demo)'],
            [
                'scenario' => 'Serangan ransomware mengunci server EMR rumah sakit.',
                'objectives' => 'Uji koordinasi & eskalasi antar tim.',
                'scope' => 'Ransomware / Kesehatan',
                'phase' => 'completed',
                'scheduled_at' => now()->subDays(7),
                'aar_notes' => 'Koordinasi baik; eskalasi ke manajemen perlu dipercepat.',
                'corrective_actions' => ['Perbarui SOP eskalasi', 'Latihan komunikasi eksternal rutin'],
            ]
        );

        $teamDefs = ['Tim Incident Response', 'Tim Recovery', 'Tim Komunikasi', 'Tim Top Management'];
        $memberPool = $acmeUsers->merge(collect([$acmeAdmin]));

        foreach ($teamDefs as $ti => $name) {
            $team = TtxTeam::firstOrCreate(
                ['tenant_id' => $acme->id, 'exercise_id' => $exercise->id, 'name' => $name],
                ['description' => $name]
            );

            TtxTeamMember::firstOrCreate(
                ['team_id' => $team->id, 'user_id' => $memberPool[$ti % $memberPool->count()]->id],
                ['tenant_id' => $acme->id, 'role_in_team' => 'lead']
            );
        }

        foreach ($profiles as $p) {
            if ($p['ttx'] !== null) {
                TtxScore::firstOrCreate(
                    ['user_id' => $p['user']->id, 'exercise_id' => $exercise->id],
                    ['tenant_id' => $acme->id, 'score' => $p['ttx']]
                );
            }
        }

        // ---------- BETA: aktivitas minimal (kontras di platform report) ----------
        ModuleAssignment::firstOrCreate(
            ['user_id' => $betaUser->id, 'training_module_id' => $modPhishing->id],
            ['tenant_id' => $beta->id, 'status' => 'assigned']
        );
    }
}