# Laporan Tugas V2.3-A.2: Integrasi Phishing Awareness Score + Auto-Remedial

**Tanggal:** 2026-09-01  
**Branch:** lab/ui-polish  
**Baseline Test:** 217 passed  
**Hasil Akhir:** 225 passed, 0 failed

---

## Ringkasan Implementasi

Tugas ini menambahkan sinyal **phishing_awareness** ke sistem scoring, auto-remedial assignment saat user klik link phishing, dan kartu dashboard phishing awareness untuk tenant admin.

---

## 1. AWARENESS SCORE (app/Support/Scoring/AwarenessScore.php)

### Perubahan Skema

**SEBELUM (5 sinyal):**
```
completion: 25%, quiz: 25%, case: 15%, ctf: 15%, ttx: 20%
```

**SESUDAH (6 sinyal):**
```
completion: 20%, quiz: 20%, case: 15%, ctf: 15%, ttx: 15%, phishing_awareness: 15%
```

### Logika Perhitungan Phishing Awareness

```php
private function phishingAwareness(Collection $phishingTargets): float
{
    if ($phishingTargets->count() === 0) {
        return 100; // Belum diuji = asumsi aman
    }

    $clicked = $phishingTargets->where('status', 'clicked')->count();
    $clickRate = ($clicked / $phishingTargets->count()) * 100;

    return max(0, 100 - ($clickRate * 0.5));
}
```

**Rumus:** `score = 100 - (click_rate_personal * 50)`

**Contoh:**
- User tanpa kampanye: **score = 100** (belum diuji)
- User 1 click dari 1 campaign: **score = 50** (click_rate = 100%)
- User 2 click dari 4 campaign: **score = 75** (click_rate = 50%)

### Entitlement-Aware

Sinyal phishing hanya aktif jika tenant memiliki fitur `'phishing'` dalam plan. Jika tidak, sinyal ditandai `locked: true` dan bobot renormalisasi ke sinyal lain.

**Perubahan Signature:**
```php
public function compute(
    Collection $assignments,
    Collection $quizAttempts,
    Collection $caseParticipations,
    Collection $ctfSolves,
    Collection $ttxScores,
    int $totalCtfPoints = 0,
    ?array $entitledFeatures = null,
    Collection $phishingTargets = null  // PARAMETER BARU
): array
```

### File yang Diubah
- `app/Support/Scoring/AwarenessScore.php` (tambah method `phishingAwareness`, update `WEIGHTS`, `SIGNAL_FEATURES`, `compute`)
- `app/Http/Controllers/User/MyScoreController.php` (tambah `PhishingTarget` import + query)
- `routes/web.php` (update user dashboard + tenant dashboard untuk sertakan phishing targets)

---

## 2. AUTO-REMEDIAL (app/Http/Controllers/PhishingTrapController.php)

### Alur

1. User klik link phishing (`/phish/{token}`)
2. Target status diubah menjadi `'clicked'`
3. Sistem mencari modul remedial:
   - Status `'published'`
   - `is_active = true`
   - Judul mengandung "Phishing" atau "Email"
4. Jika ditemukan dan user belum punya assignment aktif untuk modul tersebut:
   - Buat `ModuleAssignment` baru dengan status `'assigned'`
   - Set flash session `remedial_assigned = true`
5. Redirect ke halaman teaching dengan pesan remedial (jika ada)

### Logika Deduplication

```php
$existingAssignment = ModuleAssignment::where('user_id', $target->user_id)
    ->where('training_module_id', $remedialModule->id)
    ->where('tenant_id', $target->campaign->tenant_id)
    ->whereIn('status', ['assigned', 'in_progress'])
    ->exists();

if ($existingAssignment) {
    return; // Skip duplikasi
}
```

### File yang Diubah
- `app/Http/Controllers/PhishingTrapController.php` (tambah method `autoAssignRemedial`, import `ModuleAssignment` + `TrainingModule`)
- `resources/js/Pages/Public/PhishingTeaching.vue` (tambah props `remedialAssigned`, tambah kartu notifikasi biru)

---

## 3. DASHBOARD TENANT (routes/web.php + resources/js/Pages/Tenant/Dashboard.vue)

### Data Aggregate Backend

Route `tenant.dashboard` sekarang menghitung statistik phishing (jika tenant ber-entitle):

```php
$phishingStats = [
    'avg_click_rate' => (int) round(($clickedCount / $sentCount) * 100),
    'campaigns_sent' => $campaignsCount,
    'users_at_risk' => $atRiskUsers, // click rate > 50%
];
```

**Users at-risk:** User dengan `(clicked / total_targets) > 0.5`

### Kartu Dashboard Frontend

Kartu hanya muncul jika `phishingStats !== null` (tenant punya fitur phishing):

```vue
<div v-if="phishingStats" class="card p-6 mb-8">
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="p-4 rounded-xl bg-surface-alt">
      <div class="text-xs t-muted mb-1">Rata-rata Click Rate</div>
      <div class="font-display text-2xl font-bold t-ink">{{ phishingStats.avg_click_rate }}%</div>
    </div>
    <div class="p-4 rounded-xl bg-surface-alt">
      <div class="text-xs t-muted mb-1">Kampanye Terkirim</div>
      <div class="font-display text-2xl font-bold t-ink">{{ phishingStats.campaigns_sent }}</div>
    </div>
    <div class="p-4 rounded-xl bg-surface-alt">
      <div class="text-xs t-muted mb-1">Pengguna Berisiko</div>
      <div class="font-display text-2xl font-bold text-danger">{{ phishingStats.users_at_risk }}</div>
    </div>
  </div>
</div>
```

