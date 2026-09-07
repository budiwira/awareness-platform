# Bug Log � V3.0.3 Stabilization

Format: ID | Role | Halaman | Steps | Expected | Actual | Severity | Kategori

Severity: CRITICAL (security bypass/kebocoran data) | HIGH (flow inti rusak) | MEDIUM (flow sekunder rusak) | LOW (cosmetic/UX)
Kategori: SECURITY | FUNCTIONAL | DATA | UX

## Super Admin
B01 | superadmin | navbar tenant | lihat package tenant | nama package | "Belum ada paket" | MEDIUM | DATA
B02 | superadmin | tenants | set package + simpan | package berubah | tidak berubah | HIGH | FUNCTIONAL
B03 | superadmin | dashboard vs reports | buka keduanya | tampilan beda | sama | LOW | UX
B04 | superadmin | packages | edit modul kurasi starter/custom + simpan | cardbox update | tidak update (msg sukses muncul) | HIGH | FUNCTIONAL
B05 | superadmin | users | buka filter | dropdown tenant | hanya "Semua Tenant" | MEDIUM | UX
B06 | superadmin | users | lihat list | tanpa super admin | super admin muncul | MEDIUM | DATA
B07 | superadmin | users | bandingkan dgn dashboard | 3 tenant | 1 tenant | HIGH | DATA
B08 | superadmin | users | cari tombol tambah | ada | tidak ada | MEDIUM | UX (feature)
B09 | superadmin | studio konten | archive + hapus | bekerja | tidak bekerja | HIGH | FUNCTIONAL
B10 | superadmin | quizzes | hapus quiz | ada tombol | tidak ada | MEDIUM | UX (feature)
B11 | superadmin | case studies | aksi + hapus | bekerja | tidak bekerja | HIGH | FUNCTIONAL
B12 | superadmin | ctf | aksi | bekerja | tidak bekerja | HIGH | FUNCTIONAL

## Tenant Admin
# Tenant Admin Findings

## B13 — Mojibake icon panah di card dashboard tenant
Role: tenant_admin | URL: /tenant/dashboard | Severity: LOW | Kategori: UI
Actual: Card menampilkan "â†'" вместо icon panah.

## B14 — Mojibake + package hilang di riwayat billing
Role: tenant_admin | URL: /tenant/billing | Severity: MEDIUM | Kategori: DATA+UI
Actual: Muncul "Â·", "â€"", "â€¢"; kolom PACKAGE di riwayat permintaan kosong.

## B15 — Create user tanpa field password
Role: tenant_admin | URL: /tenant/users | Severity: MEDIUM | Kategori: FEATURE
Decision: Opsi A — form create user lengkap dengan password + konfirmasi.

## B16 — Kelola Akses tenant: simpan → halaman putih Inertia error
Role: tenant_admin | URL: /tenant/users/{id}/access | Severity: HIGH | Kategori: FUNCTIONAL
Actual: "All Inertia requests must receive a valid Inertia response..."
Catatan: kemungkinan root cause sama dengan bug simpan super admin.

## B17 — Tombol aksi terlalu nempel
Role: tenant_admin | URL: /tenant/users | Severity: LOW | Kategori: UI

## B18 — Admin bisa menandai selesai assignment yang belum dikerjakan user
Role: tenant_admin | URL: /tenant/assignments | Severity: MEDIUM | Kategori: FUNCTIONAL
Expected: status completed hanya dari aksi user sendiri; admin hanya melihat/memvalidasi.

## B19 — Select package tidak terlihat di dark mode
Role: tenant_admin | URL: /tenant/billing | Severity: MEDIUM | Kategori: UI

## B20 — Halaman TTX kosong tanpa empty state/CTA
Role: tenant_admin | URL: /tenant/ttx, /tenant/ttx/exercises | Severity: MEDIUM | Kategori: UX
Catatan: terkait DD-01 (template playbook dari platform).

## B21 — Katalog Cases/CTF tidak terlihat oleh tenant admin
Role: tenant_admin | Severity: LOW | Kategori: FEATURE (DD-01)

