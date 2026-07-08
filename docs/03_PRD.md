# AvanaHR — Product Requirements Document (Wave 1 MVP)

**Stack:** Laravel 13 · React 19 · Inertia.js v2 · TypeScript · Tailwind CSS v4 · shadcn/ui · MySQL 8 · Redis (cache + queue) · Flutter (GetX) untuk aplikasi karyawan.
**Pola:** Service-Repository-Action · spatie/laravel-permission · no-modal CRUD (halaman Inertia terpisah) · polling `usePoll` (tanpa WebSocket) · BIGINT Rupiah · UTC simpan → WIB tampil · soft delete master data · snapshot data transaksional · pesan validasi Bahasa Indonesia.
**Dokumen terkait:** 01_PROJECT_GOALS.md, 02_USER_STORIES.md, 04_ERD.md, 05_DESIGN_SYSTEM.md.

---

## M00 — Platform & Fondasi

### M00.1 Multi-tenancy (single DB, row-level)
- Semua tabel bisnis punya `tenant_id` (BIGINT, indexed). Trait `BelongsToTenant` menambah global scope + auto-fill saat create.
- Konteks tenant di-resolve dari user login (`users.tenant_id`); Super Admin (`tenant_id = null`) mengelola lintas tenant lewat panel platform terpisah (`/platform/*`).
- Query tanpa scope hanya boleh via `withoutTenancy()` eksplisit di layer platform, dilarang di layer tenant.
- Uji wajib: user tenant A akses resource tenant B via URL/API → 404 + tercatat di security log.

### M00.2 Auth & RBAC
- Auth web: Laravel session (Inertia). Auth mobile: Sanctum token.
- 5 role default per tenant + Super Admin (platform): Company Admin, HR Admin (mencakup payroll master), Approver/Finance, Manager, Employee. Payroll Admin sebagai role opsional (bisa dibuat dari permission).
- Seluruh authorization pakai permission (`can('payroll.process')`), bukan `hasRole`. Role = bundle permission; tenant boleh buat role custom.
- Daftar permission per modul didefinisikan sebagai enum PHP + seeder; UI role editor menampilkan permission grouped per modul (checklist lihat/tambah/ubah/hapus/approve/export).
- MFA (TOTP) opsional per tenant. Lockout 5x gagal login.

### M00.3 Approval Engine (polymorphic, dipakai semua modul)
- Tabel `approval_flows` (per tenant, per `approvable_type`: cuti, lembur, koreksi absen, perubahan data, mutasi, payroll run, koreksi gaji) + `approval_steps` (urutan, tipe approver: direct_manager | specific_user | role | position, kondisi opsional: min_amount, grade, branch).
- Runtime: `approvals` (morph ke transaksi) + `approval_actions` (per step: pending/approved/rejected, actor, catatan, acted_at).
- Aturan: langkah berurutan; reject di step mana pun = transaksi rejected; delegasi aktif mengalihkan tugas dengan mencatat approver asli; requester tidak boleh jadi approver dirinya (hard rule); eskalasi bila melewati SLA (jam configurable) → naik ke step berikutnya + notifikasi (E2E-0157).
- Perubahan flow hanya berlaku untuk pengajuan baru.

### M00.4 Audit Trail
- Trait `Auditable` mencatat ke `audit_logs`: model, id, event (created/updated/deleted/approved/…), old_values & new_values (JSON, hanya field berubah), user_id, IP, user_agent.
- Wajib pada: employees & sub-datanya, komponen payroll, gaji pokok, override, payroll run status, koreksi kehadiran, role/permission, approval action.

### M00.5 Notifikasi
- Channel: database (in-app), mail, FCM push (mobile). WhatsApp: interface `WhatsAppChannelInterface` dengan implementasi null default (integrasi ChatCepat menyusul).
- Event ternotifikasi: pengajuan masuk ke approver, hasil approve/reject ke requester, slip gaji terbit, kontrak akan berakhir (H-30), payroll run butuh approval.