### File yang Diubah
- `routes/web.php` (tambah aggregate query phishing stats di tenant dashboard route)
- `resources/js/Pages/Tenant/Dashboard.vue` (tambah props `phishingStats`, tambah kartu phishing awareness)

---

## 4. TEST

### Test Baru

#### `tests/Feature/PhishingAwarenessScoreTest.php` (4 test)
1. ✅ User tanpa kampanye phishing mendapat score 100
2. ✅ User dengan 1 click dari 1 campaign mendapat score 50
3. ✅ Tenant tanpa fitur phishing → sinyal locked + bobot renormalisasi
4. ✅ User dengan 2 click dari 4 campaign mendapat score 75

#### `tests/Feature/PhishingAutoRemedialTest.php` (4 test)
1. ✅ Klik phishing trap membuat assignment remedial jika modul tersedia
2. ✅ Klik phishing trap tidak membuat assignment duplikat
3. ✅ Klik phishing trap tanpa modul remedial tidak error
4. ✅ Phishing teaching page menampilkan pesan remedial saat assignment dibuat

### Test Lama yang Diupdate

`tests/Unit/AwarenessScoreTest.php` (7 test):
- Update semua signature `compute()` untuk sertakan parameter `phishingTargets`
- Update ekspektasi bobot: dari 5 sinyal ke 6 sinyal
- Update test "no data scores 0" → sekarang score 15 (karena phishing_awareness = 100 * 0.15)
- Update test renormalisasi untuk memperhitungkan sinyal phishing yang locked

---

## 5. HASIL VERIFIKASI

### Build Frontend
```
✓ built in 5.95s
```

### Test Suite
```
Tests:    225 passed (968 assertions)
Duration: 33.93s
```

**Baseline:** 217 passed  
**Hasil Akhir:** 225 passed (+8 test baru)  
**Failed:** 0  
**Incomplete:** 0

✅ **ATURAN KERAS TERPENUHI:** Hasil >= 217, 0 failed, 0 incomplete

---

## 6. PERUBAHAN FILE

### Backend (PHP)
1. `app/Support/Scoring/AwarenessScore.php` - Tambah sinyal phishing_awareness
2. `app/Http/Controllers/PhishingTrapController.php` - Auto-remedial assignment
3. `app/Http/Controllers/User/MyScoreController.php` - Query phishing targets untuk user score
4. `routes/web.php` - Update user dashboard + tenant dashboard untuk phishing data

### Frontend (Vue)
1. `resources/js/Pages/Public/PhishingTeaching.vue` - Tambah notifikasi remedial
2. `resources/js/Pages/Tenant/Dashboard.vue` - Tambah kartu phishing awareness

### Test
1. `tests/Feature/PhishingAwarenessScoreTest.php` - Test scoring (NEW)
2. `tests/Feature/PhishingAutoRemedialTest.php` - Test auto-remedial (NEW)
3. `tests/Unit/AwarenessScoreTest.php` - Update untuk 6 sinyal

---

## 7. COMMIT HISTORY

```
766c464 fix(v2.3-a.2): update unit tests untuk 6 sinyal awareness score
cd8ac60 test(v2.3-a.2): phishing awareness score + auto-remedial tests
775d464 feat(v2.3-a.2): dashboard phishing awareness card
399309e feat(v2.3-a.2): phishing awareness score + auto-remedial assignment
```

---

## 8. COMPLIANCE ATURAN KERAS

✅ **Baseline test:** 217 passed  
✅ **Hasil akhir:** 225 passed  
✅ **Failed:** 0  
✅ **Incomplete:** 0  
✅ **npm run build:** SEBELUM test (passed)  
✅ **Baris ringkasan ASLI:** `Tests:    225 passed (968 assertions)`  
✅ **Kerja di lab:** Branch `lab/ui-polish`  
✅ **Commit per sub-tugas:** 4 commit terpisah  

---

## 9. FITUR TIDAK DIUBAH

Sesuai brief, fitur berikut **TIDAK DIUBAH**:
- ❌ CBT (quiz system)
- ❌ Billing/subscription core logic
- ❌ Auth middleware
- ❌ RLS policy tabel lain (hanya aggregate query)
- ❌ Migration baru (tidak ada perubahan skema DB)

---

## 10. CARA VERIFIKASI MANUAL

### Scenario 1: User Score dengan Phishing
1. Login sebagai user tenant yang punya fitur phishing
2. Buka `/user/score`
3. Cek breakdown, harus ada sinyal **"Phishing Awareness"** dengan score 100 (jika belum pernah kena kampanye)

### Scenario 2: Auto-Remedial
1. Buat modul training dengan judul "Keamanan Email Dasar"
2. Buat kampanye phishing → kirim ke user
3. Klik link phishing sebagai user tersebut
4. Halaman teaching muncul, ada notifikasi biru: "Kami telah menugaskan pelatihan tambahan..."
5. Cek `/training` → ada assignment baru untuk modul tersebut dengan status "assigned"

### Scenario 3: Dashboard Tenant
1. Login sebagai tenant admin dengan plan Pro/Enterprise (punya fitur phishing)
2. Buka `/tenant/dashboard`
3. Lihat kartu **"Phishing Awareness"** dengan 3 metrik:
   - Rata-rata Click Rate
   - Kampanye Terkirim
   - Pengguna Berisiko

---

## SELESAI

Semua requirement V2.3-A.2 telah diimplementasi, ditest, dan diverifikasi. Sistem phishing awareness sekarang terintegrasi penuh dengan awareness score, auto-remedial bekerja saat user klik trap, dan tenant admin dapat memantau click rate lewat dashboard.
