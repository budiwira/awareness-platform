<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxInject;
use App\Models\TtxPlaybook;
use App\Models\TtxRunbook;
use App\Models\TtxTeam;
use App\Models\TtxTeamMember;
use App\Models\User;
use Illuminate\Database\Seeder;

class TtxContentSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'acme')->first() ?? Tenant::first();

        if (! $tenant) {
            return;
        }

        $users = User::where('tenant_id', $tenant->id)->get();

        // --- PLAYBOOKS (diadaptasi dari struktur CISA CTEP / NIST 800-84) ---
        $pbRansomware = TtxPlaybook::updateOrCreate(
            ['tenant_id' => $tenant->id, 'title' => 'Playbook Respons Insiden Ransomware'],
            [
                'description' => 'Prosedur terstruktur menghadapi serangan ransomware: deteksi, isolasi, eradikasi, recovery, dan komunikasi.',
                'content' => "1. Deteksi & validasi laporan\n2. Isolasi host/segment terdampak\n3. Aktifkan tim IR & eskalasi\n4. Eradikasi & rebuild\n5. Restore bertahap dari backup offline\n6. Komunikasi internal & eksternal\n7. Post-incident review & AAR",
                'is_active' => true,
            ]
        );

        $pbLeak = TtxPlaybook::updateOrCreate(
            ['tenant_id' => $tenant->id, 'title' => 'Playbook Penanganan Kebocoran Data'],
            [
                'description' => 'Panduan menangani kebocoran data pribadi: identifikasi scope, notifikasi regulator, dan komunikasi publik.',
                'content' => "1. Identifikasi sumber & scope kebocoran\n2. Amankan akses yang bocor\n3. Assess dampak & klasifikasi data\n4. Notifikasi regulator (UU PDP)\n5. Notifikasi affected individuals\n6. Monitoring dark web / misuse\n7. Review & perbaikan kontrol",
                'is_active' => true,
            ]
        );

        // --- RUNBOOKS (langkah teknis) ---
        $rbRansomware = TtxRunbook::updateOrCreate(
            ['tenant_id' => $tenant->id, 'title' => 'Runbook Isolasi & Eradikasi Ransomware'],
            [
                'description' => 'Langkah teknis isolasi dan eradikasi ransomware.',
                'steps' => [
                    'Identifikasi & isolasi host terdampak',
                    'Putuskan koneksi jaringan segment terdampak',
                    'Nonaktifkan akun kompromi & rotasi kredensial',
                    'Aktifkan backup offline',
                    'Eradikasi malware & rebuild sistem',
                    'Restore bertahap & validasi integritas',
                ],
                'is_active' => true,
            ]
        );

        $rbLeak = TtxRunbook::updateOrCreate(
            ['tenant_id' => $tenant->id, 'title' => 'Runbook Notifikasi & Komunikasi Kebocoran Data'],
            [
                'description' => 'Langkah notifikasi regulator dan komunikasi publik.',
                'steps' => [
                    'Tentukan juru bicara tunggal',
                    'Siapkan pernyataan resmi',
                    'Notifikasi regulator sesuai UU PDP',
                    'Notifikasi individu terdampak',
                    'Sediakan call center / FAQ',
                    'Monitoring pemberitaan & respons',
                ],
                'is_active' => true,
            ]
        );

        // --- EXERCISE (TTX) ---
        $exercise = TtxExercise::updateOrCreate(
            ['tenant_id' => $tenant->id, 'title' => 'TTX: Serangan Ransomware pada Rumah Sakit'],
            [
                'scenario' => 'Senin pagi, staf IT rumah sakit melaporkan file di server rekam medis (EMR) terkunci dan muncul catatan tebusan. Layanan pendaftaran pasien mulai terganggu. Dalam beberapa jam, terindikasi penyebaran lateral dan ancaman publikasi data pasien.',
                'objectives' => 'Menguji koordinasi antar tim (IR, Recovery, Komunikasi, Top Management), eskalasi, penerapan IRP, dan pengambilan keputusan di bawah tekanan — tanpa menyentuh sistem produksi.',
                'scope' => 'Ransomware / Sektor Kesehatan',
                'playbook_id' => $pbRansomware->id,
                'runbook_id' => $rbRansomware->id,
                'phase' => 'planning',
                'scheduled_at' => now()->addDays(7),
            ]
        );

        // --- INJECTS (komplikasi bertahap, pola CISA) ---
        $injects = [
            ['order' => 1, 'title' => '08:00 — Laporan Awal', 'description' => 'Staf melaporkan file EMR terkunci dan ransom note. Apa langkah pertama tim IR?'],
            ['order' => 2, 'title' => '09:30 — Eskalasi', 'description' => 'Terdeteksi penyebaran lateral ke server backup. Bagaimana strategi isolasi & perlindungan backup?'],
            ['order' => 3, 'title' => '11:00 — Tekanan Eksternal', 'description' => 'Seorang jurnalis menanyakan insiden; pelaku mengancam publikasi data pasien. Siapa juru bicara dan apa pesannya?'],
            ['order' => 4, 'title' => '13:00 — Keputusan Recovery', 'description' => 'Backup offline tersedia namun restore butuh 48 jam; layanan kritis down. Restore, negosiasi, atau keduanya? Justifikasi.'],
        ];

        foreach ($injects as $inj) {
            TtxInject::updateOrCreate(
                ['tenant_id' => $tenant->id, 'exercise_id' => $exercise->id, 'order' => $inj['order']],
                ['title' => $inj['title'], 'description' => $inj['description']]
            );
        }

        // --- TEAMS (pembagian peran, contoh mentor) ---
        $teamNames = [
            ['name' => 'Tim Incident Response', 'desc' => 'Deteksi, analisis, isolasi, eradikasi.'],
            ['name' => 'Tim Recovery', 'desc' => 'Restore backup & pemulihan layanan.'],
            ['name' => 'Tim Komunikasi', 'desc' => 'Humas, juru bicara, notifikasi eksternal.'],
            ['name' => 'Tim Top Management', 'desc' => 'Pengambilan keputusan strategis & persetujuan.'],
        ];

        $userIndex = 0;

        foreach ($teamNames as $t) {
            $team = TtxTeam::updateOrCreate(
                ['tenant_id' => $tenant->id, 'exercise_id' => $exercise->id, 'name' => $t['name']],
                ['description' => $t['desc']]
            );

            // Isi anggota dari user tenant (round-robin), anggota pertama jadi lead
            if ($users->isNotEmpty()) {
                $member = $users[$userIndex % $users->count()];
                $userIndex++;

                TtxTeamMember::updateOrCreate(
                    ['team_id' => $team->id, 'user_id' => $member->id],
                    ['tenant_id' => $tenant->id, 'role_in_team' => 'lead']
                );
            }
        }
    }
}