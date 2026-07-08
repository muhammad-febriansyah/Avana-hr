# AvanaHR — Build Order (Step-by-Step, WAJIB URUT)

Urutan ini mengikuti dependensi teknis: **fondasi → master → transaksi → payroll → mobile → pelengkap**. Jangan mengerjakan step N sebelum Definition of Done (DoD) step N-1 terpenuhi. Rujukan: 03_PRD.md, 04_ERD.md, 05_DESIGN_SYSTEM.md, 06_PRD_PAYROLL.md, 07_ADDENDUM.
Estimasi asumsi 1 developer + Claude Code. UAT ID = acceptance test yang harus lolos (Pest).

---

## FASE 0 — Fondasi Platform (±2–3 minggu) — TIDAK ADA FITUR BISNIS SEBELUM INI SELESAI

### Step 0.1 — Skeleton Project
Laravel 13 + Inertia + React + TS + Tailwind v4 + shadcn init, Poppins, light mode only, struktur folder sesuai 05 Bagian B8/B9, Pest, Redis (cache+queue), `Model::preventLazyLoading()`.
**DoD:** halaman kosong ber-layout (sidebar+topbar+breadcrumb) jalan; CI lint+test hijau.

### Step 0.2 — Komponen Shared UI (dibangun SEKALI, dipakai semua modul)
`AppLayout`, `PageBreadcrumb`, `PageHeader`, `DataTable<T>` generik (search+filter+sort+pagination server-side), `CurrencyInput`, `DatePicker` (locale id), `RequiredLabel`, `StatusBadge`, `ConfirmDialog`, `EmptyState`, `Toaster` Sonner, varian warna tombol (tabel 05 B4), `lib/format.ts`.
**DoD:** halaman demo `/dev/components` menampilkan semua komponen; checklist 05 Bagian C bisa dipenuhi hanya dengan komponen ini.

### Step 0.3 — Multi-tenancy + Auth
Tabel `tenants`, `plans`, `plan_features`, `users`; trait `BelongsToTenant`; login (session) + panel Super Admin `/platform` terpisah; seed 1 tenant demo.
**DoD:** QA-0111 (isolasi tenant: akses lintas tenant → 404 + security_logs), QA-0112 (create tenant baru → data kosong terisolasi).

### Step 0.4 — RBAC (spatie, teams = tenant_id)
Enum permission per modul, seed 5 role default + Super Admin, halaman Role & Permission (role custom, checklist grouped).
**DoD:** QA-0113 (role view-only tidak bisa edit); semua route ber-middleware permission.

### Step 0.5 — Audit Trail + Settings
Trait `Auditable`, tabel `audit_logs`, `tenant_settings`, `security_logs`; halaman audit log (DataTable, filter model/user/tanggal).
**DoD:** QA-0114 (old/new value, actor, timestamp, IP tercatat).

### Step 0.6 — Approval Engine (JANTUNG SISTEM — dites tuntas di sini)
Tabel flows/steps/approvals/actions/delegations; builder workflow no-code; inbox "Persetujuan Saya"; delegasi; eskalasi SLA (scheduler); notifikasi database+mail.
**DoD:** QA-0010 (multi-level by rule), QA-0011 (delegasi), QA-0119 (rule baru hanya untuk pengajuan baru), E2E-0157 (eskalasi SLA); hard rule requester ≠ approver.

### Step 0.7 — Dynamic Menu (M10 addendum)
Registry + override + settings + role visibility; sidebar render dari DB + cache; halaman `/platform/menus` & `/settings/menus` (drag & drop).
**DoD:** Admin tenant hide/rename/reorder menu berhasil; menu di luar paket tidak pernah muncul; permission tetap memblokir route walau menu tampil.

> Kenapa menu masuk Fase 0: seluruh halaman setelah ini didaftarkan lewat seeder menu registry — kalau dibangun belakangan, harus refactor sidebar dua kali.

---

## FASE 1 — Master Data & Karyawan (±2–3 minggu)

### Step 1.1 — Struktur Organisasi + Grade
`org_units` (tree, effective-dated), `positions`, `grades` (band SUSU); tree view + org chart read-only; validasi circular.
**DoD:** QA-0007, QA-0008, QA-0009 (circular ditolak dengan pesan jelas).

### Step 1.2 — Cabang & Lokasi + Geofence
`branches` (koordinat + radius); form dengan map picker (leaflet); penempatan karyawan (dibuat setelah 1.3, relasinya disiapkan).
**DoD:** CRUD cabang; radius tersimpan; filter per cabang di list.

### Step 1.3 — Database Karyawan (modul terbesar Fase 1)
`employees` + hash unik NIK/NPWP + custom fields + kontrak (reminder H-30) + halaman detail ber-tab; Employee ID auto; import Excel karyawan (template + exception list).
**DoD:** QA-0001, QA-0002 (duplikasi ditolak per field), QA-0012 (reminder kontrak), QA-0016 (effective-dated), QA-0120 (custom field muncul di form).

