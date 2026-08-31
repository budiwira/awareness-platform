# AGENTS.md — House Rules

## Konteks
Laravel 12 + PostgreSQL (RLS) + Inertia/Vue 3 + Tailwind.
Multi-tenant; test suite Pest wajib hijau.

## WAJIB
- `npm run build` setelah mengubah Vue/CSS.
- `php artisan test` hijau sebelum menyatakan selesai.
- Commit dengan pesan `lab: <deskripsi>`.

## DILARANG KERAS
- Menjalankan `migrate`, `migrate:fresh`, `db:seed`, atau perintah DB destruktif.
- Membaca/menyalin/mengirim isi `.env` atau kredensial apa pun.
- Mengubah `app/Models`, migration, policy RLS, atau test.
- Menghapus fitur/route yang ada.

## Gaya UI
Brand teal (#0f766e) + aksen amber; font Plus Jakarta Sans (display) + Inter.
Hindari indigo default dan emoji sebagai ikon.