### M00.6 Feature Gating per Paket
- `plans` + `plan_features` (kode fitur) + `tenants.plan_id`. Middleware `feature:` + helper `tenant()->hasFeature('payroll.custom_run')`.
- MVP: semua modul Wave 1 masuk Essential; gate disiapkan untuk Wave 2/3 (rekrutmen, performance, AI = Enterprise 360).

### M00.7 Settings & Master Referensi
- `tenant_settings` key-value (JSON) untuk: zona waktu display (default WIB), format slip, password slip default, batas % potongan gaji, threshold face match, radius geofence default, SLA approval.
- Master referensi platform (shared, tanpa tenant_id): tarif PTKP, tabel TER (PMK 168/2023), tarif & plafon BPJS, UMR per provinsi/kota (effective-dated) — tenant bisa override bila perlu.

---

## M01 — Organisasi & Cabang

### M01.1 Struktur Organisasi
- Hierarki: Company → Division → Department → Unit (self-referencing `org_units.parent_id`, `level`).
- Posisi (`positions`): nama, org_unit, grade, reporting ke posisi lain. Validasi anti-circular (cek rantai parent saat simpan — tolak dengan pesan "Struktur pelaporan melingkar terdeteksi").
- Perubahan struktur effective-dated (`effective_date`); org chart dibaca per tanggal.
- Halaman: tree view + form CRUD per halaman. Org chart visual read-only (library react-organizational-chart atau custom).

### M01.2 Cabang & Lokasi Kerja (BR-27)
- `branches`: nama, alamat, `latitude`, `longitude` (decimal 10,7), `geofence_radius_m` (default 100), zona waktu, cost center.
- Karyawan wajib punya minimal satu penempatan (`employee_id`, `branch_id`, `is_primary`).
- Semua list & laporan mendukung filter cabang.

## M02 — Karyawan (HR Core)

### M02.1 Data Karyawan
- `employees` (data inti + payroll info) — lihat ERD. Employee ID auto: `{prefix_tenant}-{tahun}-{seq}` configurable.
- Validasi unik per tenant: NIK KTP, NPWP, email; pesan validasi per field dalam Bahasa Indonesia.
- Custom field per tenant: `custom_field_definitions` (label, tipe: text/number/date/select, mandatory) + `custom_field_values` (morph). Muncul otomatis di form & detail.
- Tab detail karyawan: Profil, Kepegawaian, Payroll (gaji pokok + komponen), Kehadiran, Cuti, Dokumen (Wave 2 stub), Riwayat (audit).

### M02.2 Maker-Checker Data Sensitif
- Field sensitif (rekening bank, NPWP, NIK, nama): perubahan dari ESS atau HR non-admin → `employee_change_requests` (old/new JSON) → approval → apply. Field non-sensitif langsung tersimpan.

### M02.3 Siklus Karyawan (BR-03)
- `employee_movements`: type (mutation/promotion/demotion), from/to (org_unit, position, grade, branch, salary snapshot), effective_date, via approval engine.
- Setelah approved + effective date tercapai (scheduled command harian): data karyawan terupdate, riwayat tersimpan.
- Resign: `employee_terminations` (type: resign/PHK/pensiun/meninggal, effective_date, alasan) + exit clearance checklist sederhana MVP (item configurable). Efek: status inactive per tanggal, user login dinonaktifkan, muncul di run payroll terakhir sebagai final settlement.

### M02.4 Kontrak
- `employee_contracts`: nomor, type (PKWT/PKWTT/Magang/Kemitraan), start/end, file. Reminder H-30/H-14/H-7 sebelum berakhir → notifikasi HR + tampil di dashboard ("Kontrak Akan Berakhir").

## M03 — Shift & Jadwal (BR-08)

