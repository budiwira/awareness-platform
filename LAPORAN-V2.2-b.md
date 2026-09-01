# LAPORAN V2.2-b: Review Attempt CBT + Upload Avatar

**Tanggal:** 1 September 2026  
**Baseline Test:** 189 passed → **202 passed** (13 test baru)  
**Status:** ✅ SELESAI

---

## RINGKASAN

Implementasi 2 fitur utama:
1. **Review Attempt CBT** - User dapat melihat pembahasan soal quiz yang sudah dikerjakan
2. **Upload Avatar** - User dapat upload foto profil dengan validasi keamanan

---

## 1. REVIEW ATTEMPT CBT

### Backend

**Migration:**
- `add_explanation_to_quiz_questions_table` - Tambah kolom `explanation` (nullable text) untuk penjelasan jawaban

**Route:**
```php
Route::get('/quiz/attempt/{attempt}/review', [UserQuizController::class, 'review'])
    ->name('user.training.quiz.review');
```

**Controller:** `app/Http/Controllers/User/ModuleQuizController.php`
- Method `review(Request $request, QuizAttempt $attempt)`
- **RLS Check:** Hanya pemilik attempt yang bisa akses
- **Status Guard:** Hanya attempt dengan status `submitted` atau `expired`
- **Response Payload:**
  - Soal dalam **urutan asli** (BUKAN question_order teracak)
  - Opsi dalam **urutan asli** (BUKAN teracak)
  - `user_answer_index` - Indeks jawaban user (sudah dimapping ke asli)
  - `correct_index` - Indeks jawaban benar (BOLEH bocor karena attempt selesai)
  - `explanation` - Penjelasan jawaban bila tersedia

**Model:**
- `app/Models/QuizQuestion.php` - Tambah `explanation` ke `$fillable`

### Frontend

**Vue Component:** `resources/js/Pages/User/MyTraining/Review.vue`
- Layout kartu per soal dengan nomor urut
- **Highlight visual:**
  - Jawaban benar: border & background `var(--brand)` (teal) + ✓ icon
  - Jawaban salah: border & background `var(--danger)` (rose) + ✗ icon
  - Label "Jawaban Benar" dan "Jawaban Anda"
- Penjelasan jawaban dalam box `var(--surface2)` bila ada
- Header dengan skor, badge lulus/gagal, passing score, tanggal submit
- Link kembali ke MyScore
- Theme-aware: semua warna menggunakan CSS variables

**MyScore/Index.vue Updates:**
- Tabel riwayat attempt: tambah kolom **Aksi**
- Link "Review" untuk attempt dengan status `submitted` atau `expired`
- Status badge: "Lulus/Gagal" untuk submitted, "Expired" untuk expired
- Tanggal dari `submitted_at` (bukan `created_at`)

### Test Coverage

**File:** `tests/Feature/UserQuizReviewTest.php` - **6 test**

1. ✓ user can review own submitted attempt
2. ✓ user can review expired attempt
3. ✓ user cannot review in progress attempt (403)
4. ✓ user cannot review another user attempt (403)
5. ✓ review shows original question order not shuffled
6. ✓ review shows original option order not shuffled

---

## 2. UPLOAD AVATAR

### Backend

**Migration:**
- `add_avatar_path_to_users_table` - Tambah kolom `avatar_path` (nullable string) di tabel `users`

**Route:**
```php
Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])
    ->name('profile.avatar');
```

**Controller:** `app/Http/Controllers/ProfileController.php`
- Method `uploadAvatar(Request $request)`
- **Validasi:**
  - `mimes:jpg,jpeg,png,webp` - SVG **DITOLAK** (risiko XSS)
  - `max:2048` (KB) - File >2MB ditolak
- **Storage:** `storage/app/public/avatars/` dengan nama acak 40 karakter
- **Hapus file lama:** Saat upload baru, file lama dihapus otomatis
- Flash message: "Avatar berhasil diperbarui."

**Model:**
- `app/Models/User.php` - Tambah `avatar_path` ke `$fillable`

**Middleware:**
- `app/Http/Middleware/HandleInertiaRequests.php` - Pass `avatar_path` ke Inertia shared props

### Frontend

**Vue Component:** `resources/js/Pages/Profile/Partials/UpdateAvatarForm.vue`
- Preview avatar: circular 96x96px dengan ring brand
- Fallback inisial bila belum upload
- Button "Pilih Foto" trigger hidden file input
- Preview real-time saat file dipilih
- Validasi client-side: accept="image/jpeg,image/jpg,image/png,image/webp"
- Button "Upload" disabled bila belum pilih file
- Flash "Tersimpan." setelah sukses

**Profile/Edit.vue:**
- Section avatar di paling atas (sebelum profile info)
- Import dan render `UpdateAvatarForm`

**AppLayout.vue Header:**
- Tampilkan avatar dengan `url(/storage/${user.avatar_path})`
- Background-image circular 32x32px dengan ring brand
- Fallback inisial bila `avatar_path` null
- Conditional rendering: `v-if="user?.avatar_path"` vs `v-else` (initials)

### Test Coverage

**File:** `tests/Feature/ProfileAvatarTest.php` - **7 test**

