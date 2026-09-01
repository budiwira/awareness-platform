# Architecture

## Overview

Platform awareness keamanan siber dengan isolasi multi-tenant penuh, scoring explainable, dan security-first design.

## Multi-Tenant Isolation

### Design Decision: Shared Database + RLS

Platform menggunakan **shared database with Row-Level Security (RLS)** approach, bukan database-per-tenant atau schema-per-tenant.

**Keputusan ini diambil karena**:
- Skalabilitas: Mengelola ratusan database/schema terpisah kompleks dan mahal
- Maintenance: Satu migration untuk semua tenant, bukan ratusan
- Cost: PostgreSQL connection pooling lebih efisien untuk shared database
- Query Performance: Agregasi cross-tenant (platform analytics) lebih cepat
- Backup/Restore: Satu backup untuk semua tenant, konsisten

**Trade-offs yang diterima**:
- RLS overhead: ~5-10% query performance penalty (acceptable untuk use case ini)
- Complexity: Developer harus aware tentang `tenant_id` context setting
- Migration Risk: Schema change affect semua tenant sekaligus (mitigated dengan thorough testing)

### RLS Implementation

#### Database Roles
Dua role PostgreSQL dengan privilege berbeda:

1. **app_user** (runtime):
   - DML only: SELECT, INSERT, UPDATE, DELETE
   - TIDAK bisa CREATE, ALTER, DROP
   - Digunakan oleh aplikasi Laravel (koneksi `pgsql`)

2. **owner_user** (deployment):
   - Full DDL + DML
   - Digunakan HANYA untuk `php artisan migrate`
   - TIDAK digunakan saat runtime

#### RLS Policies
Setiap tabel data memiliki policy:

```sql
CREATE POLICY tenant_isolation ON users
  USING (tenant_id = current_setting('app.current_tenant_id')::bigint);

ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE users FORCE ROW LEVEL SECURITY;
```

`FORCE` memastikan RLS berlaku bahkan untuk table owner.

#### Context Setting
Setiap request mengeset `app.current_tenant_id`:

```php
// app/Http/Middleware/SetTenantContext.php
DB::statement("SET app.current_tenant_id = ?", [$user->tenant_id]);
```

Middleware ini diapply di route groups setelah authentication.

#### Super Admin Exception
Super Admin (tenant_id = NULL) tidak terikat satu tenant:
- Policy: `USING (tenant_id = ... OR current_setting('app.current_tenant_id', true) IS NULL)`
- Context NOT di-set untuk Super Admin (boleh akses cross-tenant)

### Application-Layer Defense-in-Depth

RLS adalah safety net, bukan satu-satunya defense. Application layer tetap enforce:
- Gates: `Gate::authorize('view', $model)` check tenant match
- Policies: `UserPolicy::view()` verifikasi tenant_id
- Query scopes: `->where('tenant_id', auth()->user()->tenant_id)`

**Principle**: RLS prevents SQL injection bypasses; application layer prevents logic bugs.

## Scoring System

### 6-Signal Weighted Average

Awareness Score dihitung dari 6 sinyal independen:

| Signal | Weight | Metric |
|--------|--------|--------|
| Training Completion | 20% | (Completed / Total Assigned) × 100 |
| Quiz Performance | 20% | Average of best scores per quiz |
| Case Study Performance | 15% | Average score of completed cases |
| CTF Engagement | 15% | (Points Earned / Total Points) × 100 |
| TTX Performance | 15% | Average TTX scores |
| Phishing Awareness | 15% | 100 - (Click Rate × 0.5) |

### Explainability

Setiap user melihat breakdown per signal:
- Signal score (0-100)
- Normalized weight (setelah entitlement filter)
- Locked status (jika fitur tidak ter-entitle)

Contoh:
```
Overall: 78

Breakdown:
- Training Completion: 85 (25% weight)
- Quiz Performance: 72 (25%)
- Case Study: locked (0%, not in plan)
- CTF: 90 (25%)
- TTX: locked (0%, not in plan)
- Phishing Awareness: 60 (25%)
```

### Entitlement-Aware Scoring

Jika tenant plan tidak include fitur (e.g., CTF), signal tersebut:
- Di-mark `locked: true`
- TIDAK berkontribusi ke overall score
- Weight di-renormalisasi untuk sinyal yang ter-entitle

**Rationale**: User tidak dihukum karena tenant tidak subscribe fitur tertentu.

### Implementation