### Step 1.4 — Maker-Checker + Siklus Karyawan
`employee_change_requests`; `employee_movements` (mutasi/promosi via approval, scheduler apply per effective date); `employee_terminations` + exit clearance sederhana; user karyawan (akun ESS) auto-provision.
**DoD:** QA-0003 (pending → aktif setelah approve), QA-0004, QA-0005, QA-0006 (resign menonaktifkan akses, data historis tetap).

> Milestone M1: HR bisa onboard perusahaan lengkap (org, cabang, karyawan) — layak demo pertama ke client.

---## FASE 2 — Time Management (±3 minggu)

### Step 2.1 — Shift & Jadwal
`shifts`, `shift_patterns`, `employee_schedules` + generator bulk + `holidays` (seed libur nasional).
**DoD:** QA-0024 (pola rotasi 2-2-3 ter-generate, tak bentrok libur/cuti).

### Step 2.2 — Cuti
`leave_types`, `leave_balances`, `leave_requests` + approval + kalender tim; saldo pending/available/used/expired; accrual awal tahun + prorata join.
**DoD:** QA-0026, QA-0027, QA-0028, QA-0095, E2E-0150 (cancel mengembalikan saldo).

### Step 2.3 — Kehadiran: API + Web + Rekap
Endpoint `POST /api/v1/attendance/events` (idempotent by uuid, server time, haversine geofence, suspicious flag); absen via web (GPS browser); `attendance_summaries` + command rekap harian; import fingerprint; halaman monitoring kehadiran hari ini (polling).
**DoD:** QA-0017, QA-0021 (duplicate ditolak), QA-0033, QA-0034 (import dedup).

### Step 2.4 — Koreksi Kehadiran + Lock Periode
`attendance_corrections` via approval; tombol Lock periode absensi (prasyarat payroll).
**DoD:** QA-0022, QA-0023 (final berubah hanya setelah approve + audit).

### Step 2.5 — Lembur
`overtime_requests` via approval; aktualisasi vs attendance; rate table Kepmenaker configurable.
**DoD:** QA-0029, QA-0030 (aktual mengikuti attendance + policy).

### Step 2.6 — Mobile Flutter v1 (paralel dengan 2.3–2.5 bila ada bandwidth)
Login Sanctum + app-lock biometric → enrollment wajah (ML Kit + MobileFaceNet TFLite, simpan embedding) → clock-in/out (liveness + similarity + GPS) → offline queue (drift) + sync → pengajuan & riwayat cuti/lembur/koreksi → push FCM.
**DoD:** QA-0018 (face match + confidence tersimpan), QA-0019 (mismatch → suspicious), QA-0020 (offline sync tanpa duplikat), QA-0117 (app-lock).

> Milestone M2: absensi wajah jalan end-to-end dari HP karyawan sampai rekap HR — fitur jualan utama, demo kedua.

---

## FASE 3 — Payroll (±3–4 minggu — JANGAN DIKOMPRES, paling berisiko)

Urutan internal WAJIB (tiap step tergantung step sebelumnya):

### Step 3.1 — Setting Komponen
`working_day_rules`, `tax_ptkp_rates`, `tax_ter_rates` (seed PMK 168/2023), `bpjs_rates`, `regional_minimum_wages`, `component_formulas` + `formula_items` (formula builder UI).

### Step 3.2 — Master Komponen
`salary_components` (3 dasar perhitungan, min/max, flags pajak/prorate/pay_after_inactive) — DoD: seed default (Gaji Pokok, T. Jabatan, T. Makan, T. Transport, Lembur, potongan BPJS, PPh 21).

### Step 3.3 — Master Gaji (Payroll Group)
`payroll_groups` + pivot komponen + setting periode/cut-off/sumber absensi-OT — DoD: 1 group default bulanan ter-seed per tenant baru.

### Step 3.4 — Nilai Komponen + Gaji Pokok
`component_value_mappings` (resolusi prioritas), `employee_component_overrides` (approval + SK), `employee_basic_salaries` (append-only, validasi band).
**DoD:** QA-0042 (warning out-of-range SUSU); unit test resolusi nilai: override > mapping spesifik > formula > fixed.

### Step 3.5 — Calculation Engine (queued, chunked) — INTI
Job `CalculatePayrollRun`: snapshot → tarik absensi locked + lembur aktual + unpaid leave → resolve komponen → prorate → BPJS → PPh 21 TER → tulis payslip_lines + calculation_note.
**DoD:** unit test dengan fixture angka riil: QA-0035, QA-0038 (TER), QA-0040/0041 (BPJS), prorate join/resign; E2E-0148, E2E-0149. **Validasi manual bersama orang payroll/klien sebelum lanjut.**

