# User Acceptance Test (UAT) — Cyber Security Awareness Platform

**Tanggal:** ____________  **Penguji:** ____________  **Versi:** task-15-done

## Persiapan
1. `php artisan migrate:fresh --database=pgsql_owner --seed`
2. `npm run build`
3. `php artisan serve`
4. Akun uji (password semua: `password`):

| Role | Email |
|---|---|
| Super Admin | superadmin@platform.local |
| Tenant Admin (Acme) | admin@acme.local |
| Tenant Admin (Beta) | admin@beta.local |
| User skor tinggi | budi@acme.local |
| User skor rendah | andi@acme.local |
| User Beta | user@beta.local |

## Cara mengisi
Kolom Hasil: **PASS / FAIL**. Jika FAIL, catat bug di tabel Bug Log.

---

## A. Autentikasi & RBAC
| ID | Skenario | Ekspektasi | Hasil |
|---|---|---|---|
| A1 | Login password salah | Error, tidak masuk | |
| A2 | Login superadmin | Platform Dashboard | |
| A3 | Login tenant admin | Tenant Dashboard | |
| A4 | Login user | My Dashboard | |
| A5 | User buka /platform/tenants | 403 | |
| A6 | User buka /tenant/users | 403 | |

## B. Super Admin
| ID | Skenario | Ekspektasi | Hasil |
|---|---|---|---|
| B1 | Buat tenant baru | Muncul di daftar + audit log | |
| B2 | Buat training module | Muncul di Content Library | |
| B3 | Buat quiz + pertanyaan | Muncul, bisa dibuka | |
| B4 | Buat case study + scene | Muncul dengan opsi kualitas | |
| B5 | Buat CTF challenge | Flag TIDAK terlihat di halaman | |
| B6 | Platform Reports | Acme & Beta tampil + rata-rata awareness | |
| B7 | Plans & Billing | 4 plan tampil; bisa buat plan baru | |

## C. Tenant Admin
| ID | Skenario | Ekspektasi | Hasil |
|---|---|---|---|
| C1 | Buat user baru | Muncul di daftar Users | |
| C2 | Import CSV user | User terbuat; formula CSV dinetralkan | |
| C3 | Tugaskan modul ke user | User menerima notifikasi (bell) | |
| C4 | TTX: buat playbook & runbook | Muncul di halaman TTX | |
| C5 | TTX: buat exercise + tim + anggota | Stepper & kartu tim tampil | |
| C6 | TTX: advance fase + tambah inject | Fase berpindah; inject bertambah | |
| C7 | TTX: evaluasi (skor + AAR) | Exercise completed; skor tersimpan | |
| C8 | Reports + Download CSV | File terbuka benar di Excel (UTF-8) | |
| C9 | Billing: ganti plan | Plan aktif berubah | |
| C10 | Billing: downgrade di bawah jumlah user | Ditolak dengan pesan error | |

## D. User
| ID | Skenario | Ekspektasi | Hasil |
|---|---|---|---|
| D1 | Lihat training & tandai selesai | Status berubah | |
| D2 | Kerjakan quiz lulus | Modul completed + skor tercatat | |
| D3 | Kerjakan case study | Skor + halaman hasil tampil | |
| D4 | CTF flag salah | Error "Flag salah" | |
| D5 | CTF flag benar | Solved + poin bertambah | |
| D6 | My Score | Ring + breakdown 5 komponen tampil | |
| D7 | Notifikasi: tandai semua dibaca | Bell jadi 0 | |

## E. Keamanan & Multi-Tenancy
| ID | Skenario | Ekspektasi | Hasil |
|---|---|---|---|
| E1 | Admin Beta buka data Acme | Tidak terlihat (isolasi tenant) | |
| D2 | User buka assignment user lain (URL langsung) | 403/404 | |
| E3 | psql: role app tanpa context | 0 baris di tabel tenant | |
| E4 | Audit log: coba update sebagai app | Ditolak (immutability) | |

---

## Bug Log
| ID Bug | Terkait | Deskripsi | Status (Open/Fixed) |
|---|---|---|---|
| | | | |

## Sign-Off
| Nama | Peran | Tanda Tangan | Tanggal |
|---|---|---|---|
| | Mentor | | |
| | Pengembang | | |