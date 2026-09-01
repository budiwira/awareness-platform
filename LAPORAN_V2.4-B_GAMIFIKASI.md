# LAPORAN V2.4-B: GAMIFIKASI

## RINGKASAN
Implementasi sistem gamifikasi untuk meningkatkan engagement user melalui badge, streak login, dan leaderboard.

## STATUS
✅ SELESAI - 252 passed (1133 assertions)
- Baseline: 238 passed
- Hasil akhir: 252 passed (+14 test baru)
- 0 failed, 0 incomplete

## SKEMA DATABASE

### 1. badges
- id, name, description, icon (emoji/SVG)
- category: completion, quiz, phishing, streak, achievement
- criteria_type: first_module_completed, quiz_perfect_score, streak_days, dll
- criteria_value: nullable integer (threshold)
- is_active
- Tidak perlu RLS (global untuk semua tenant)

### 2. user_badges
- id, user_id, badge_id, tenant_id, earned_at
- RLS enabled: tenant_isolation policy (SELECT), insert policy, update policy (super_admin only)
- Unique constraint: (user_id, badge_id)

### 3. users (tambahan kolom)
- login_streak: integer default 0
- last_login_date: date nullable
- show_on_leaderboard: boolean default true

## SEED DATA
13 badges awal:
- First Steps: complete first module
- Quiz Master: pass 5 quizzes
- Perfect Score: score 100
- Phishing Defender: never clicked (min 3 campaigns)
- 7-Day Streak, 30-Day Streak, 100-Day Streak
- Completionist: all modules completed
- CTF Explorer, Case Solver, TTX Participant
- High Achiever (score 90+), Security Champion (score 95+)

## FITUR

### 1. Badge Auto-Award
Service: `app/Services/BadgeAwardService.php`
- checkModuleCompletion(): first module, all modules
- checkQuizPerformance(): quiz passed count, perfect score
- checkPhishingAwareness(): never clicked dengan min 3 campaigns
- checkStreak(): 7/30/100 day milestones
- checkAwarenessScore(): threshold 90/95
- checkFirstCTF/Case/TTX

Observers:
- QuizAttemptObserver: trigger saat quiz submitted & passed
- ModuleAssignmentObserver: trigger saat assignment completed
- Registered di AppServiceProvider

### 2. Streak Logic
Lokasi: `AuthenticatedSessionController@store`
- Login hari yang sama: no change
- Login hari kemarin: streak++
- Gap > 1 hari: reset ke 1
- Auto check badge setelah update streak

### 3. Leaderboard
Route: `GET /me/leaderboard`
Controller: `User\LeaderboardController`
- Hanya user dalam tenant sendiri
- Hanya user dengan show_on_leaderboard = true
- Sorted by awareness score descending (real-time compute)
- Top 20 + current user position & data
- RLS enforced via controller (filter tenant_id)

### 4. Profile Gamification
Halaman Profile (`Profile/Edit.vue`) menampilkan:
- GamificationStats component:
  * Login streak (hari)
  * Badge terkumpul (count)
  * Toggle opt-out leaderboard
  * Link ke badges & leaderboard
- Controller mengirim loginStreak & earnedBadges

### 5. Halaman My Badges
Route: `GET /me/badges`
View: `User/Badges.vue`
- Grid badges grouped by category
- Badge earned: full color + tanggal
- Badge locked: grayscale + "Belum didapat"
- Progress bar: X dari Y badge terkumpul

## ROUTES BARU
```php
// User routes (prefix: /me, auth + can:access-user-dashboard)
Route::get('/badges', [BadgeController::class, 'index'])->name('user.badges.index');
Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('user.leaderboard.index');
```

## UI/UX
- Badge page: card grid, emoji icon 5xl, category grouping
- Leaderboard: top 20 list, rank medal (gold/silver/bronze), current user highlight
- Profile: stats cards (streak 🔥, badges 🏆), leaderboard toggle
- AppLayout: menu "Badge Saya" & "Leaderboard" di section "Saya"
- Theme-aware: CSS variables, no hardcoded colors

## TEST COVERAGE

### BadgeAwardTest (4 tests)
- ✅ user earns first module completed badge
- ✅ user earns perfect score badge
- ✅ user earns streak badge after 7 days login
- ✅ badge is not awarded twice