- `shifts`: nama, jam masuk/pulang, toleransi terlambat (menit), lintas hari (overnight flag), istirahat.
- `shift_patterns` + `pattern_items` (urutan hari → shift/off) untuk rotasi (mis. 2-2-3).
- `employee_schedules`: hasil generate per karyawan per tanggal (shift_id nullable = libur). Generator bulk: pilih karyawan/unit + pola + rentang tanggal; deteksi bentrok cuti approved & hari libur (`holidays` per tenant, seed libur nasional).
- Tukar shift & perubahan jadwal individual = Wave 2; MVP: HR bisa edit jadwal per tanggal (dengan audit).

## M04 — Kehadiran (BR-06) + Mobile Flutter

### M04.1 Alur Absensi Mobile (face recognition on-device)
1. **Enrollment** (sekali, saat aktivasi): ambil 3–5 pose → ML Kit face detection (kualitas: satu wajah, mata terbuka, frontal) → MobileFaceNet TFLite → simpan rata-rata embedding (float32[192]) di secure storage device + kirim ke server (encrypted at rest) untuk re-enroll lintas device. Foto mentah TIDAK disimpan/dikirim.
2. **Clock-in/out**: buka kamera → liveness pasif ML Kit (kedip / gerakan kepala acak) → embedding baru → cosine similarity vs enrollment. `similarity >= threshold` (default 0.75, per-tenant setting) = match.
3. **Kirim ke server**: `POST /api/attendance/events` body: `event_uuid` (idempotency), type (in/out), captured_at (device), lat, lng, similarity_score, liveness_passed, device_id. Mismatch tetap dikirim dengan flag → server catat `is_suspicious = true` (QA-0019), absensi tidak dihitung tapi terlacak.
4. **Server validasi**: waktu resmi = server time; geofence dihitung server-side (haversine vs branch penempatan; WFH approved → skip geofence); di luar radius → simpan dengan `is_outside_geofence = true` (butuh review, bukan auto-reject); duplicate `event_uuid` → 200 idempotent; clock-in kedua di hari sama → ditolak dengan pesan (atau dicatat event tambahan sesuai policy setting).
5. **Offline**: event antre di SQLite (drift) → sync saat online, urutan FIFO, retry backoff.

### M04.2 Rekap Harian
- Command per hari (atau on-the-fly saat lock periode): pasangkan in/out vs jadwal → `attendance_summaries` per karyawan per tanggal: status (hadir/terlambat/pulang cepat/alfa/cuti/libur/dinas), menit terlambat, jam kerja, jam lembur kandidat.
- Import file mesin fingerprint (Excel/CSV template): mapping employee, dedup by (employee, tanggal, timestamp), error → exception list downloadable (QA-0033/0034).

### M04.3 Koreksi Kehadiran (BR-35)
- `attendance_corrections`: tanggal, field dikoreksi (jam masuk/pulang/status), nilai usulan, alasan, lampiran → approval engine → setelah approve: summary & event disesuaikan + audit. Data final tidak berubah sebelum approve.

## M05 — Cuti (BR-07)

- `leave_types`: nama, kuota tahunan, carry-over (max hari, expiry), potong saldo ya/tidak (izin sakit dengan surat = tidak), butuh lampiran, min notice hari, max hari berurutan.
- `leave_balances` per karyawan per tipe per tahun: entitled, used, pending, carried_over, expired. Accrual: kredit penuh awal tahun (default) atau prorata join.
- Pengajuan: rentang tanggal (exclude libur/weekend configurable), cek saldo (pending mengurangi sementara), lampiran bila wajib → approval → saldo final + kalender tim; cancel sebelum mulai mengembalikan saldo (E2E-0150).
- Kalender tim (Manager): bulan view, overlap warning bila > X% anggota cuti bersamaan.

## M06 — Lembur (BR-09)

- `overtime_requests`: tanggal, jam rencana mulai/selesai, alasan → approval sebelum pelaksanaan.
- Jam aktual = irisan (rencana approved ∩ attendance aktual di luar jam kerja), dibulatkan per policy (default: ke bawah per 30 menit). Formula upah lembur configurable per tenant: default Kepmenaker (1/173 × upah × multiplier 1.5/2/3/4 sesuai urutan jam & hari libur) — disimpan sebagai rate table, bukan hardcode.
- Hanya lembur approved + teraktualisasi yang mengalir ke payroll (E2E-0149).

