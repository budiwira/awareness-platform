# Deployment

## Prinsip Least Privilege

- Koneksi aplikasi (`pgsql`) memakai role **app**: hanya DML,
  tidak bisa CREATE/ALTER/DROP.
- Koneksi migrasi (`pgsql_owner`) dipakai **sekali** saat deploy
  untuk `migrate`, lalu tidak dipakai runtime.
- RLS aktif di semua tabel data; verifikasi dengan
  `SELECT relname, relrowsecurity FROM pg_class WHERE relrowsecurity;`

## Checklist Produksi

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] HTTPS wajib (HSTS sudah dikirim middleware SecurityHeaders)
- [ ] Generate key baru: `php artisan key:generate`
- [ ] Konfigurasi 2 koneksi DB (app & owner)
- [ ] `php artisan migrate --database=pgsql_owner --force`
- [ ] `php artisan db:seed --class=PlanSeeder --force` (plan wajib ada)
- [ ] `npm run build`
- [ ] `php artisan config:cache && php artisan route:cache`
- [ ] Backup terjadwal: `pg_dump` harian (role owner)
- [ ] Seeder demo (`DemoSeeder`) **JANGAN** dijalankan di produksi

## Rollback

Migrasi memiliki `down()`; rollback hanya via role owner.