### Step 3.6 — Review, Approval, Lock
Halaman detail run (rekap per komponen, drill-down, anomali net<0 & delta>threshold); submit → approval (SoD) → lock (freeze snapshot); unlock ber-otorisasi + audit.
**DoD:** QA-0036 (lock), QA-0052 (self-approval ditolak).

### Step 3.7 — Output: Slip, Bank File, Laporan
Slip PDF terproteksi (token + akses ESS), bank file driver (BCA dulu, lalu Mandiri/BRI/BNI), laporan proses/rekap/gaji detail/PPh21/BPJS (dari final saja, export queued).
**DoD:** QA-0044, QA-0045 (akses ilegal ditolak + security log), QA-0050, QA-0051 (exception rekening invalid), QA-0108, QA-0109.

### Step 3.8 — Koreksi/Retro, Rekalkulasi Tahunan, THR/Bonus, Pinjaman
`payroll_adjustments` (retro), run Desember (pasal 17), run irregular THR prorata & bonus, `employee_loans` auto-deduct.
**DoD:** QA-0037, QA-0039, QA-0047, QA-0048, QA-0049; E2E-0147 (final settlement resign).

> Milestone M3: satu siklus gajian penuh (absen → hitung → approve → lock → slip → bank file) — sistem layak dipakai produksi terbatas (pilot 1 tenant).

---

## FASE 4 — ESS/MSS, Dashboard & Pelengkap (±2 minggu)

### Step 4.1 — ESS/MSS Web lengkap
Dashboard pribadi, slip di ESS, MSS bulk approve + tim + kalender cuti.
**DoD:** QA-0092, QA-0093, QA-0097, QA-0098, QA-0110 (row-level manager).

### Step 4.2 — Mobile Flutter v2
Slip gaji (PDF viewer), approval manager, kalender ringkas, pengumuman.

### Step 4.3 — Dashboard Perusahaan + Laporan Kehadiran/Cuti
KPI cards + tren payroll + komposisi departemen + gauge absensi (referensi HumanCore); cache 15 menit; export rekap kehadiran & cuti.
**DoD:** QA-0103; angka dashboard = angka laporan (konsistensi).

### Step 4.4 — Calendar (M13) + Pengumuman
Kalender multi-layer + `company_events` + reminder; pengumuman bertarget.

### Step 4.5 — CRM Pipeline (M12)
Pipeline/stage configurable → kanban dnd → aktivitas & task → insights ringkas. (Bisa digeser setelah go-live bila timeline mepet — tidak memblokir HRIS.)

### Step 4.6 — AI Foundation (M11)
Provider config + driver + usage log + fitur pertama `ai_insights` (narasi metrik dashboard). HR Copilot chat = setelah go-live.

---

## FASE 5 — Hardening & Go-Live (±1–2 minggu)

1. **Security pass:** QA-0111 ulang menyeluruh, QA-0113, QA-0114, QA-0116 (MFA), QA-0118 (masking + HTTPS), rate limit, signed URL.
2. **Performance pass:** EXPLAIN semua query list, cek N+1 (guard sudah on), k6/ab test halaman berat + payroll 1.000 karyawan dummy < 5 menit.
3. **Regression UAT:** jalankan seluruh 84 P0 dari Skenario_UAT.xlsx → 100% pass.
4. Seeder onboarding tenant (wizard: data perusahaan → cabang → import karyawan → jenis cuti → payroll group) — menentukan kecepatan aktivasi klien.
5. Backup + monitoring (log, uptime, queue horizon) + runbook.

---

## Ringkasan Dependensi (kenapa urutannya begini)

```
Approval Engine ──► dipakai: perubahan data, movement, cuti, lembur, koreksi, payroll, override
Menu Dinamis ──► semua halaman berikutnya didaftarkan via seeder menu
Org + Grade ──► posisi karyawan, rute approval (direct manager), band gaji
Karyawan ──► semua transaksi
Cabang ──► geofence absensi
Shift/Jadwal ──► status kehadiran & lembur dihitung terhadap jadwal
Absensi LOCKED + Lembur AKTUAL + Cuti ──► input payroll
Payroll LOCKED ──► slip ESS, bank file, laporan pajak/BPJS
```

## Aturan kerja dengan Claude Code
1. Satu step = satu sesi/branch (`feat/step-2-3-attendance-api`), merge hanya bila DoD + test hijau.
2. Setiap mulai step: baca 05_DESIGN_SYSTEM.md + bagian PRD terkait + tabel ERD terkait.
3. Setiap selesai halaman: jalankan checklist 05 Bagian C.
4. Test UAT ID di DoD ditulis sebagai Pest test dengan nama sama (mis. `test('QA-0052 self approval payroll ditolak')`) — traceability langsung ke dokumen klien.
```
