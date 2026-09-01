# Cyber Security Awareness Platform

Platform pelatihan & pengukuran kesadaran keamanan siber untuk organisasi — multi-tenant, terukur, dan aman secara desain.

## Overview

Platform komprehensif untuk melatih, menguji, dan mengukur tingkat kesadaran keamanan siber karyawan organisasi. Dirancang dengan isolasi multi-tenant penuh menggunakan PostgreSQL Row-Level Security (RLS), memastikan data antar organisasi terpisah di level database.

## Features

### Multi-Tenant RLS
- Isolasi data penuh via PostgreSQL Row-Level Security (bukan hanya filter aplikasi)
- Setiap tenant memiliki akses eksklusif ke data mereka sendiri
- Defense-in-depth: RLS + application-layer authorization

### CBT (Computer-Based Test) Anti-Cheat
- Soal dan opsi jawaban di-shuffle server-side untuk setiap attempt
- `correct_index` tidak pernah dikirim ke client
- Validasi deadline dan session server-side
- Review attempt menampilkan urutan asli user (bukan shuffled)

### Phishing Simulation
- Campaign dengan email template dan target list
- Link tracking dengan token unik
- Auto-remedial: assignment training module otomatis setelah click
- Teaching page (bukan credential capture) — ethical design
- Dashboard tracking: sent, clicked, reported

### Gamifikasi
- Awareness Score explainable (6 sinyal: completion, quiz, case, CTF, TTX, phishing awareness)
- Badge system dengan achievement tracking
- Leaderboard global tenant dengan opt-in privacy
- Point system dan milestone rewards

### Analytics & Reporting
- Dashboard per tenant: completion rate, quiz performance, phishing click rate
- Per-user progress tracking dan score breakdown
- Export CSV untuk compliance reporting
- Platform-wide analytics (Super Admin)

