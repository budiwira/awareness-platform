# Laporan Tugas V2.3-B: Landing Page Publik

## Ringkasan
Landing page publik untuk Security Awareness Platform telah selesai dibangun dengan Astra UI System dark-first, theme-aware, dan fully responsive. Route "/" sekarang menampilkan landing page produk profesional, bukan redirect login.

## Perubahan File

### 1. Route (routes/web.php)
- Route "/" diubah dari `redirect()->route('login')` menjadi render Inertia page `Public/Landing`
- Route mengirim data `auth.user` untuk membedakan guest vs authenticated
- Route login tetap tersedia di "/login"

### 2. Landing Page (resources/js/Pages/Public/Landing.vue)
Struktur lengkap:
- **Navbar publik**: Logo, anchor links (Fitur, Cara Kerja, Paket, Keamanan), theme toggle, CTA dinamis
- **Hero**: Headline "Bangun Budaya Keamanan Siber yang Terukur", subheadline, CTA, mock dashboard card
- **Metrics strip**: 3 kartu (CBT Interaktif, Plan & Entitlements, Tenant Analytics)
- **Fitur utama**: 6 fitur cards (Training Modules, CBT Quiz, TTX, Case Study, CTF, Reports)
- **Cara kerja**: 4 langkah (Setup, Assign, Complete, Monitor)
- **Paket**: 4 tier (Starter, Pro, Enterprise, Custom) sesuai entitlements V2.1-E
- **Keamanan & Etika**: 4 prinsip (Multi-tenant isolation, Server-side scoring, RBAC, Privasi)
- **Final CTA**: Call to action closing
- **Footer**: Copyright line

### 3. Theme Toggle Publik
- localStorage key "theme" (sama dengan Login.vue)
- Default dark bila key kosong
- Toggle icon SVG (sun/moon) di navbar
- onMounted set theme dari localStorage atau default 'dark'

### 4. CSS Variables (app.css)
- Tidak ada perubahan CSS
- Semua styling Landing.vue menggunakan CSS variables existing
- Tidak ada hex hard-coded
- Tidak ada `dark:` variant Tailwind
- Verified: `grep '#[0-9A-Fa-f]{6}' Landing.vue` = 0 hasil
- Verified: `grep 'dark:' Landing.vue` = 0 hasil

### 5. Test (tests/Feature/PublicLandingTest.php)
5 test cases baru:
1. `test_guest_can_access_landing_page` - guest buka "/" dapat Landing component, auth.user = null
2. `test_landing_page_renders_for_guest` - status 200, component Public/Landing
3. `test_authenticated_user_can_access_landing_page` - user login buka "/" dapat Landing + auth.user
4. `test_login_route_still_accessible` - route "/login" tetap berfungsi
5. `test_dashboard_route_redirects_by_role` - route "/dashboard" redirect by role (User → user.dashboard)

### 6. Test Update (tests/Feature/SmokeTest.php)
- Test "home redirects guest to login" diubah menjadi "home renders landing page for guest"
- Dari `assertRedirect(route('login'))` menjadi `assertOk()`

## Commit History
```
33ec797 test(v2.3-b): PublicLandingTest + update SmokeTest untuk landing page
6f91f76 feat(v2.3-b): Landing.vue dengan Astra dark-first, theme toggle, sections lengkap
1572db2 feat(v2.3-b): route publik '/' render Landing.vue
```

## Hasil Verifikasi

### Build
```
npm run build
✓ built in 5.60s
public/build/assets/Landing-Cd2difjU.js    28.40 kB │ gzip: 5.82 kB
```

### Test Suite
```
Tests:    207 passed (870 assertions)
Duration: 28.62s
```

**Baseline: 202 passed → Hasil: 207 passed (+5 test baru)**
- ✅ 0 failed
- ✅ 0 incomplete
- ✅ Semua test lama tetap hijau

### QA Manual
Server: http://127.0.0.1:8000/

**Dark Mode (default):**
- ✅ Background near-black (--bg: #0A0A0E)
- ✅ Surface violet-black (--surface: #131320)
- ✅ Card radius 24px premium
- ✅ Button pill dengan glow violet hover
- ✅ Tidak ada blok putih / warna generik
- ✅ Brand violet (#7C3AED) konsisten

**Light Mode:**
- ✅ Teks terbaca (--ink: #111827, --muted: #6b7280)
- ✅ Card bersih putih dengan border
- ✅ Tidak ada input/card hitam
- ✅ Brand teal (#0f766e) konsisten

**Responsive:**
- ✅ Hero stack vertikal di mobile
- ✅ Navbar tidak pecah
- ✅ Grid fitur/paket collapse jadi 1 kolom
- ✅ CTA button full-width di mobile

**CTA Dinamis:**
- ✅ Guest: "Masuk ke Platform" → /login
- ✅ Authenticated: "Masuk Dashboard" → /dashboard

**Anchors:**
- ✅ Navbar "Fitur" smooth scroll ke #features
- ✅ Navbar "Cara Kerja" scroll ke #how-it-works
- ✅ Navbar "Paket" scroll ke #plans
- ✅ Navbar "Keamanan" scroll ke #security

## Checklist Aturan Keras

- ✅ Baseline suite 202 passed → hasil 207 passed, 0 failed, 0 incomplete
- ✅ npm run build SEBELUM php artisan test
- ✅ Tidak mengubah logic auth, RLS, billing, entitlements, CBT
- ✅ Tidak ada hex hard-coded di Landing.vue
- ✅ Tidak pakai Tailwind `dark:` variant
- ✅ Lampirkan baris ringkasan ASLI: "Tests: 207 passed (870 assertions)"
- ✅ Route publik "/" render Inertia page Public/Landing
- ✅ Route "/login" tetap tersedia
- ✅ User authenticated boleh buka landing, CTA berubah
- ✅ Theme toggle localStorage "theme", default dark
- ✅ Grep "dark:" di Landing.vue = 0
- ✅ Grep hex di Landing.vue = 0
- ✅ Commit per sub-tugas

## Selesai
Tugas V2.3-B selesai. Landing page publik siap untuk demo mentor, investor, dan calon tenant.