## M07 — Payroll (BR-11/12/13) — Detail penuh di PRD_PAYROLL

Modul payroll mengikuti **PRD_AvanaHR_Modul_Payroll.md** (arsitektur 4 lapis BPR: Setting Komponen → Master Komponen → Master Gaji/Payroll Group → Nilai Komponen; run lifecycle `draft → calculated → pending_approval → approved → locked → paid`; PPh 21 TER + rekalkulasi Desember; BPJS; snapshot immutable; SoD; koreksi via adjustment; THR/bonus run irregular; bank file driver BCA/Mandiri/BRI/BNI; slip PDF terproteksi via ESS).

Integrasi lintas modul:
- Prasyarat calculate: periode `attendance_summaries` berstatus locked; koreksi pending = blocker final.
- Sumber: attendance_summaries (hadir/alfa/terlambat → potongan sesuai komponen), overtime aktual (→ komponen lembur), leave unpaid (→ potongan), employee_movements (gaji per effective date), terminations (final settlement + komponen `pay_after_inactive`).

## M08 — ESS / MSS

### Web (Inertia)
- ESS: dashboard pribadi, profil + pengajuan perubahan, absen web (GPS browser, tanpa wajah — ditandai channel web), riwayat kehadiran, cuti (saldo + pengajuan), lembur, slip gaji.
- MSS: pending approvals (bulk approve), tim saya (profil terbatas field), kehadiran tim hari ini, kalender cuti tim.

### Mobile Flutter (karyawan + manager)
- Modul: login + biometric app-lock, absensi (face + GPS + offline), pengajuan & riwayat (cuti/lembur/koreksi), approval (manager), slip gaji (PDF viewer), notifikasi push, pengumuman.
- State: GetX. API: Sanctum bearer. Endpoint versi `/api/v1/*`. Response envelope `{ data, message, meta }`.

## M09 — Dashboard & Laporan

- Dashboard Company Admin/HR (referensi mockup HumanCore): kartu KPI (total karyawan + delta, biaya payroll bulan ini + delta, kehadiran rata-rata, kontrak akan berakhir), tren biaya payroll 6 bulan (line), komposisi per departemen (donut), absensi hari ini (gauge hadir/terlambat/tidak hadir), daftar kontrak akan berakhir. Data di-cache 15 menit per tenant.
- Laporan MVP: rekap kehadiran bulanan (per karyawan/cabang), rekap cuti, laporan payroll (proses, rekap bulanan, gaji detail), laporan PPh 21, laporan BPJS. Semua export Excel (maatwebsite) + PDF, dari data final saja, di-generate via queued job dengan notifikasi selesai bila > 3.000 baris.
- Row-level access: Manager hanya data bawahannya (QA-0110).

---

## Non-Functional Requirements

| Aspek | Ketentuan |
| --- | --- |
| Performa | Lihat 05_DESIGN_SYSTEM.md bagian Performa (eager loading wajib, indexing, pagination server-side, cache) |
| Keamanan | HTTPS only, field sensitif (NIK, NPWP, rekening) encrypted cast + masking di UI sesuai permission (QA-0118), rate limit login & API absensi, signed URL untuk file |
| Kepatuhan | UU PDP: embedding bukan foto, consent saat enrollment, hak hapus data; retensi payroll ≥ ketentuan pajak |
| Skala | Target: tenant hingga 5.000 karyawan; payroll run 1.000 karyawan < 5 menit (queued, chunked) |
| Backup | DB harian + storage; file di S3-compatible (default: lokal disk untuk shared hosting) |
| Testing | Pest; setiap acceptance criteria UAT P0 punya minimal 1 feature test; kalkulasi payroll & PPh 21 unit-tested dengan fixture angka riil |
| Bahasa | UI & validasi Bahasa Indonesia; format tanggal `d MMMM yyyy` WIB; Rupiah `Rp 1.234.567` |