### StreakTest (4 tests)
- ✅ streak increments on consecutive day login
- ✅ streak resets after missing a day
- ✅ streak does not change on same day login
- ✅ streak starts at 1 on first login

### LeaderboardTest (6 tests)
- ✅ leaderboard shows only users from same tenant
- ✅ leaderboard excludes users who opted out
- ✅ leaderboard shows current user position
- ✅ leaderboard sorts by awareness score descending
- ✅ user can toggle leaderboard visibility in profile
- ✅ opted out user does not appear in leaderboard

## FILE YANG DIUBAH/DIBUAT

### Backend
**Migration:**
- 2026_09_01_090527_create_badges_table.php
- 2026_09_01_090529_create_user_badges_table.php (RLS)
- 2026_09_01_090530_add_streak_fields_to_users_table.php
- 2026_09_01_090531_add_show_on_leaderboard_to_users_table.php

**Models:**
- app/Models/Badge.php
- app/Models/UserBadge.php
- app/Models/User.php (update: relationships, fillable, casts)

**Services:**
- app/Services/BadgeAwardService.php

**Observers:**
- app/Observers/QuizAttemptObserver.php
- app/Observers/ModuleAssignmentObserver.php

**Controllers:**
- app/Http/Controllers/User/BadgeController.php
- app/Http/Controllers/User/LeaderboardController.php
- app/Http/Controllers/Auth/AuthenticatedSessionController.php (streak logic)
- app/Http/Controllers/ProfileController.php (gamification stats)

**Requests:**
- app/Http/Requests/ProfileUpdateRequest.php (validation show_on_leaderboard)

**Providers:**
- app/Providers/AppServiceProvider.php (register observers)

**Seeders:**
- database/seeders/BadgeSeeder.php

**Routes:**
- routes/web.php (badges & leaderboard routes)

### Frontend
**Pages:**
- resources/js/Pages/User/Badges.vue
- resources/js/Pages/User/Leaderboard.vue
- resources/js/Pages/Profile/Edit.vue (update props)
- resources/js/Pages/Profile/Partials/GamificationStats.vue

**Layouts:**
- resources/js/Layouts/AppLayout.vue (menu baru)

### Tests
- tests/Feature/BadgeAwardTest.php
- tests/Feature/StreakTest.php
- tests/Feature/LeaderboardTest.php

## COMMITS
```
7c1a5f4 fix(v2.4-b): fix test fixtures - create training module before quiz
77bb72b feat(v2.4-b): tests for badge, streak, leaderboard
e8176b1 feat(v2.4-b): profile gamification (badges, streak, leaderboard opt-out)
5c7c5f4 feat(v2.4-b): leaderboard controller + routes
a43c561 feat(v2.4-b): streak logic on login
5d4cc9f feat(v2.4-b): badge auto-award service + observers
4c0deae feat(v2.4-b): badges schema + seed
```

## VERIFIKASI BUILD
```
npm run build
✓ built in 6.13s
841 modules transformed
Manifest: 27.24 kB
Total assets: ~500 kB gzipped
```

## VERIFIKASI TEST
```
php artisan test
Tests:    252 passed (1133 assertions)
Duration: 36.98s
```

## QA MANUAL CHECKLIST
- [ ] Login user -> streak increment
- [ ] Complete module -> earn "First Steps" badge
- [ ] Quiz score 100 -> earn "Perfect Score"
- [ ] Login 7 hari berturut -> earn "7-Day Streak"
- [ ] Buka /me/badges -> lihat badge grid
- [ ] Buka /me/leaderboard -> top 20 users sorted
- [ ] Profile -> streak & badge stats tampil
- [ ] Toggle leaderboard opt-out -> user hilang dari leaderboard
- [ ] Dark/light theme -> semua elemen terbaca

## CATATAN
- Badge auto-award via observer: real-time saat event terjadi
- Leaderboard compute awareness score real-time (tidak cached)
- Streak logic: update setiap kali login, bukan middleware terpisah
- RLS enforced: user_badges isolated by tenant_id
- Opt-out leaderboard: user tetap bisa lihat leaderboard tapi tidak muncul di list
- Badge criteria extensible: tambah badge baru lewat seeder
- UI menggunakan emoji untuk icon badge (tidak perlu asset eksternal)

## NEXT STEPS (V2.5)
- Push notification saat earn badge (opsional)
- Badge leaderboard: top badge collectors
- Team challenges & group badges
- Custom badge design per tenant
- Badge history timeline
