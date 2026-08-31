# Cyber Security Awareness Platform

Platform pelatihan & pengukuran kesadaran keamanan siber untuk organisasi —
multi-tenant, terukur, dan aman secara desain.

## Fitur

**Super Admin (Platform)**
- Manajemen tenant, content library (modul training), quiz, case study, CTF
- Laporan lintas tenant, manajemen plan & billing

**Tenant Admin (Organisasi)**
- Manajemen user (termasuk import CSV), penugasan training
- Tabletop Exercise (TTX): playbook, runbook, simulasi 4 fase, tim, injects, AAR
- Laporan per anggota + ekspor CSV, billing & langganan

**User (Anggota)**
- Training + quiz, case study interaktif, CTF
- Awareness Score (explainable, 5 komponen), notifikasi

## Tech Stack

- Laravel 12 (PHP 8.2+) · PostgreSQL 15+ dengan **Row-Level Security**
- Inertia.js + Vue 3 + Tailwind CSS
- Pest (testing) · Vite

## Keamanan (by design)

- Multi-tenancy isolasi penuh via RLS (bukan hanya filter aplikasi)
- RBAC 3 role + policy per aksi
- Audit log immutable (DB-level)
- Flag CTF & kunci jawaban quiz tidak pernah dikirim ke client
- Security headers, CSRF, sanitasi CSV injection

## Instalasi

1. `composer install` && `npm install`
2. Salin `.env.example` → `.env`, isi kredensial DB (dua koneksi: `pgsql` role app, `pgsql_owner` role owner)
3. `php artisan key:generate`
4. `php artisan migrate --database=pgsql_owner`
5. `php artisan db:seed` (mengisi tenant, user, plan, konten demo)
6. `npm run build`
7. `php artisan serve`

## Akun Demo (