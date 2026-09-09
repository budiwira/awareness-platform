# Backup & Restore Procedure — Awareness Platform

## Cakupan Backup
| Komponen | Metode | Lokasi |
|---|---|---|
| Database PostgreSQL | `pg_dump -Fc` sebagai owner (bypass RLS sah) | `C:\backups\awareness\<timestamp>\db_*.dump` |
| `.env` (credentials) | Copy langsung | `...\..env` |
| `storage/app` (uploads) | ZIP | `...\storage.zip` |

## Jadwal
- **Otomatis:** Scheduled Task `AwarenessPlatformBackup`, harian 02:00
- **Retensi:** 14 hari (auto-delete)
- **Restore drill:** wajib bulanan (`scripts\restore-drill.ps1`) — backup yang tidak pernah di-restore adalah harapan, bukan backup

## Restore Procedure (Disaster Recovery)
1. Identifikasi dump terakhir yang valid: `Get-ChildItem C:\backups\awareness -Recurse -Filter *.dump | Sort LastWriteTime -Desc`
2. Verifikasi integritas: `pg_restore --list <dump>` (harus exit 0)
3. Buat database target: `CREATE DATABASE awareness_restored;`
4. Restore: `pg_restore -h <host> -U <owner> -d awareness_restored --no-owner --no-privileges <dump>`
5. Verifikasi row counts vs sumber (pakai `scripts\restore-drill.ps1` dengan parameter `-TestDb awareness_restored`)
6. Arahkan `.env` DB_DATABASE ke database baru, jalankan `php artisan migrate --force` bila versi schema perlu catch-up
7. Smoke test: login + 1 halaman tenant + 1 halaman user
8. Ekstrak `storage.zip` ke `storage/app`, gunakan `.env` dari arsip bila yang aktif hilang

## Known Gaps (Hardening Checklist Production)
- [ ] **Enkripsi at-rest** dump + .env (ACL folder sudah membatasi, enkripsi belum)
- [ ] **Offsite copy** (lokasi saat ini = satu titik kegagalan)
- [ ] **Role dedicated `awareness_backup`** (CREATEDB + BYPASSRLS, NOLOGIN interaktif) — dev masih pakai owner
- [x] **Rotasi password postgres superuser** (lihat BUG-LOG B34: `budi1234` lemah)
- [ ] **Alerting** kalau task harian gagal (cek BACKUP-LOG.md atau Event Viewer)

## Catatan Keamanan
- Dump berisi SELURUH data lintas tenant ? perlakukan sebagai data paling sensitif
- Folder backup di-lock ACL: hanya user pemilik yang boleh akses
- Jangan pernah commit dump/.env/backups ke git (sudah di-.gitignore)