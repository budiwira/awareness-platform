# Security

## Overview

Platform dirancang dengan security-first approach: defense-in-depth, least privilege, dan fail-secure defaults.

## Authentication & Authorization

### Authentication
- **Provider**: Laravel Breeze (session-based)
- **Password Hashing**: bcrypt (cost factor 12)
- **Session Storage**: Database (encrypted cookies sebagai fallback)
- **Session Lifetime**: 120 menit idle timeout
- **Remember Me**: Optional, 2 minggu max

### Authorization Model
**Role-Based Access Control (RBAC)** dengan 3 roles:

1. **Super Admin**
   - Platform-wide access
   - Tenant management
   - Content library (global modules, quizzes, CTF)
   - Cross-tenant reporting
   - Plan & billing management
   - `tenant_id = NULL`

2. **Tenant Admin**
   - Tenant-scoped access
   - User management (within tenant)
   - Training assignment
   - Phishing campaign management
   - TTX simulation management
   - Tenant reporting & export
   - Billing & subscription (own tenant)

3. **User**
   - Personal data access
   - Training modules (assigned)
   - Quiz attempts
   - Case studies
   - CTF challenges
   - Personal dashboard & awareness score
   - Leaderboard (opt-in)

### Gate & Policy Enforcement

Setiap action protected dengan Laravel Gate atau Policy:

```php
// Controller
Gate::authorize('view', $moduleAssignment);

// Policy
public function view(User $user, ModuleAssignment $assignment): bool
{
    return $user->id === $assignment->user_id 
        || $user->isAdmin();
}
```

**Middleware stack**:
1. `auth` - Verifikasi authenticated
2. `SetTenantContext` - Set `app.current_tenant_id`
3. `CheckRole` - Verifikasi role untuk route group
4. `CheckFeatureEntitlement` - Verifikasi plan features

## Multi-Tenant Security

### Row-Level Security (RLS)

**Primary defense**: PostgreSQL RLS policies enforce isolasi di database level.

#### Policy Structure
```sql
CREATE POLICY tenant_isolation ON table_name
  USING (
    tenant_id = current_setting('app.current_tenant_id')::bigint
    OR current_setting('app.current_tenant_id', true) IS NULL
  );

ALTER TABLE table_name ENABLE ROW LEVEL SECURITY;
ALTER TABLE table_name FORCE ROW LEVEL SECURITY;
```

`FORCE` memastikan policy berlaku bahkan untuk table owner (super user bypass prevention).

#### Context Setting
Middleware `SetTenantContext` set context per request:

```php
// Tenant Admin / User
DB::statement("SET app.current_tenant_id = ?", [$user->tenant_id]);

// Super Admin
// NO context set (NULL = bypass RLS, access all tenants)
```

**Critical**: Context reset di connection pool. Laravel menggunakan persistent connections, jadi context di-set setiap request.

#### Tables with RLS Enabled
- `users`
- `module_assignments`
- `quiz_attempts`
- `case_participations`
- `ctf_solves`
- `ttx_scores`
- `phishing_campaigns`
- `phishing_targets`
- `badges` (user_badges junction)
- `notifications`

#### Tables WITHOUT RLS
- `tenants` (Super Admin only access via Gate)
- `plans` (public reference data)
- `training_modules` (global content library, filtered by application)
- `quizzes` (global content)
- `ctf_challenges` (global content)

**Rationale**: Content library shared across tenants; assignment tables (module_assignments) enforce tenant isolation.

### Application-Layer Defense

RLS adalah safety net. Application layer tetap enforce:

1. **Query Scopes**:
   ```php
   ModuleAssignment::where('tenant_id', auth()->user()->tenant_id)->get();
   ```

2. **Model Observers**:
   ```php
   // Auto-set tenant_id pada create
   static::creating(function ($model) {
       $model->tenant_id = auth()->user()->tenant_id;
   });
   ```

3. **Route Model Binding**:
   ```php
   Route::get('/assignments/{assignment}', ...)
       ->can('view', 'assignment');
   ```

**Defense-in-Depth**: Jika developer lupa filter `tenant_id`, RLS blocks unauthorized access.

### SQL Injection Prevention

1. **Eloquent ORM**: Parameter binding otomatis
2. **Query Builder**: Prepared statements
3. **Raw Queries**: `DB::statement("... WHERE id = ?", [$id])` (parameterized)
4. **Input Validation**: FormRequest memvalidasi sebelum query

**Forbidden**: String concatenation dalam SQL (`"WHERE id = " . $id`).

### Tenant Spillage Testing

Test suite memiliki `TenantIsolationTest.php`:
- User A tidak bisa akses data User B (beda tenant)
- Tenant Admin tidak bisa akses data tenant lain
- RLS blocks cross-tenant queries