### Astra Theme
- Dark mode dengan CSS variables (theme switching via localStorage)
- Modern UI: teal primary (#0f766e), amber accent
- Typography: Plus Jakarta Sans (display) + Inter (body)
- Accessible contrast (WCAG AA)
- Motion: prefers-reduced-motion support

### Content Library
- Training modules dengan rich content
- Quiz dengan multiple choice, randomization, dan passing threshold
- Case study interaktif
- CTF challenges
- Tabletop Exercise (TTX) dengan 4 fase simulasi

### Role-Based Access Control
- **Super Admin**: Platform management, tenant creation, content library, cross-tenant reports
- **Tenant Admin**: User management, training assignment, campaign management, tenant reports, billing
- **User**: Training access, quiz, case studies, CTF, personal dashboard, awareness score

## Tech Stack

- **Backend**: Laravel 12 (PHP 8.2+)
- **Database**: PostgreSQL 15+ dengan Row-Level Security (RLS)
- **Frontend**: Inertia.js + Vue 3 (Composition API) + Tailwind CSS
- **Build**: Vite
- **Testing**: Pest (252 tests, feature + unit)
- **Static Analysis**: Larastan Level 5

## Architecture Summary

### Multi-Tenant Isolation
Platform menggunakan **shared database with RLS** approach:
- Satu database untuk semua tenant
- RLS policies enforce isolasi di level PostgreSQL
- Application layer menggunakan `tenant_id` context via `SET app.current_tenant_id`
- Dual database connection: `pgsql` (app role, DML only) dan `pgsql_owner` (migration role)

### Scoring System
6-signal weighted average:
- Training completion (20%)
- Quiz performance (20%)
- Case study performance (15%)
- CTF engagement (15%)
- TTX performance (15%)
- Phishing awareness (15%)

Entitlement-aware: sinyal dari fitur yang tidak ter-entitle di-exclude dan bobot di-renormalisasi.

### Theme System
CSS variables untuk theming, dikelola via `resources/js/Composables/useTheme.js`:
- Preference disimpan di localStorage
- Default dark mode (Astra theme)
- Transisi smooth via CSS transitions

### Security by Design
- RBAC via Laravel Gates & Policies
- RLS untuk tenant isolation
- CSRF protection (Laravel default)
- XSS prevention (Vue escaping, CSP headers)
- SQL injection prevention (Eloquent, prepared statements)
- No hardcoded secrets (environment variables)
- Audit trail immutable

Lihat [SECURITY.md](SECURITY.md) untuk detail lengkap.

## Setup Instructions (Local Development)

### Prerequisites
- PHP 8.2+
- Composer 2.x
- Node.js 18+
- PostgreSQL 15+
- Redis (optional, untuk queue/cache)

### Installation

1. Clone repository:
   ```bash
   git clone <repository-url>
   cd awareness-lab
   ```

2. Install dependencies:
   ```bash
   composer install
   npm install
   ```

3. Setup environment:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Configure database:
   Edit `.env` dan isi kredensial database:
   ```
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=awareness_platform
   DB_USERNAME=app_user
   DB_PASSWORD=<app_password>

   DB_CONNECTION_OWNER=pgsql_owner
   DB_HOST_OWNER=127.0.0.1
   DB_PORT_OWNER=5432
   DB_DATABASE_OWNER=awareness_platform
   DB_USERNAME_OWNER=owner_user
   DB_PASSWORD_OWNER=<owner_password>
   ```

   **Penting**: Gunakan dua role PostgreSQL:
   - `app_user`: DML only (SELECT, INSERT, UPDATE, DELETE)
   - `owner_user`: DDL + DML (CREATE, ALTER, DROP, dll)

5. Run migrations:
   ```bash
   php artisan migrate --database=pgsql_owner
   ```

6. Seed database:
   ```bash
   php artisan db:seed
   ```

7. Build frontend:
   ```bash
   npm run build
   ```

8. Run development server:
   ```bash
   php artisan serve
   ```

   Access platform di `http://localhost:8000`

### Development Workflow

- Frontend dev (hot reload):
  ```bash
  npm run dev
  ```

- Run tests:
  ```bash
  php artisan test
  ```

- Static analysis:
  ```bash
  ./vendor/bin/phpstan analyse
  ```

- Code formatting:
  ```bash
  ./vendor/bin/pint
  ```

## Default Accounts (After Seed)

### Super Admin
- Email: `superadmin@platform.local`
- Password: `password`
- Role: Super Admin (platform-wide access)

### Tenant Admin (Acme Corporation)
- Email: `admin@acme.local`
- Password: `password`
- Tenant: Acme Corporation
- Role: Tenant Admin

### User (Acme Corporation)
- Email: `user@acme.local`
- Password: `password`
- Tenant: Acme Corporation
- Role: User

### Tenant Admin (Beta Nusantara)
- Email: `admin@beta.local`
- Password: `password`
- Tenant: Beta Nusantara
- Role: Tenant Admin

**PENTING**: Ubah semua password default sebelum deploy ke production.

## Testing Instructions

### Run All Tests
```bash
php artisan test
```

Expected output:
```
Tests:  252 passed (1133 assertions)
```

### Run Specific Test Suite
```bash
php artisan test --filter=QuizTest
php artisan test tests/Feature/TenantIsolationTest.php
```

### Coverage Report
```bash
php artisan test --coverage --min=80
```

## Static Analysis Instructions

### Run Larastan (PHPStan for Laravel)
```bash
./vendor/bin/phpstan analyse
```

Configuration: `phpstan.neon` (Level 5)

### Run Pint (Code Style)
```bash
./vendor/bin/pint
```

Configuration: `pint.json` (Laravel preset)

## Security Summary

- **Authentication**: Laravel Breeze (session-based)
- **Authorization**: Role-based (Super Admin, Tenant Admin, User) + RLS
- **Multi-Tenant Isolation**: PostgreSQL RLS policies + application-layer gates
- **Data Protection**: bcrypt (passwords), CSRF tokens, XSS escaping, SQL injection prevention
- **CBT Security**: Server-side randomization, no `correct_index` to client
- **Phishing Simulation**: Ethical design (teaching page only, no credential capture)
- **Audit Trail**: Immutable audit log (DB-level)
- **Dependency Scanning**: `composer audit`, `npm audit`
- **Security Headers**: X-Frame-Options, X-Content-Type-Options, CSP

Lihat [SECURITY.md](SECURITY.md) untuk threat model dan mitigations lengkap.

## Quality Assurance

- **Test Suite**: 252 Pest tests (feature + unit), 1133 assertions
- **Static Analysis**: Larastan Level 5 (strict types, no mixed)
- **Code Style**: Laravel Pint (PSR-12)
- **Security Audit**: `composer audit` clean, `npm audit` clean
- **Database Integrity**: RLS policies verified, foreign keys enforced
- **Browser Compatibility**: Modern browsers (Chrome, Firefox, Safari, Edge)

## Documentation

- [ARCHITECTURE.md](ARCHITECTURE.md) — System design, trade-offs, scoring logic
- [SECURITY.md](SECURITY.md) — Threat model, mitigations, compliance
- [DEPLOYMENT.md](DEPLOYMENT.md) — Production setup, server config, monitoring

## Contributing

1. Fork repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'feat: add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open Pull Request

### Contribution Guidelines
- Write tests for new features
- Maintain Larastan Level 5 compliance
- Follow Laravel Pint code style
- Update documentation for user-facing changes
- No breaking changes to RLS policies without review

## License

This project is proprietary. All rights reserved.

## Support

For questions or issues:
- Email: support@platform.local
- Documentation: https://docs.platform.local
- Issue Tracker: https://github.com/organization/awareness-lab/issues
