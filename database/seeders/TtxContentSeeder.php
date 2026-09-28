<?php

namespace Database\Seeders;

use App\Domain\Tabletop\ExerciseCapabilityCatalog;
use App\Domain\Tabletop\PlaybookPhaseStructure;
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
    public const FLAGSHIP_TITLE = 'Credential Compromise & Coordinated Incident Response';

    public const FLAGSHIP_PLAYBOOK_TITLE = 'Credential Compromise Response Playbook';

    /**
     * V1 provisions the platform-authored flagship into these demo tenants as
     * tenant-owned copies. This preserves RLS and keeps private scenarios local.
     *
     * @var list<string>
     */
    public const FLAGSHIP_TENANT_SLUGS = ['acme', 'pt-demo'];

    /**
     * Evaluation evidence expected from each inject. This stays in content code
     * because the inject schema intentionally has no objective metadata field.
     *
     * @var array<int, list<string>>
     */
    public const FLAGSHIP_OBJECTIVE_MAP = [
        1 => ['EX-1'],
        2 => ['EX-1', 'EX-2', 'EX-3'],
        3 => ['EX-3', 'EX-4'],
        4 => ['EX-4', 'EX-5'],
        5 => ['EX-6'],
    ];

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

        $this->call(TtxFlagshipScenarioSeeder::class);

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

    public function provisionFlagshipScenario(Tenant $tenant): void
    {
        $playbook = TtxPlaybook::updateOrCreate(
            ['tenant_id' => $tenant->id, 'title' => self::FLAGSHIP_PLAYBOOK_TITLE],
            [
                'description' => 'Panduan respons organisasi untuk menangani kompromi kredensial secara terkoordinasi tanpa menggantikan prosedur teknis rinci.',
                'content' => implode("\n\n", [
                    "1. Detection & Validation\nValidasi sinyal identitas, konfirmasi konteks pengguna, dan pertahankan bukti awal.",
                    "2. Incident Classification\nNilai tingkat keparahan berdasarkan kepastian kompromi, cakupan akses, dan dampak bisnis.",
                    "3. Escalation & Ownership\nTetapkan incident owner, fungsi pendukung, jalur keputusan, dan ritme pembaruan.",
                    "4. Account Containment\nBatasi penyalahgunaan akun dengan tindakan yang proporsional terhadap risiko dan kebutuhan operasi.",
                    "5. Session Revocation\nCabut sesi yang berisiko dan verifikasi bahwa akses tidak sah telah dihentikan.",
                    "6. Impact Investigation\nTentukan aktivitas, data, pengguna, proses bisnis, dan pihak ketiga yang mungkin terdampak.",
                    "7. Internal / External Communication\nKoordinasikan pesan yang akurat, disetujui, tepat waktu, dan diperbarui saat fakta berubah.",
                    "8. Recovery\nPulihkan akses serta proses bisnis secara terkendali dengan pemantauan tambahan.",
                    "9. Post-Incident Review\nDokumentasikan keputusan, pelajaran, pemilik tindakan, dan perbaikan Playbook.",
                ]),
                'is_active' => true,
            ]
        );
        $playbook->forceFill([
            'structured_phases' => PlaybookPhaseStructure::normalize($this->flagshipPlaybookPhases()),
        ])->save();

        $exercise = TtxExercise::updateOrCreate(
            ['tenant_id' => $tenant->id, 'title' => self::FLAGSHIP_TITLE],
            [
                'scenario' => 'Arunika Services, sebuah organisasi layanan bisnis fiktif, menerima laporan aktivitas tidak wajar pada akun seorang manajer keuangan. Tim harus memvalidasi indikasi awal, menjaga operasi tetap berjalan, dan mengoordinasikan respons dengan informasi yang belum lengkap.',
                'objectives' => "EX-1 Detection & Triage\nEX-2 Escalation & Ownership\nEX-3 Containment Decision\nEX-4 Cross-functional Coordination\nEX-5 Incident Communication\nEX-6 Recovery & Improvement",
                'scope' => 'Beginner–Intermediate · 45–60 menit · 4–8 peserta · 1 fasilitator',
                'playbook_id' => $playbook->id,
                'runbook_id' => null,
                'phase' => 'planning',
                'scheduled_at' => null,
            ]
        );
        $exercise->forceFill([
            'capability_codes' => ExerciseCapabilityCatalog::normalize(ExerciseCapabilityCatalog::codes()),
        ])->save();

        $injects = [
            [
                'order' => 1,
                'title' => 'Suspicious Account Activity',
                'description' => "Situasi:\nPukul 09.10, Service Desk menerima laporan dari manajer keuangan Arunika Services tentang beberapa permintaan persetujuan MFA yang tidak ia lakukan. Pemantauan identitas juga menandai satu login berhasil dari lokasi yang tidak biasa.\n\nFakta yang diketahui:\n- Pemilik akun dapat dihubungi dan sedang bekerja dari lokasi normal.\n- Belum ada konfirmasi bahwa kredensial atau sesi telah disalahgunakan.\n- Layanan bisnis utama masih berjalan normal.\n- Log identitas dan perangkat tersedia untuk ditinjau.\n\nPertanyaan diskusi:\nTindakan apa yang harus dilakukan tim dalam 30 menit berikutnya, siapa yang memiliki setiap tindakan, dan informasi apa yang masih diperlukan untuk menentukan tingkat keparahan insiden?",
            ],
            [
                'order' => 2,
                'title' => 'Confirmed Credential Compromise',
                'description' => "Situasi:\nPeninjauan awal mengonfirmasi bahwa pengguna memasukkan kredensial pada halaman masuk palsu sehari sebelumnya. Sebuah sesi yang tidak dikenal membuat aturan penerusan email dan membuka beberapa dokumen kerja bersama.\n\nFakta yang diketahui:\n- Aktivitas tersebut menggunakan identitas manajer keuangan yang sah.\n- Aturan penerusan mengarah ke alamat eksternal yang tidak dikenal.\n- Belum diketahui seluruh dokumen yang dilihat atau disalin.\n- Penutupan akun akan memengaruhi persetujuan transaksi rutin pagi ini.\n\nPertanyaan diskusi:\nSiapa yang mengambil kepemilikan insiden, kepada siapa insiden harus dieskalasikan, dan tindakan containment apa yang harus disetujui sekarang sambil menjaga proses bisnis kritis tetap terkendali?",
            ],
            [
                'order' => 3,
                'title' => 'Lateral Business Impact',
                'description' => "Situasi:\nService Desk menemukan bahwa akun yang dikompromikan telah mengirim permintaan reset akses kepada dua rekan kerja dan mengajukan perubahan rekening pembayaran pemasok. Salah satu rekan mengikuti tautan tersebut sebelum melapor.\n\nFakta yang diketahui:\n- Permintaan perubahan rekening masih menunggu persetujuan kedua.\n- Sesi aktif pengguna terkait masih ada pada laptop dan perangkat seluler terkelola.\n- Finance sedang menyelesaikan proses pembayaran harian dengan tenggat siang ini.\n- Security, Operasional TI, Finance, dan People/HR memiliki bagian informasi yang berbeda.\n\nPertanyaan diskusi:\nBagaimana tim menentukan cakupan containment akun dan sesi, mengoordinasikan fungsi yang terdampak, serta menyeimbangkan penghentian risiko dengan kelangsungan operasi? Tetapkan pemilik dan urutan tindakan segera.",
            ],
            [
                'order' => 4,
                'title' => 'External and Internal Communication Pressure',
                'description' => "Situasi:\nSeorang pemasok mempertanyakan permintaan perubahan rekening, rumor mulai beredar di kanal percakapan internal, dan seorang klien utama meminta konfirmasi dalam satu jam setelah menerima pesan mencurigakan dari akun Arunika Services.\n\nFakta yang diketahui:\n- Investigasi masih berlangsung dan belum ada bukti terverifikasi mengenai pengambilan data.\n- Permintaan pembayaran palsu berhasil dihentikan sebelum diproses.\n- Belum ada pernyataan internal atau eksternal yang disetujui.\n- Tim hukum dan privasi sedang menilai kewajiban pemberitahuan.\n\nPertanyaan diskusi:\nSiapa yang harus diberi tahu sekarang, pesan sementara apa yang dapat disampaikan dengan jujur, siapa yang menyetujui dan menyampaikan pesan, serta bagaimana tim akan memperbarui pemangku kepentingan ketika fakta berubah?",
            ],
            [
                'order' => 5,
                'title' => 'Recovery and Lessons',
                'description' => "Situasi:\nAkun dan sesi yang terdampak telah diamankan. Tidak ada aktivitas mencurigakan baru selama enam jam, tetapi proses Finance masih memakai prosedur manual dan beberapa pengguna menunggu pemulihan akses. Pimpinan meminta rencana kembali ke operasi normal.\n\nFakta yang diketahui:\n- Bukti dan log utama telah dipertahankan untuk peninjauan lanjutan.\n- Pemeriksaan awal mengarah pada phishing dan verifikasi perubahan pembayaran yang tidak konsisten.\n- Pemantauan tambahan dapat diterapkan pada akun dan transaksi berisiko tinggi.\n- Belum ada pemilik atau tenggat untuk tindakan perbaikan jangka panjang.\n\nPertanyaan diskusi:\nKriteria apa yang harus dipenuhi sebelum akses dan proses normal dipulihkan, pemantauan apa yang diperlukan, dan pelajaran serta tindakan korektif apa yang harus dimiliki tim untuk 24 jam dan 30 hari berikutnya?",
            ],
        ];

        foreach ($injects as $inject) {
            $model = TtxInject::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'exercise_id' => $exercise->id,
                    'order' => $inject['order'],
                ],
                [
                    'title' => $inject['title'],
                    'description' => $inject['description'],
                ]
            );
            $model->forceFill([
                'capability_codes' => ExerciseCapabilityCatalog::normalize(self::FLAGSHIP_OBJECTIVE_MAP[$inject['order']]),
            ])->save();
        }
    }

    /**
     * @return list<array{key: string, title: string, guidance: string, participant_summary: string, capability_codes: list<string>}>
     */
    private function flagshipPlaybookPhases(): array
    {
        return [
            [
                'key' => 'detection_validation',
                'title' => 'Detection & Validation',
                'guidance' => 'Validasi sinyal identitas dari sumber tepercaya, konfirmasi konteks pengguna, dan pertahankan bukti awal sebelum menarik kesimpulan.',
                'participant_summary' => 'Validasi indikasi dengan fakta yang tersedia dan catat informasi yang masih perlu dikonfirmasi.',
                'capability_codes' => ['EX-1'],
            ],
            [
                'key' => 'incident_classification',
                'title' => 'Incident Classification',
                'guidance' => 'Klasifikasikan tingkat insiden berdasarkan kepastian kompromi, cakupan akses, sensitivitas aktivitas, dan dampak bisnis.',
                'participant_summary' => 'Tentukan tingkat keparahan berdasarkan bukti, cakupan, dan dampak organisasi.',
                'capability_codes' => ['EX-1'],
            ],
            [
                'key' => 'escalation_ownership',
                'title' => 'Escalation & Ownership',
                'guidance' => 'Tetapkan incident owner, fungsi pendukung, jalur keputusan, dan ritme pembaruan yang sesuai dengan klasifikasi insiden.',
                'participant_summary' => 'Pastikan insiden memiliki pemilik, jalur eskalasi, dan otoritas keputusan yang jelas.',
                'capability_codes' => ['EX-2'],
            ],
            [
                'key' => 'account_containment',
                'title' => 'Account Containment',
                'guidance' => 'Batasi kemampuan akun untuk disalahgunakan dengan mempertimbangkan tingkat risiko, bukti, dan kebutuhan proses bisnis kritis.',
                'participant_summary' => 'Pilih containment akun yang mengurangi risiko sambil mengelola dampak operasi.',
                'capability_codes' => ['EX-3'],
            ],
            [
                'key' => 'session_revocation',
                'title' => 'Session Revocation',
                'guidance' => 'Cabut sesi yang berisiko, verifikasi efektivitas tindakan, dan pantau upaya akses ulang.',
                'participant_summary' => 'Hentikan sesi berisiko dan pastikan akses tidak sah benar-benar berakhir.',
                'capability_codes' => ['EX-3'],
            ],
            [
                'key' => 'impact_investigation',
                'title' => 'Impact Investigation',
                'guidance' => 'Gabungkan bukti lintas fungsi untuk menentukan aktivitas, data, pengguna, proses bisnis, dan pihak ketiga yang mungkin terdampak.',
                'participant_summary' => 'Bangun gambaran dampak bersama dari bukti dan informasi lintas tim.',
                'capability_codes' => ['EX-1', 'EX-4'],
            ],
            [
                'key' => 'incident_communication',
                'title' => 'Internal / External Communication',
                'guidance' => 'Koordinasikan pesan yang akurat, disetujui, tepat waktu, dan dapat diperbarui ketika fakta atau kewajiban berubah.',
                'participant_summary' => 'Sampaikan informasi yang terverifikasi melalui pemilik dan jalur persetujuan yang tepat.',
                'capability_codes' => ['EX-5'],
            ],
            [
                'key' => 'recovery',
                'title' => 'Recovery',
                'guidance' => 'Tetapkan kriteria pemulihan, pulihkan akses dan proses secara terkendali, lalu terapkan pemantauan tambahan.',
                'participant_summary' => 'Pulihkan layanan dengan kriteria yang jelas dan pemantauan risiko lanjutan.',
                'capability_codes' => ['EX-6'],
            ],
            [
                'key' => 'post_incident_review',
                'title' => 'Post-Incident Review',
                'guidance' => 'Ubah evidence dan finding menjadi tindakan korektif atau perbaikan Playbook dengan pemilik serta tenggat yang jelas.',
                'participant_summary' => 'Dokumentasikan pelajaran dan tetapkan perbaikan yang dapat ditindaklanjuti.',
                'capability_codes' => ['EX-6'],
            ],
        ];
    }
}