`app/Support/Scoring/AwarenessScore.php` adalah single source of truth:
- Pure function: deterministic given inputs
- Testable: unit test per signal
- Auditable: formula didokumentasikan

## Theme System

### CSS Variables + localStorage

Theme switching tanpa backend state:

1. User pilih theme via UI
2. `useTheme.js` composable simpan ke `localStorage.getItem('theme')`
3. Root element `<html>` dapat attribute `data-theme="astra-dark"`
4. CSS variables di `resources/css/themes/*.css` override default

### Astra Theme Palette

**Primary (Teal)**:
- `--color-primary-50` hingga `--color-primary-950`
- Base: `#0f766e` (teal-700)

**Accent (Amber)**:
- `--color-accent-50` hingga `--color-accent-950`
- Base: `#f59e0b` (amber-500)

**Typography**:
- Display: Plus Jakarta Sans (hero, headings)
- Body: Inter (paragraphs, UI text)

### Dark Mode Default

Platform default ke dark mode (Astra):
- Better for prolonged screen time (awareness training sessions)
- Professional look for enterprise
- Reduced eye strain

User dapat override ke light mode via settings.

## CBT (Computer-Based Test) Anti-Cheat

### Server-Side Randomization

**Problem**: Client-side randomization dapat di-bypass via browser DevTools.

**Solution**: Randomization di server, simpan di `quiz_attempts` table:

```php
// Controller
$shuffledQuestions = $quiz->questions->shuffle();
$questionsData = $shuffledQuestions->map(function ($q) {
    $shuffledOptions = collect($q->options)->shuffle();
    return [
        'id' => $q->id,
        'text' => $q->question_text,
        'options' => $shuffledOptions->map(fn($opt) => [
            'id' => $opt['id'],
            'text' => $opt['text'],
            // NO 'correct' flag, NO 'correct_index'
        ]),
    ];
});

QuizAttempt::create([
    'question_order' => $shuffledQuestions->pluck('id'),
    'option_orders' => $perQuestionOptionOrder, // JSON
]);
```

### No Correct Answer Leakage

Client TIDAK pernah menerima:
- `correct_index` atau `is_correct` flag
- Correct answer text di-mark dengan attribute
- Any hint tentang jawaban benar

**Validation di server**:
```php
// Submit endpoint
foreach ($answers as $qId => $selectedOptionId) {
    $question = Question::find($qId);
    $isCorrect = $question->options[$question->correct_index]['id'] === $selectedOptionId;
    // ...
}
```

### Deadline Validation

Deadline check di server, bukan client:
```php
if ($attempt->deadline && now()->isAfter($attempt->deadline)) {
    abort(403, 'Quiz deadline exceeded');
}
```

Client countdown adalah UX only, bukan enforcement.

### Review Mode

Setelah submit, user dapat review attempt:
- Melihat jawaban mereka vs jawaban benar
- Soal dan opsi ditampilkan dalam **urutan original user** (dari `question_order` dan `option_orders`)
- Bukan urutan shuffled baru atau urutan database

**Rationale**: User ingin review jawaban sesuai yang mereka lihat saat test, bukan urutan acak baru.

## Phishing Simulation Ethics

### Teaching Page, Not Credential Capture

**Design decision**: Phishing simulation untuk edukasi, bukan social engineering attack.

**Implementation**:
- Link phishing mengarah ke `/phishing/trap/{token}`
- Controller mark `clicked_at`, render teaching page
- Teaching page explain: "Ini simulasi, ciri-ciri phishing, cara menghindari"
- TIDAK ada form login, TIDAK capture credentials

**Rationale**:
- Legal risk: Credential capture bisa dianggap unauthorized access
- Ethical: Karyawan tidak perlu merasa "ditipu" secara traumatic
- Effective: Teaching page lebih educational daripada shame

### Auto-Remedial Assignment

Setelah user click phishing link:
- System auto-assign training module "Phishing Awareness"
- User dapat notifikasi untuk complete training
- Remedial tracked di dashboard tenant admin

**Rationale**: Just-in-time learning lebih effective daripada punishment.

### Unique Token

Setiap phishing target dapat token unik:
```php
'token' => Str::random(64)
```

Token hanya valid untuk satu user, satu campaign. Prevents:
- Link sharing (token tied to specific user)
- Replay attacks (token diburn setelah click)

## Entitlements (Plan-Based Feature Gating)

### Plan → Features Mapping