```php
test('user cannot view another tenant assignment', function () {
    // Tenant A user tries to access Tenant B assignment
    $response = actingAs($tenantAUser)
        ->get(route('assignments.show', $tenantBAssignment));
    
    $response->assertNotFound(); // RLS blocks
});
```

## CBT (Computer-Based Test) Security

### Server-Side Randomization

**Threat**: Client-side shuffle dapat di-reverse via browser DevTools.

**Mitigation**:
1. Shuffle questions di server: `$quiz->questions->shuffle()`
2. Shuffle options per question di server
3. Simpan order di `quiz_attempts.question_order` (JSON array)
4. Simpan option orders di `quiz_attempts.option_orders` (JSON object)

Client receives pre-shuffled data, tidak bisa reconstruct original order.

### No Correct Answer Leakage

**Threat**: Client inspect network response untuk cari `correct_index`.

**Mitigation**:
- Quiz payload TIDAK contains `correct_index`, `is_correct`, atau flag apapun
- Options hanya `{ id, text }`
- Validation di server: compare `selected_option_id` dengan `questions.options[correct_index].id`

```php
// ❌ VULNERABLE
return [
    'questions' => $quiz->questions->map(fn($q) => [
        'options' => $q->options->map(fn($opt) => [
            'text' => $opt['text'],
            'correct' => $opt['correct'], // ❌ LEAKED
        ]),
    ]),
];

// ✅ SECURE
return [
    'questions' => $quiz->questions->map(fn($q) => [
        'options' => $shuffledOptions->map(fn($opt) => [
            'id' => $opt['id'],
            'text' => $opt['text'],
            // NO 'correct' field
        ]),
    ]),
];
```

### Deadline Validation

**Threat**: Client manipulate countdown timer untuk extend quiz time.

**Mitigation**:
- Deadline stored di `quiz_attempts.deadline` (server timestamp)
- Submit endpoint checks `now() > deadline` sebelum accept answers
- Client countdown adalah UX hint only, bukan enforcement

```php
if ($attempt->deadline && now()->isAfter($attempt->deadline)) {
    return response()->json(['message' => 'Deadline exceeded'], 403);
}
```

### Replay Attack Prevention

**Threat**: User submit multiple times untuk brute-force correct answers.

**Mitigation**:
- `quiz_attempts.status` transition: `in_progress` → `submitted`
- Submit endpoint checks `status === 'in_progress'` before accepting
- Idempotent: Duplicate submit returns existing score

```php
if ($attempt->status !== 'in_progress') {
    return response()->json(['message' => 'Already submitted'], 400);
}
```

### Review Mode Security

**Threat**: User inspect review page untuk learn answers, retake quiz.

**Mitigation**:
- Review only available setelah `status === 'submitted'`
- Review shows correct answers (educational purpose)
- User CANNOT retake quiz (one attempt per assignment)

**Rationale**: Post-submission review adalah learning opportunity, bukan security risk.

## Phishing Simulation Security

### Ethical Design: No Credential Capture

**Decision**: Phishing simulation untuk edukasi, bukan credential theft.

**Implementation**:
- Phishing link → `/phishing/trap/{token}` (GET only)
- Controller mark `clicked_at`, render teaching page
- Teaching page explain ciri-ciri phishing, TIDAK ada form
- TIDAK capture credentials, cookies, atau PII

**Legal Rationale**: Credential capture dapat melanggar unauthorized access laws (e.g., CFAA di US, UU ITE di Indonesia).

### Unique Token per Target

**Threat**: User share phishing link, multiple clicks counted.

**Mitigation**:
- Token: `Str::random(64)` per `PhishingTarget`
- Token tied to `user_id` + `campaign_id`
- Status transition: `sent` → `clicked` (one-time)
- Duplicate click (already `clicked`) does not re-increment metrics

```php
$target = PhishingTarget::where('token', $token)
    ->where('status', 'sent')
    ->first();

if (!$target) {
    abort(404); // Token invalid atau sudah digunakan
}
```

### Email Template Security

**Threat**: Template injection via user-controlled variables.

**Mitigation**:
- Blade escaping: `{{ $variable }}` auto-escaped
- Allowed variables: `{name}`, `{email}`, `{organization}` (pre-defined)
- Validation: Template tidak boleh contains `{%`, `{{`, `<script>`, `<iframe>`

```php
$template = Str::of($campaign->email_template)
    ->replace('{name}', e($target->user->name))
    ->replace('{email}', e($target->user->email));
```

### Auto-Remedial Assignment

**Threat**: Mass assignment vulnerability saat create ModuleAssignment.

**Mitigation**:
- `ModuleAssignment::create()` dengan explicit field list
- `tenant_id` set from campaign, bukan user input
- RLS enforces tenant boundary

