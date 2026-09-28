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
| C4 | TTX: pilih skenario dan Playbook untuk sesi | Sesi baru membuka Prepare dengan snapshot konteks | |
| C5 | TTX: siapkan tim, peserta, Primary/Support | Readiness menunjukkan cakupan fase relevan; hanya pembuat sesi dapat mengubah draft | |
| C6 | TTX: tandai Ready, mulai, lalu lanjutkan inject | Facilitator Console menampilkan status tim; inject berikutnya dirilis, respons sebelumnya terkunci | |
| C7 | TTX: isi Debrief dan AAR lalu finalisasi | Capability relevan memiliki rating, Evidence, Finding; Result baca-saja dan mutasi ditolak | |
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
| D8 | Tabletop: jawab inject aktif dan perbarui respons tim | Empat field V2 tersimpan; revisi naik; inject mendatang dan respons tim lain tidak terlihat | |
| D9 | Tabletop: buka Result selesai | Hanya ringkasan peserta dan respons tim sendiri tampil | |

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
