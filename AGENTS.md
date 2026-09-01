
## Disiplin Commit
- Commit setiap sub-tugas yang selesai sebelum lanjut ke sub-tugas berikutnya.
- Jangan akhiri sesi dengan perubahan tanpa commit.

## Disiplin Commit
- Commit setiap sub-tugas yang selesai sebelum lanjut ke sub-tugas berikutnya.
- Jangan akhiri sesi dengan perubahan tanpa commit.

## Standar UI/UX (Modern - Dinamis - Profesional)
Modern:
- Whitespace lega, radius xl/2xl, shadow lembut; jangan warna default framework.
- Ikon SVG inline konsisten; jangan emoji.
- Warna lewat CSS variables (dark-ready); jangan hardcode di komponen baru.
Dinamis:
- Setiap elemen interaktif WAJIB punya hover, focus-visible, dan active state.
- Transisi 150-200ms; jangan animasi lambat yang mengganggu.
- Halaman list WAJIB punya 3 state: loading (.skeleton), kosong (EmptyState), error.
- Konten halaman masuk dengan .fade-in; hormati prefers-reduced-motion.
- Tombol submit menampilkan status memproses (disabled + teks berubah).
Profesional:
- Satu hero per halaman; judul pakai .font-display.
- Angka rata kanan di tabel; tanggal format konsisten (d MMM yyyy).
- Teks UI Bahasa Indonesia konsisten; jangan lorem ipsum.
- Kontras aksesibel (WCAG AA) untuk teks dan badge.

## Otonomi V2
- Migration DIPERBOLEHKAN hanya bila brief tugas memuat spesifikasi migration
  eksplisit; wajib meniru pola RLS migration existing; dilarang membuat policy
  di luar spesifikasi brief.
- Setiap fitur baru WAJIB menyertakan test Pest: happy path + otorisasi
  lintas tenant (RLS) + penolakan role yang salah.
- File app/Http/Middleware, policy, dan routes/web.php hanya berubah sesuai
  spesifikasi brief.
- Update test lama yang memang sengaja diubah perilakunya oleh brief,
  dan sebutkan di laporan.

## Definisi Selesai (KERAS)
- Test lama gagal = tugas belum selesai: update test atau perbaiki kode.
- DILARANG menghapus, men-skip, atau menandai incomplete test untuk
  menghijaukan suite.
- Jumlah passed TIDAK BOLEH turun dari baseline tanpa persetujuan reviewer.
- Laporan wajib menyertakan baris ringkasan ASLI output php artisan test.

## Aturan Kerja Hermes
- Hermes HANYA dijalankan di repo lab (C:\Users\budii\awareness-lab).
- DILARANG menjalankan hermes chat dari repo main.
- Main hanya menerima merge dari reviewer setelah review kode.