```php
ModuleAssignment::create([
    'user_id' => $target->user_id,
    'tenant_id' => $target->campaign->tenant_id, // NOT from request
    'training_module_id' => $remedialModule->id,
    'status' => 'assigned',
]);
```

## Data Protection

### Password Storage
- **Algorithm**: bcrypt (Blowfish, adaptive hashing)
- **Cost Factor**: 12 (default Laravel, ~100ms per hash)
- **Salt**: Random, per-password (handled by bcrypt)

### Sensitive Data Encryption
- **Application Key**: `APP_KEY` (32-byte random, generated via `php artisan key:generate`)
- **Encryption**: AES-256-CBC via Laravel Crypt facade
- **Use Cases**: Session cookies, remember tokens

### CSRF Protection
- **Middleware**: `VerifyCsrfToken` (Laravel default)
- **Token**: Generated per session, embedded di forms via `@csrf` directive
- **Validation**: POST/PUT/PATCH/DELETE require valid token

### XSS Prevention
- **Vue Escaping**: `{{ variable }}` auto-escaped (mustache syntax)
- **Blade Escaping**: `{{ $variable }}` auto-escaped
- **CSP Header**: `Content-Security-Policy` via middleware (future enhancement)
- **Sanitization**: User input di-strip HTML tags via `strip_tags()` atau validation rule

### SQL Injection Prevention
- **Eloquent**: Automatic parameter binding
- **Query Builder**: Prepared statements
- **Raw Queries**: `DB::statement("... WHERE id = ?", [$id])`

**Code Review Rule**: No string concatenation dalam SQL.

## Input Validation

### FormRequest Validation

Setiap POST/PUT/PATCH endpoint menggunakan FormRequest:

```php
class StorePhishingCampaignRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'email_subject' => 'required|string|max:255',
            'email_template' => 'required|string',
            'target_user_ids' => 'required|array',
            'target_user_ids.*' => 'exists:users,id',
        ];
    }
}
```

**Benefits**:
- Type validation (string, integer, boolean)
- Format validation (email, url, date)
- Business rule validation (exists in DB, unique)
- Auto-reject invalid input (422 response)

### Server-Side Validation Only

Client-side validation adalah UX enhancement, BUKAN security control.

**Rule**: SEMUA validation di server, client validation optional.

## Secrets Management

### Environment Variables

Semua secrets di `.env`, TIDAK hardcoded:
- `APP_KEY`
- `DB_PASSWORD`, `DB_PASSWORD_OWNER`
- `MAIL_PASSWORD`
- `AWS_SECRET_ACCESS_KEY`
- `STRIPE_SECRET`

**Production Checklist**:
- `.env` file permission: `600` (owner read/write only)
- `.env` TIDAK di-commit ke Git (`.gitignore` enforce)
- Secrets rotation setiap 90 hari (manual procedure)

### Database Credentials

**Dual-role pattern**:
- Runtime: `DB_USERNAME` (app_user, DML only)
- Migration: `DB_USERNAME_OWNER` (owner_user, DDL + DML)

**Rationale**: Jika app compromised, attacker TIDAK dapat DROP tables (app_user tidak punya privilege).

### API Keys

Third-party API keys (future):
- Stored di `.env`, referenced via `config('services.provider.key')`
- TIDAK exposed di client-side code
- Rate limiting per key

## Rate Limiting

### Laravel Throttle Middleware

Protected endpoints:
- **Login**: 5 attempts per minute per IP
- **Quiz Submit**: 10 attempts per minute per user
- **API**: 60 requests per minute per user (future)

```php
Route::middleware(['throttle:5,1'])->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});
```

**Response**: 429 Too Many Requests dengan `Retry-After` header.

### DDoS Mitigation (Future)

Production setup (future):
- Cloudflare atau AWS WAF di depan aplikasi
- Challenge page untuk suspicious traffic
- Geographic blocking jika necessary

## Audit Trail

### Immutable Audit Log

`app/Support/Audit/Audit.php` provides append-only logging:

```php
Audit::log('user.login', [
    'user_id' => $user->id,
    'ip' => $request->ip(),
    'user_agent' => $request->userAgent(),
]);
```

**Storage**: PostgreSQL `audit_logs` table:
- `id` (primary key)
- `event` (string, e.g., `user.login`)
- `payload` (JSONB)
- `created_at` (immutable timestamp)

**No UPDATE or DELETE**: Table hanya INSERT, no foreign keys (prevent cascade delete).

### Audit Events

Critical events logged:
- User login/logout
- Role change
- Tenant creation/deletion
- Training assignment
- Quiz attempt submission
- Phishing campaign creation
- Phishing link click
- Badge awarded
- Billing status change

### Audit Review

Super Admin dapat query audit logs:
```sql
SELECT * FROM audit_logs
WHERE event = 'user.login'
  AND created_at > NOW() - INTERVAL '7 days'
ORDER BY created_at DESC;
```

