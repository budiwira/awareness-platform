# Backup & Restore Procedure — Awareness Platform

## Cakupan Backup (semua terenkripsi AES-256-CBC at-rest)
| Komponen | Metode | File |
|---|---|---|
| Database PostgreSQL | `pg_dump -Fc` sebagai owner, lalu encrypt | `db_*.dump.enc` |
| `.env` (credentials) | Copy + encrypt | `.env.enc` |
| `storage/app` (uploads) | ZIP + encrypt | `storage.zip.enc` |

Format enkripsi: `[IV 16 byte][ciphertext]`, key 32-byte hex dari `.env` (`BACKUP_ENCRYPTION_KEY`).
**Key escrow wajib di password manager** — tanpa key, backup tidak bisa dibuka meski file dicuri.

## Jadwal
- **Otomatis:** Scheduled Task `AwarenessPlatformBackup`, harian 02:00 (terverifikasi berjalan 2026-09-10)
- **Retensi:** 14 hari (auto-delete)
- **Restore drill:** wajib bulanan (`scripts\restore-drill.ps1`) — drill decrypt ke TEMP, bukan ke folder backup

## Restore Procedure (Disaster Recovery)
1. Identifikasi backup terakhir: `Get-ChildItem C:\backups\awareness -Directory | Sort Name -Desc`
2. Decrypt: `powershell -File scripts\decrypt-backup.ps1 -BackupDir <dir>` ? hasil ke `<dir>\decrypted\`
   (Untuk drill otomatis, `restore-drill.ps1` decrypt ke TEMP dan hapus setelah selesai)
3. Verifikasi integritas: `pg_restore --list <decrypted dump>` (harus exit 0)
4. Buat database target: `CREATE DATABASE awareness_restored;`
5. Restore: `pg_restore -h <host> -U <owner> -d awareness_restored --no-owner --no-privileges <dump>`
6. Verifikasi row counts vs sumber
7. Arahkan `.env` ke database baru, `php artisan migrate --force` bila perlu
8. Smoke test: login + 1 halaman tenant + 1 halaman user
9. Ekstrak `storage.zip`, gunakan `.env` dari arsip bila yang aktif hilang
10. **HAPUS folder `decrypted\` setelah selesai** — plaintext tidak boleh menetap di disk

## Known Gaps (Hardening Checklist Production)
- [x] **Enkripsi at-rest** dump + .env (AES-256-CBC, selesai 2026-09-10)
- [x] **Rotasi password postgres superuser** (B34, selesai 2026-09-09)
- [ ] **Offsite copy** (lokasi saat ini = satu titik kegagalan)
- [x] **Role dedicated `awareness_backup`** (CREATEDB + BYPASSRLS + SELECT-only, selesai 2026-09-10) (CREATEDB + BYPASSRLS, NOLOGIN interaktif) — dev masih pakai owner
- [x] **Alerting** (ALERTS.log + toast on failure, selesai 2026-09-10) kalau task harian gagal (task terbukti jalan, tapi kegagalan masih silent)

## Catatan Keamanan
- Dump berisi SELURUH data lintas tenant ? data paling sensitif
- Folder backup di-lock ACL: hanya user pemilik
- Backup plaintext pra-2026-09-10 sudah dihapus (disiplin: plaintext tidak boleh menetap)
- Jangan pernah commit dump/.env/backups/key ke git