`plans` table memiliki JSON column `features`:
```json
{
  "training": true,
  "phishing": true,
  "case_studies": false,
  "ctf": false,
  "ttx": false
}
```

### Enforcement Layers

1. **UI**: Menu item hidden jika feature locked
2. **Route**: Middleware `CheckFeatureEntitlement` abort 403
3. **Scoring**: Locked signals excluded dari overall score

### Upgrade Prompts

User melihat locked features dengan badge "Upgrade Required":
- Call-to-action ke billing page
- Clear value proposition per feature

## Testing Strategy

### Pest Feature Tests

252 tests, organized by feature:
- `TenantIsolationTest`: RLS enforcement, cross-tenant access denied
- `QuizTest`: CBT anti-cheat, randomization, no leakage
- `PhishingTest`: Token uniqueness, teaching page, auto-remedial
- `ScoringTest`: 6-signal calculation, entitlement filtering
- `AuthorizationTest`: Gate/policy per role per action

### Test Database

Tests menggunakan in-memory SQLite dengan RLS disabled (SQLite tidak support RLS). RLS tested via PostgreSQL integration tests di CI/CD.

**Rationale**: Fast local tests (SQLite) + comprehensive CI tests (PostgreSQL).

### Coverage Target

80% line coverage, enforced via CI:
```bash
php artisan test --coverage --min=80
```

Critical paths (authentication, authorization, scoring, RLS) memiliki 100% coverage.

## Static Analysis (Larastan Level 5)

### Strict Types Enforcement

`phpstan.neon` configured untuk:
- Level 5 (strict)
- No `@phpstan-ignore` without justification
- Generic type hints (Collection<User>, array<string, int>)
- Enum exhaustiveness check

### Common Issues Caught

- Null pointer dereference
- Type mismatches (int vs string)
- Missing model relation types
- Unused variables

### CI Integration

Larastan runs di pre-commit hook dan CI pipeline:
```bash
./vendor/bin/phpstan analyse
```

Commit blocked jika Larastan fails.

## Frontend Architecture (Inertia.js)

### SSR-Style without SSR Complexity

Inertia.js provides:
- Backend routes render Vue components directly
- Props passed from controller to component
- No API layer needed for simple CRUD
- Progressive enhancement (falls back to traditional request if JS fails)

**Trade-off accepted**: No true SSR (SEO not critical for authenticated app).

### Vue 3 Composition API

Semua components menggunakan Composition API (bukan Options API):
- `<script setup>` syntax
- Composables untuk reusable logic (`useTheme.js`, `useFlash.js`)
- Reactive state via `ref()` dan `reactive()`

### State Management

Tidak menggunakan Vuex/Pinia. State management via:
- Inertia props (server-rendered state)
- localStorage (theme, UI preferences)
- Composables (shared reactive state)

**Rationale**: Inertia props sudah provide server state, tidak perlu Redux-style store.

### Component Structure

```
resources/js/
├── Components/
│   ├── Ui/              # Reusable UI (Button, Card, Badge)
│   └── Feature/         # Feature-specific (QuizCard, PhishingTable)
├── Layouts/             # App shell (AppLayout, GuestLayout)
├── Pages/               # Routable pages (Dashboard.vue, Quiz/Index.vue)
└── Composables/         # Shared logic (useTheme, useFlash)
```

## Future Considerations

### Mobile App

**If needed**:
- Build Laravel Sanctum API (RESTful)
- React Native or Flutter app
- RLS tetap enforce di backend, mobile app consume API

**Not implemented now**: Browser-based responsive UI cukup untuk MVP.

### API v2 (Headless)

**If needed**:
- API-first architecture dengan OpenAPI spec
- Rate limiting per tenant
- Webhook support untuk integrations

**Not implemented now**: Inertia.js cukup untuk monolithic app.

### Horizontal Scaling

**Current bottleneck**: PostgreSQL write throughput (single primary).

**Future options**:
- Read replicas untuk analytics queries
- Connection pooling via PgBouncer
- Partitioning tables per tenant (trade-off: migration complexity)

**When**: Setelah 100K+ users atau tenant admin requests timeout.

### AI-Powered Features

**Potential**:
- Personalized training recommendations (ML model based on awareness score)
- Phishing email generation (LLM-generated realistic templates)
- Automated case study scoring (NLP rubric evaluation)

**Blocker**: Data volume not yet sufficient untuk training model. Revisit setelah 1 year data collected.

---

**Document Status**: Living document, updated per architectural decision record (ADR).