1. ✓ user can upload avatar
2. ✓ avatar rejects svg (keamanan)
3. ✓ avatar rejects file over 2mb
4. ✓ avatar accepts png webp
5. ✓ uploading new avatar deletes old file
6. ✓ avatar appears in app layout (Inertia props)
7. ✓ fallback initials when no avatar

---

## VERIFIKASI

### Build
```bash
npm run build
```
✅ **SUKSES** - Vite build completed (6.62s)

### Test Suite
```bash
php artisan test
```
✅ **202 passed** (831 assertions) - Duration: 27.75s

**Breakdown:**
- Baseline: 189 passed
- UserQuizReviewTest: +6 passed
- ProfileAvatarTest: +7 passed

**Tidak ada test yang gagal atau di-skip.**

---

## FILE YANG DIUBAH

### Backend (10 files)
1. `database/migrations/2026_09_01_064924_add_explanation_to_quiz_questions_table.php` ✨ NEW
2. `database/migrations/2026_09_01_065104_add_avatar_path_to_users_table.php` ✨ NEW
3. `app/Models/QuizQuestion.php` - Add `explanation` to fillable
4. `app/Models/User.php` - Add `avatar_path` to fillable
5. `app/Http/Controllers/User/ModuleQuizController.php` - Add `review()` method
6. `app/Http/Controllers/ProfileController.php` - Add `uploadAvatar()` method
7. `app/Http/Middleware/HandleInertiaRequests.php` - Pass `avatar_path` to frontend
8. `routes/web.php` - Add 2 routes

### Frontend (5 files)
9. `resources/js/Pages/User/MyTraining/Review.vue` ✨ NEW
10. `resources/js/Pages/Profile/Partials/UpdateAvatarForm.vue` ✨ NEW
11. `resources/js/Pages/User/MyScore/Index.vue` - Add review link column
12. `resources/js/Pages/Profile/Edit.vue` - Add avatar section
13. `resources/js/Layouts/AppLayout.vue` - Display avatar in header

### Test (2 files)
14. `tests/Feature/UserQuizReviewTest.php` ✨ NEW - 6 test
15. `tests/Feature/ProfileAvatarTest.php` ✨ NEW - 7 test

**Total:** 15 files (5 new, 10 modified)

---

## COMMIT HISTORY

```
221f7da test(v2.2-b): review + avatar test coverage
c6afe00 feat(profile): upload avatar dengan validasi + tampil di header
25f1778 feat(cbt): review attempt dengan soal & opsi urut asli + link di MyScore
```

---

## STANDAR YANG DIPENUHI

### AGENTS.md
✅ **Commit per sub-tugas** - 3 commit terpisah (review, avatar, test)  
✅ **UI Modern-Dinamis-Profesional:**
- Whitespace lega, radius xl, shadow lembut
- Hover/focus/active state pada semua elemen interaktif
- Transisi 150-200ms
- CSS variables untuk theme-aware (dark-ready)
- Badge dengan kontras aksesibel
- Tanggal format konsisten (d MMM yyyy)

### Otonomi V2
✅ **Tidak ada migration RLS** - Hanya ALTER TABLE biasa (add column)  
✅ **Test coverage lengkap** - Happy path + authorization (owner only, status guard)  
✅ **RLS check eksplisit** - Controller method `review()` cek `user_id` ownership

### Definisi Selesai (KERAS)
✅ **Test baseline terjaga** - 189 → 202 passed (0 failed, 0 incomplete)  
✅ **Baris ringkasan ASLI:**
```
Tests:    202 passed (831 assertions)
Duration: 27.75s
```

---

## KEAMANAN

1. **SVG ditolak** - Validasi `mimes:jpg,jpeg,png,webp` mencegah XSS via SVG
2. **Max 2MB** - Mencegah DoS via file besar
3. **Nama acak** - `Str::random(40)` mencegah path traversal
4. **RLS enforcement** - Review attempt hanya pemilik, status submitted/expired only
5. **Hapus file lama** - Mencegah disk space leak

---

## CATATAN TEKNIS

1. **Urutan asli vs teracak:**
   - Saat attempt: soal & opsi diacak (`question_order`, `option_orders`)
   - Saat review: ditampilkan urutan asli dari database
   - Mapping jawaban user dari indeks teracak → asli

2. **correct_index boleh bocor:**
   - Review hanya untuk attempt selesai (submitted/expired)
   - User sudah tidak bisa ubah jawaban
   - Tujuan: pembelajaran dari kesalahan

3. **Storage symlink:**
   - Avatar disimpan ke `storage/app/public/avatars/`
   - Accessible via `/storage/avatars/` (symlink harus sudah dibuat)
   - Command: `php artisan storage:link`

4. **Theme-aware:**
   - Semua warna via CSS variables (`var(--brand)`, `var(--danger)`, dll)
   - Mendukung light/dark theme toggle existing
   - Tidak ada hardcoded color values

---

## HASIL AKHIR

✅ **Review attempt:** User dapat belajar dari kesalahan dengan melihat kunci jawaban + penjelasan  
✅ **Upload avatar:** User dapat personalisasi profil dengan foto (validasi keamanan ketat)  
✅ **Test coverage:** 13 test baru memastikan authorization & validasi  
✅ **Theme-aware:** UI konsisten dengan design system existing  
✅ **Baseline terjaga:** 202/202 passed, 0 regression

**Status:** PRODUCTION READY ✅