## User
# User Findings (budi@demo.io)

## B22 — Submit case study crash 500 (Undefined array key 100)
Role: user | URL: POST /me/cases/run/6 | Severity: HIGH | Kategori: FUNCTIONAL
Steps: buka case → jawab scene → submit
Actual: ErrorException Undefined array key 100 di CaseStudyController.php:177
DevTools: POST 500
Catatan AppSec: halaman error menampilkan versi Laravel/PHP + path (hardening: APP_DEBUG=false di prod)

## B23 — User baru memiliki score non-zero di leaderboard
Role: user | URL: /me/leaderboard | Severity: MEDIUM | Kategori: DATA
Actual: user "jafar" tanpa aktivitas menampilkan score 29
Decision: DD-02 — tampilkan 0/"-" sampai aktivitas pertama

## B24 — Leaderboard menampilkan label negatif ke semua orang
Role: user | URL: /me/leaderboard | Severity: MEDIUM | Kategori: UX/PRIVACY
Decision: DD-03 — publik hanya rank+nama+skor; kategori detail hanya owner & admin

## B25 — Quiz belum memenuhi standar CBT profesional
Role: user | Severity: MEDIUM | Kategori: FEATURE
Decision: DD-04 — timer, passing grade jelas, edit/hapus soal, import Word + template

# User Findings (budi@demo.io)

## B22 — Submit case study crash 500 (Undefined array key 100)
Role: user | URL: POST /me/cases/run/6 | Severity: HIGH | Kategori: FUNCTIONAL
Steps: buka case → jawab scene → submit
Actual: ErrorException Undefined array key 100 di CaseStudyController.php:177
DevTools: POST 500
Catatan AppSec: halaman error menampilkan versi Laravel/PHP + path (hardening: APP_DEBUG=false di prod)

## B23 — User baru memiliki score non-zero di leaderboard
Role: user | URL: /me/leaderboard | Severity: MEDIUM | Kategori: DATA
Actual: user "jafar" tanpa aktivitas menampilkan score 29
Decision: DD-02 — tampilkan 0/"-" sampai aktivitas pertama

## B24 — Leaderboard menampilkan label negatif ke semua orang
Role: user | URL: /me/leaderboard | Severity: MEDIUM | Kategori: UX/PRIVACY
Decision: DD-03 — publik hanya rank+nama+skor; kategori detail hanya owner & admin

## B25 — Quiz belum memenuhi standar CBT profesional
Role: user | Severity: MEDIUM | Kategori: FEATURE
Decision: DD-04 — timer, passing grade jelas, edit/hapus soal, import Word + template

## B26 — Score tidak konsisten antara Dashboard dan Leaderboard
Role: user | URL: /user/dashboard vs /me/leaderboard | Severity: MEDIUM | Kategori: DATA
Actual: dashboard menampilkan 55, leaderboard menampilkan 57 untuk user yang sama (budi), waktu bersamaan
Expected: satu angka konsisten di semua permukaan
Catatan: bergabung dengan B23 (score user baru non-zero) → kluster "scoring pipeline"

## B27 — Simpan modul + simpan fitur/tenant bersamaan: hanya yang pertama tersimpan
Role: super_admin | URL: /platform/tenants (Kelola Akses) | Severity: MEDIUM | Kategori: FUNCTIONAL
Root cause (hipotesis): setelah save pertama, Inertia redirect memuat ulang props →
state toggle yang BELUM di-save ter-reset → save kedua mengirim state basi.

## B28 — Item ter-archive tidak punya tombol Publish (tidak bisa dipulihkan)
Role: super_admin | URL: /platform/ctf, /platform/cases, /platform/modules | Severity: MEDIUM | Kategori: UX
Expected: status archived menampilkan tombol Publish sebagai pengganti Archive.

## B29 — Packages & Billing masih error setelah policy fix
Role: super_admin | Severity: HIGH | Kategori: FUNCTIONAL
Catatan: berarti akar B02/B04 BUKAN (hanya) RLS — ada penyebab kedua.