# Laporan Implementasi V2.3-A.1: Simulasi Phishing

## Ringkasan
Fitur simulasi phishing untuk melatih kewaspadaan user terhadap email phishing. Tenant admin dapat membuat kampanye, mengirim email simulasi, dan melihat laporan click-rate. User yang mengklik link melihat halaman edukasi (teaching page) tanpa form kredensial.

## Baseline Test
- **Sebelum:** 207 passed
- **Setelah:** 217 passed (10 test baru)
- **Status:** ✅ Baseline terjaga, +10 passed

## Hasil Test Akhir
```
Tests:    217 passed (938 assertions)
Duration: 32.30s
```

## Skema Database

### 1. phishing_campaigns
```sql
- id, tenant_id (uuid, indexed, FK tenants)
- title, sender_name, subject, body_template (text dengan {{link}})
- status (draft|running|completed)
- created_by (FK users)
- email_snapshot (JSON: {subject, body} untuk preview)
- timestamps
```

**RLS Policies:**
- SELECT: tenant isolation OR super_admin
- INSERT: tenant_id match
- UPDATE: tenant_id match OR super_admin

### 2. phishing_targets
```sql
- id, campaign_id (FK phishing_campaigns)
- user_id (FK users)
- token (varchar 64, unique)
- status (sent|clicked)
- clicked_at (nullable timestamp)
- timestamps
- INDEX (campaign_id, status)
```

**RLS Policies:**
- ALL: EXISTS campaign dengan tenant isolation OR user_id match
- WITH CHECK: campaign tenant_id match OR super_admin

## Routes

### Tenant Admin (Gate: phishing feature)
```
GET    /tenant/phishing                   -> index
GET    /tenant/phishing/create            -> create
POST   /tenant/phishing                   -> store
GET    /tenant/phishing/{campaign}        -> show (report)
POST   /tenant/phishing/{campaign}/send   -> send
```

### Public (Unauthenticated)
```
GET    /phish/{token}                     -> PhishingTrapController@show
```

## Backend

### Models
- **PhishingCampaign:** relasi tenant, creator (User), targets (HasMany)
- **PhishingTarget:** relasi campaign, user
- Fillable sesuai spesifikasi, cast email_snapshot ke array

### Controllers
1. **PhishingCampaignController (Tenant):**
   - Gate `hasFeature($tenant, 'phishing')` di semua method
   - `store`: buat campaign + targets dengan token random 64 char
   - `send`: snapshot email ke JSON, status -> running, kirim Mail::to() per target
   - `show`: aggregate stats (total, clicked, click_rate %), tabel targets dengan user name/email

2. **PhishingTrapController (Public):**
   - Cari token dengan status=sent
   - Update status=clicked + clicked_at
   - Render Inertia `Public/PhishingTeaching`
   - Token invalid/already clicked -> 404

### Mailable
- **PhishingSimMail:** text-only email
- Replace `{{link}}` dengan `url("/phish/{token}")`
- Envelope from: `$senderName`, subject: `$emailSubject` (renamed untuk hindari conflict dengan parent `$subject`)

## Frontend (Vue 3 + Inertia)

### 1. Menu Gate
**AppLayout.vue** (tenant_admin section):
```vue
{ label: 'Simulasi Phishing', route: 'tenant.phishing.index', 
  locked: !entitlements?.features?.includes('phishing') }
```

### 2. Index (Tenant/Phishing/Index.vue)
- Empty state: icon, "Belum ada kampanye"
- List: title, sender_name, subject, targets_count, status badge (draft/running/completed)
- Link ke show per campaign

### 3. Create (Tenant/Phishing/Create.vue)
- Form: title, sender_name, subject, body_template (textarea dengan hint `{{link}}`)
- Multi-select users dengan checkbox + "Pilih semua"
- Validasi: minimal 1 target
- Submit -> draft campaign

### 4. Show (Tenant/Phishing/Show.vue)
- Stats cards: Total Target, Diklik, Click Rate % (warna dinamis)
- Preview email (sender, subject, body)
- Tabel targets: nama, email, status badge (sent/clicked), waktu klik
- Tombol "Kirim Sekarang" untuk draft campaign (konfirmasi modal)

### 5. Teaching Page (Public/PhishingTeaching.vue)
- **Standalone layout** (tidak pakai AppLayout, dark theme-aware)
- Hero: icon warning, "Ini Adalah Simulasi Keamanan"
- 3 Red Flags: Pengirim Mencurigakan, Urgensi Berlebihan, Tautan Mencurigakan
- Tips: verifikasi pengirim, jangan share password, laporkan
- Tombol "Saya Mengerti" (window.close)
- **TIDAK ADA FORM KREDENSIAL** (sesuai spec)

## Test Suite (PhishingSimulationTest.php)