**Retention**: Audit logs retained 1 year (production policy).

## Dependencies

### Composer Audit

Check PHP dependencies untuk known vulnerabilities:

```bash
composer audit
```

**CI Integration**: Runs di setiap PR, blocks merge jika vulnerable packages found.

### NPM Audit

Check JavaScript dependencies:

```bash
npm audit
```

**Production**: `npm audit --production` (ignore dev dependencies).

**Resolution**: Update packages atau apply patches via `npm audit fix`.

### Dependency Update Policy

- Security patches: Applied within 48 hours
- Minor updates: Reviewed monthly
- Major updates: Reviewed quarterly (with full regression test)

## CORS Configuration

### Current (Inertia.js)

Same-origin app, CORS TIDAK needed:
- API requests via Inertia (Laravel routes, bukan AJAX ke external domain)
- No `Access-Control-Allow-Origin` header required

### Future (RESTful API)

Jika expose RESTful API:
- `Access-Control-Allow-Origin`: Whitelist specific domains (NOT wildcard `*`)
- `Access-Control-Allow-Credentials`: `true` (untuk cookies)
- `Access-Control-Allow-Methods`: `GET, POST, PUT, DELETE`
- `Access-Control-Max-Age`: `86400` (cache preflight 24 hours)

Configuration via `config/cors.php`.

## Security Headers

### Implemented (Middleware)

`app/Http/Middleware/SecurityHeaders.php`:

```php
$response->headers->set('X-Frame-Options', 'SAMEORIGIN');
$response->headers->set('X-Content-Type-Options', 'nosniff');
$response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
$response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
```

### Future Enhancements

- **CSP (Content-Security-Policy)**: `default-src 'self'; script-src 'self' 'unsafe-inline'` (block inline scripts)
- **HSTS (Strict-Transport-Security)**: `max-age=31536000; includeSubDomains` (enforce HTTPS)

**Blocker**: CSP requires audit inline scripts (Vue inline event handlers); HSTS requires production HTTPS setup.

## Known Limitations

### No Two-Factor Authentication (2FA)
**Impact**: Account takeover risk jika password compromised.
**Mitigation**: Strong password policy (min 8 chars, complexity), rate limiting.
**Future**: Implement TOTP 2FA via Laravel Fortify.

### No Single Sign-On (SSO)
**Impact**: User manage separate credentials untuk platform.
**Mitigation**: Password reset via email.
**Future**: SAML 2.0 atau OAuth 2.0 SSO dengan enterprise IdP (Okta, Azure AD).

### No IP Whitelist
**Impact**: Login dari any IP allowed.
**Mitigation**: Audit logs track login IP.
**Future**: Tenant-level IP whitelist configuration.

### No API Rate Limiting (Granular)
**Impact**: Abuse via excessive requests.
**Mitigation**: Laravel throttle middleware (coarse-grained).
**Future**: Redis-based rate limiter dengan per-endpoint limits.

### No Penetration Testing
**Status**: Internal security review only, no third-party pentest.
**Future**: Annual pentest sebelum major releases.

## Incident Response Procedure

### Detection
- Monitor error logs (`storage/logs/laravel.log`) untuk anomalies
- Audit log review (weekly) untuk suspicious events
- User reports via support email

### Triage
1. Classify severity: Critical (data breach), High (unauthorized access), Medium (DoS), Low (XSS)
2. Assign incident response team (developer + security lead)
3. Create incident ticket

### Containment
- Critical: Take app offline, rollback to last known good state
- High: Revoke compromised credentials, force password reset
- Medium: Rate limit offending IPs
- Low: Apply patch, monitor

### Eradication
- Identify root cause (code review, log analysis)
- Apply fix (patch vulnerability)
- Deploy hotfix to production

### Recovery
- Restore service
- Verify fix (regression test)
- Monitor untuk recurring issues

### Post-Mortem
- Document incident (timeline, root cause, impact)
- Update runbook
- Share learnings dengan team

## Compliance

### GDPR Readiness

**Right to Access**: User dapat export personal data via dashboard (future).
**Right to Erasure**: Tenant Admin dapat delete users (soft delete + anonymization).
**Data Minimization**: Collect only necessary data (name, email, role).
**Consent**: Privacy policy displayed pada registration.

**Gap**: Data Processing Agreement (DPA) template (future).

### SOC 2 Readiness

**Access Control**: RBAC + RLS enforce least privilege.
**Audit Logging**: Immutable audit trail.
**Encryption**: TLS in transit (production), AES-256 at rest (cookies).
**Backup**: Database backup policy (documented di DEPLOYMENT.md).

**Gap**: Formal security policy document, vulnerability disclosure policy (future).

---

**Document Maintenance**: Review quarterly atau setelah security incident.