10 test cases, semua passed:
1. ✅ Tenant tanpa fitur phishing -> 403 + FeatureLocked
2. ✅ Tenant dengan fitur phishing dapat akses index
3. ✅ Admin dapat create campaign dengan targets (token 64 char unique)
4. ✅ RLS tenant isolation via HTTP (403 cross-tenant access)
5. ✅ Send campaign: snapshot email, status=running, Mail::assertSent
6. ✅ Klik token valid: status=clicked, clicked_at set, teaching page rendered
7. ✅ Klik token invalid -> 404
8. ✅ Klik token yang sudah clicked -> 404
9. ✅ Tenant tidak bisa akses campaign tenant lain (403)
10. ✅ Teaching page render komponen Public/PhishingTeaching

## Entitlements

### PlanSeeder Update
```php
'pro' => ['training', 'reports_export', 'ttx', 'case_studies', 'phishing']
'enterprise' => ['training', ..., 'ctf', 'phishing']
'starter' => ['training'] // TIDAK termasuk phishing
```

Seed otomatis: `php artisan db:seed --class=PlanSeeder`

## File yang Dibuat/Diubah

### Database
- `2026_09_01_075135_create_phishing_campaigns_table.php` (RLS 3 policies)
- `2026_09_01_075136_create_phishing_targets_table.php` (RLS 1 policy)

### Backend
- `app/Models/PhishingCampaign.php`
- `app/Models/PhishingTarget.php`
- `app/Mail/PhishingSimMail.php`
- `app/Http/Controllers/Tenant/PhishingCampaignController.php`
- `app/Http/Controllers/PhishingTrapController.php`
- `resources/views/emails/phishing-sim.blade.php`

### Routes
- `routes/web.php`: 6 routes (5 tenant, 1 public)

### Frontend
- `resources/js/Pages/Tenant/Phishing/Index.vue`
- `resources/js/Pages/Tenant/Phishing/Create.vue`
- `resources/js/Pages/Tenant/Phishing/Show.vue`
- `resources/js/Pages/Public/PhishingTeaching.vue`
- `resources/js/Layouts/AppLayout.vue` (menu gate)

### Tests
- `tests/Feature/PhishingSimulationTest.php` (10 test)

### Config
- `database/seeders/PlanSeeder.php` (fitur 'phishing' untuk Pro & Enterprise)

## Git Commits
```
4d76ddb fix(v2.3-a.1): perbaiki PhishingSimMail $subject conflict + test RLS via HTTP
845a243 test(v2.3-a.1): PhishingSimulationTest (entitlements, RLS, send, click, teaching)
6f5b384 feat(v2.3-a.1): Vue phishing CRUD, report, teaching page + menu gate
e73c60d feat(v2.3-a.1): route phishing campaigns (tenant admin) + public trap
0631e01 feat(v2.3-a.1): PhishingSimMail + template blade
fe6d509 feat(v2.3-a.1): model PhishingCampaign, PhishingTarget + relasi
042d561 feat(v2.3-a.1): migration phishing_campaigns + phishing_targets + RLS policies
5b29291 feat(v2.3-a.1): tambah fitur 'phishing' ke Plan Pro & Enterprise
```

## Standar UI yang Diterapkan
- ✅ Whitespace lega, radius xl/2xl, shadow lembut
- ✅ Ikon SVG inline konsisten (email, warning, lock)
- ✅ Warna lewat CSS variables (dark-ready)
- ✅ Semua elemen interaktif punya hover/focus/active state
- ✅ Transisi 150-200ms
- ✅ Empty state (EmptyState komponen)
- ✅ Status badge: badge-default (draft), badge-info (running), badge-ok (completed), badge-danger (clicked)
- ✅ Teks UI Bahasa Indonesia konsisten
- ✅ Font display untuk judul campaign

## Verifikasi Akhir
```bash
npm run build        # ✅ Success, 6.70s
php artisan test     # ✅ 217 passed (938 assertions), 32.30s
```

## Catatan Teknis

1. **Token Generation:** `Str::random(64)` untuk keamanan, unique constraint di DB
2. **Email Snapshot:** Disimpan saat send untuk preservasi konten asli (audit trail)
3. **RLS Isolation:** phishing_targets policy via EXISTS subquery ke campaigns
4. **Mailable Fix:** Rename `$subject` ke `$emailSubject` untuk hindari conflict dengan parent Mailable class
5. **Test Strategy:** RLS diverifikasi via HTTP (403 cross-tenant) bukan raw DB query untuk menghindari session persistence issue
6. **Teaching Page:** Standalone Inertia page tanpa auth, dark theme-aware, NO credential form

## Kesimpulan
✅ Fitur simulasi phishing lengkap sesuai spec V2.3-A.1
✅ Baseline 207 test terjaga, bertambah menjadi 217 passed
✅ RLS policies enforce tenant isolation
✅ UI modern, theme-aware, accessible
✅ Tidak ada form kredensial di teaching page (aman)
✅ Email log-only (dev), siap production SMTP
