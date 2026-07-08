# PRD — AvanaHR Modul Payroll

**Versi:** 1.0 · **Referensi:** Manual Book BPR-HRMS Modul Payroll, BRD HCMS v3 (BR-11, BR-12, BR-13, BR-15), Skenario UAT (PAY-0035 s/d PAY-0052, E2E-0148, E2E-0149, E2E-0151)
**Stack:** Laravel 13 + React + Inertia.js + TypeScript + Tailwind v4 + shadcn/ui · Service-Repository-Action · spatie/laravel-permission
**Konvensi:** BIGINT Rupiah, UTC→WIB display, snapshot pada data transaksional, soft delete pada master data, no-modal CRUD (halaman Inertia terpisah), Sonner notification, pesan validasi Bahasa Indonesia.

---

## 1. Ringkasan

Modul Payroll AvanaHR mengadopsi arsitektur payroll engine 4 lapis dari sistem BPR-HRMS yang sudah terbukti di lapangan, dengan modernisasi: PPh 21 skema TER (PMK 168/2023), snapshot immutable per payroll run, lock period, segregation of duties, slip gaji digital terproteksi, dan bank transfer file multi-format. Seluruh data ter-scope per tenant (`tenant_id` global scope).

**Prinsip utama:** komponen gaji sepenuhnya dapat dikonfigurasi per tenant tanpa coding. Formula dirakit dari kombinasi komponen. Nilai komponen di-resolve otomatis dari atribut pegawai (grade, posisi, lokasi) via mapping, bukan input manual per orang.

---

## 2. Adopsi dari Manual BPR — Mapping

| Fitur BPR | Diadopsi ke AvanaHR | Catatan |
| --- | --- | --- |
| Master Gaji (kategori gaji) | ✅ `payroll_groups` | Ditambah relasi ke tenant & tier |
| Master Komponen Penerimaan/Potongan | ✅ `salary_components` | Struktur atribut hampir identik |
| Dasar perhitungan Formula/Tabel/Fixed | ✅ 3 tipe `calculation_basis` | Inti fleksibilitas engine |
| Setting Komponen: Master Formula + kombinasi | ✅ `component_formulas` + `formula_items` | Operator × dan + dipertahankan |
| Perhitungan Hari | ✅ `working_day_rules` | Faktor pembagi hari kerja |
| Tarif PTKP | ✅ `tax_ptkp_rates` | Tetap dibutuhkan untuk penentuan kategori TER |
| Tarif PKP progresif | ⚠️ Diganti | Digantikan tabel TER bulanan + rekalkulasi tahunan pasal 17 di Desember |
| Nilai Komponen (mapping by atribut) | ✅ `component_value_mappings` | Resolusi by grade/posisi/status/lokasi |
| Setting Master Gaji: periode, cut-off, sumber absensi/OT (berjalan vs bulan lalu) | ✅ Field di `payroll_groups` | Detail penting, jangan hilang |
| Perilaku komponen saat pegawai tidak aktif (masih/tidak dibayarkan) | ✅ `pay_after_inactive` flag | Krusial untuk final settlement |
| Proses gaji bulanan/mingguan/2-mingguan | ✅ `payroll_runs.frequency` | Enum: monthly, weekly, biweekly |
| Approval proses gaji | ✅ Approval engine polymorphic | + segregation of duties (QA-0052) |
| Koreksi gaji via pengajuan + approval | ✅ `payroll_adjustments` | Pola BPR dipertahankan |
| Upload absensi & OT via Excel | ✅ Sebagai fallback/import | Sumber utama: modul Absensi internal |
| Cetak slip gaji | ✅ Slip digital PDF terproteksi | Password/akses per pegawai (QA-0044) |
| Sales Order & mapping SO | ❌ Tidak diadopsi | Spesifik bisnis outsourcing, di luar scope HRIS umum |
| Kategori Organik/Non-Organik BPO/PE | ❌ Diganti | Diganti `employment_status` generik (PKWT/PKWTT/Magang/Kemitraan) |

**Baru di AvanaHR (tidak ada di BPR):** PPh 21 TER, snapshot immutable, lock period, bank disbursement file, BPJS otomatis, THR prorata, pinjaman auto-deduct, retroactive adjustment, slip via ESS mobile.

---

## 3. Konsep & Istilah

- **Payroll Group (Master Gaji):** paket konfigurasi gaji per kategori pegawai — menentukan komponen yang berlaku, periode, cut-off, dan aturan perhitungan. Setiap pegawai ditempel ke tepat satu payroll group.
- **Salary Component (Master Komponen):** komponen penerimaan (earning) atau potongan (deduction) dengan dasar perhitungan Formula/Tabel/Fixed.
- **Component Formula (Master Formula):** kombinasi komponen dengan operator perkalian dan penjumlahan, contoh: `Tunjangan Makan = (Gaji Pokok × 0.05) + 50000`.
- **Value Mapping (Nilai Komponen):** aturan resolusi nilai berdasarkan atribut pegawai (grade, posisi, status, lokasi). Contoh: Gaji Pokok grade G3 area Jabodetabek = Rp 6.500.000.
- **Payroll Run (Proses Gaji):** satu eksekusi perhitungan untuk satu payroll group + satu periode. Status: `draft → calculated → pending_approval → approved → locked → paid`.
- **Payslip Line (Snapshot):** hasil perhitungan per pegawai per komponen, immutable setelah locked.
- **Adjustment (Koreksi Gaji):** perubahan setelah lock hanya via pengajuan koreksi ber-approval, dieksekusi sebagai komponen adjustment di periode berikutnya (retro) atau run khusus.

---

## 4. Arsitektur Data — 4 Lapis Master (Pola BPR)

```
Lapis 1  Setting Komponen   : component_formulas, working_day_rules, tax_ptkp_rates, tax_ter_rates
Lapis 2  Master Komponen    : salary_components (+ calculation basis)
Lapis 3  Master Gaji        : payroll_groups (+ pivot komponen, setting periode & cut-off)
Lapis 4  Nilai Komponen     : component_value_mappings + employee_component_overrides
                    ↓
Transaksi: payroll_runs → payroll_run_employees → payslip_lines (snapshot)
           payroll_adjustments, employee_loans, loan_installments
```

### 4.1 Skema Tabel Inti

Semua tabel: `id BIGINT`, `tenant_id` (global scope), `created_at/updated_at`, master data pakai `deleted_at` (soft delete). Nilai uang `BIGINT` Rupiah.

**salary_components**
```
code            string, unik per tenant
name            string
type            enum: earning | deduction
effective_date  date
sort_order      int                        -- urutan tampil di slip
is_taxable      boolean                    -- masuk perhitungan PPh 21
process_type    enum: regular | irregular  -- gaji rutin vs THR/bonus
frequency       enum: monthly | weekly | biweekly
show_on_payslip boolean
show_on_contract boolean
pay_after_inactive boolean                 -- masih dibayarkan setelah pegawai nonaktif (pola BPR)
calc_basis      enum: formula | table | fixed
formula_id      FK nullable → component_formulas
fixed_amount    BIGINT nullable
min_amount / max_amount  BIGINT nullable   -- batas hasil perhitungan (pola BPR)
prorate_enabled boolean
overtime_related boolean
bpjs_basis      boolean                    -- masuk dasar upah BPJS
is_active       boolean
```

**component_formulas** & **formula_items**
```
component_formulas: name, description, contract_display (process|setting), is_active
formula_items: formula_id, seq, source_type (earning|deduction|umr|constant),
               source_component_id nullable, multiplier decimal, add_operand BIGINT,
               prorate boolean
-- Evaluasi berurutan: result = Σ ( resolve(source) × multiplier + add_operand )
```

**working_day_rules**
```
name, divisor_days int (mis. 21, 25, 30), method enum: fixed | calendar | workdays
```

**tax_ptkp_rates** — status kawin/tanggungan (TK/0 s/d K/3), tahun, nilai tahunan.
**tax_ter_rates** — kategori TER (A/B/C), rentang penghasilan bruto bulanan (from/to BIGINT), tarif persen decimal, tahun berlaku. Seed dari lampiran PMK 168/2023. Kategori TER pegawai diturunkan dari status PTKP.

**payroll_groups**
```
code, name, description
frequency       enum: monthly | weekly | biweekly
period_start_day int                      -- tanggal mulai periode
cutoff_day      int                       -- tanggal cut-off (pola BPR: batas rapel)
working_day_rule_id  FK
attendance_source    enum: current | previous   -- absensi bulan berjalan vs bulan lalu (pola BPR)
overtime_source      enum: current | previous
prorate_method  enum: calendar | workdays
is_active
```
Pivot **payroll_group_components**: group_id, component_id, is_prorated, is_overtime_base (checklist prorate & overtime per pola BPR).

**component_value_mappings** (Nilai Komponen)
```
component_id    FK
-- kriteria mapping, semua nullable = wildcard:
employment_status, position_id, grade_id, branch_id, area_code
value           BIGINT
priority        int          -- resolusi: kriteria paling spesifik menang
effective_date  date
```

**employee_component_overrides** — nilai khusus per pegawai (pola BPR "tipe khusus"): employee_id, component_id, value, status aktif, effective_date, no_sk, file lampiran. Perubahan wajib approval (maker-checker) + audit trail.

**employee_basic_salaries** — riwayat gaji pokok (pola BPR update gaji pokok): employee_id, effective_date, no_sk, is_umr boolean, amount BIGINT, note, attachment. Append-only, tidak pernah di-update.

### 4.2 Tabel Transaksi

**payroll_runs**
```
payroll_group_id, frequency
period_start, period_end, cutoff_date, payment_date   -- semua dari popup proses BPR
status: draft | calculated | pending_approval | approved | locked | paid | cancelled
calculated_at, calculated_by
approved_at, approved_by       -- WAJIB ≠ calculated_by (SoD, QA-0052)
locked_at, locked_by
notes
```

**payroll_run_employees** — snapshot header per pegawai:
```
run_id, employee_id
-- SNAPSHOT (di-copy saat calculate, tidak refer ke master):
employee_name_snapshot, position_snapshot, grade_snapshot, branch_snapshot,
bank_name_snapshot, bank_account_snapshot, npwp_snapshot, ptkp_status_snapshot,
ter_category_snapshot, basic_salary_snapshot
attendance_days, absent_days, overtime_hours decimal, prorate_factor decimal
gross BIGINT, total_deduction BIGINT, pph21 BIGINT, net BIGINT
payslip_token uuid            -- akses slip
```

**payslip_lines** — snapshot detail: run_employee_id, component_code_snapshot, component_name_snapshot, type, amount BIGINT, calculation_note text (jejak formula yang dipakai — memudahkan audit & jawab komplain).

**payroll_adjustments** (Koreksi Gaji, pola BPR)
```
employee_id, effective_date, target_run_id nullable
change_points text, reason text, attachment
status: pending | approved | rejected | applied
requested_by, approved_by
-- setelah approved → diaplikasikan sebagai payslip_lines tipe adjustment
-- pada run berikutnya (retro) atau run aktif yang masih draft
```

**employee_loans** & **loan_installments** — pinjaman: principal, tenor, installment_amount, outstanding; cicilan auto-deduct saat run (QA-0047), dengan validasi total potongan ≤ batas % gaji (BR-15).

---

## 5. Flow Proses

### 5.1 Setup (sekali per tenant, oleh Payroll Admin)

1. Isi Setting Komponen: working day rules, PTKP, tabel TER (seed default disediakan sistem).
2. Buat Master Formula + rakit formula items.
3. Buat Master Komponen penerimaan & potongan, pilih dasar perhitungan.
4. Buat Payroll Group: checklist komponen, set periode, cut-off, sumber absensi/OT, prorate & overtime flags.
5. Isi Nilai Komponen (mapping) per grade/posisi/lokasi.
6. Tempel payroll group ke tiap pegawai; isi/import gaji pokok.

### 5.2 Payroll Run Bulanan (E2E-0148)

```
1. Payroll Admin buat run: pilih group + periode → sistem isi default
   period_start/end, cutoff, payment_date dari konfigurasi group.
2. [Prasyarat] Periode absensi sudah LOCKED di modul Absensi.
   Koreksi kehadiran pending = warning, tidak bisa lanjut final.
3. CALCULATE (queued job, chunked per 100 pegawai):
   a. Ambil pegawai aktif ber-group tsb (+ pegawai resign dalam periode → final settlement,
      komponen dengan pay_after_inactive=false otomatis gugur).
   b. Snapshot data pegawai ke payroll_run_employees.
   c. Tarik rekap absensi & lembur approved sesuai attendance_source/overtime_source.
   d. Resolve tiap komponen: override pegawai > value mapping (paling spesifik) > formula > fixed.
      Terapkan min/max, prorate (join/resign tengah periode), pembagi hari kerja.
   e. Potongan otomatis: cicilan pinjaman, unpaid leave, sanksi absensi.
   f. Hitung BPJS Kesehatan + TK (JHT/JP/JKK/JKM) dengan plafon & rate configurable per tenant.
   g. Hitung PPh 21 TER: bruto bulanan × tarif TER kategori pegawai.
      Run Desember / final settlement → rekalkulasi tahunan pasal 17, selisih jadi
      kurang/lebih bayar (QA-0039).
   h. Tulis payslip_lines + agregat gross/deduction/net.
4. REVIEW: halaman rekap (pola Rekap Proses BPR) — total per komponen, perbandingan
   vs periode lalu, drill-down per pegawai, daftar anomali (net < 0, selisih > threshold %).
5. SUBMIT APPROVAL → approval engine. Approver ≠ pembuat run (hard rule, QA-0052).
6. APPROVED → LOCK: snapshot final, periode terkunci. Perubahan data master/absensi
   setelah ini tidak mempengaruhi run (QA-0036). Unlock hanya Company Admin + alasan + audit.
7. Output pasca-lock:
   - Slip gaji PDF per pegawai, akses via ESS (hanya miliknya sendiri — QA-0045),
     download opsional password (default: tanggal lahir ddmmyyyy, configurable).
   - Bank file: generator per format (BCA, Mandiri, BRI, BNI — driver pattern,
     mudah tambah bank). Rekening invalid → exception list, tidak ikut file (QA-0051).
   - Status run → PAID setelah konfirmasi transfer.
8. Laporan: proses gaji per run, rekap bulanan, gaji detail per kategori/cabang,
   laporan PPh 21 & BPJS (hanya dari run final, bukan draft — QA-0108).
```

### 5.3 Koreksi Setelah Lock (pola BPR + retro QA-0037)

Pengajuan koreksi (alasan + lampiran) → approval → sistem hitung selisih → masuk sebagai komponen adjustment bertanda di run periode berikutnya. Run yang sudah locked **tidak pernah diedit**.

### 5.4 THR & Bonus (run khusus)

Run terpisah tipe `irregular` (slip terpisah — sesuai kebutuhan "Payroll Custom" di List Modul): THR prorata masa kerja < 12 bulan (QA-0048), bonus dengan multiplier rating performance (QA-0049). Pajak irregular dihitung sesuai ketentuan TER untuk penghasilan tidak teratur.

---

## 6. Aturan Bisnis

1. Payroll tidak bisa final sebelum review + approval (BR-11); approver wajib berbeda dari pembuat.
2. Hanya lembur **approved** dan absensi **final/locked** yang masuk perhitungan (BR-09, E2E-0148/0149).
3. Total potongan cicilan + potongan lain ≤ batas % gaji configurable per tenant, default 50% (BR-15).
4. Komponen dan gaji pokok bersifat effective-dated — perhitungan pakai nilai yang berlaku pada periode, bukan nilai saat input (QA-0016).
5. Reimbursement masuk payroll hanya setelah approval final dan tidak boleh double-paid (E2E-0151) — flag `paid_in_run_id` di klaim.
6. Semua perubahan master komponen, override pegawai, dan gaji pokok tercatat di audit trail (old/new value, actor, timestamp).
7. Slip hanya bisa diakses pegawai bersangkutan + role berwenang; percobaan akses ilegal dicatat di security log (QA-0045).
8. Gaji divalidasi terhadap band SUSU grade-nya; out-of-range → warning saat setup, bukan blocker (QA-0042, BR-13).

---

## 7. Halaman Inertia (no-modal CRUD)

```
/payroll/components            index + create + edit (halaman terpisah)
/payroll/formulas              index + builder formula (drag urutan item)
/payroll/settings              working day rules, PTKP, TER, BPJS config (tab)
/payroll/groups                index + create/edit + halaman setting komponen per group
/payroll/value-mappings        per komponen, tabel mapping
/payroll/employees             data gaji pegawai (pola Data Pegawai BPR): riwayat gaji pokok,
                               komponen aktif/nonaktif, override
/payroll/runs                  index (filter periode/status) + create
/payroll/runs/{id}             detail run: rekap, drill-down pegawai, anomali, tombol
                               calculate/submit/approve/lock sesuai status & permission
/payroll/runs/{id}/employees/{eid}   detail slip per pegawai + calculation_note
/payroll/adjustments           koreksi gaji: index + create + approval
/payroll/loans                 pinjaman & jadwal cicilan
/payroll/reports               proses gaji, rekap bulanan, gaji detail, PPh 21, BPJS, bank file
```

Permission (spatie): `payroll.view`, `payroll.manage-master`, `payroll.process`, `payroll.approve`, `payroll.lock`, `payroll.view-all-payslips`, `payroll.adjust`, `payroll.export-bank`.

---

## 8. Acceptance Criteria (dari Skenario UAT)

| UAT | Kriteria | Fase |
| --- | --- | --- |
| PAY-0035 | Run bulanan menghasilkan gross/deduction/net untuk semua pegawai eligible | MVP |
| PAY-0036 | Lock period: data tidak berubah tanpa unlock berotorisasi | MVP |
| PAY-0037 | Retro adjustment dihitung sebagai komponen periode berjalan | MVP |
| PAY-0038 | PPh 21 TER konsisten sesuai kategori & konfigurasi | MVP |
| PAY-0039 | Rekalkulasi tahunan Desember menghasilkan kurang/lebih bayar | MVP |
| PAY-0040/0041 | BPJS otomatis, ikut effective date kepesertaan | MVP |
| PAY-0042 | Validasi band SUSU dengan warning out-of-range | MVP |
| PAY-0044/0045 | Slip terproteksi, tidak bisa diakses user lain, percobaan tercatat | MVP |
| PAY-0046/0047 | Klaim approved & cicilan pinjaman otomatis masuk run | MVP |
| PAY-0048/0049 | THR prorata & bonus multiplier rating | MVP |
| PAY-0050/0051 | Bank file multi-format, rekening invalid → exception | MVP |
| PAY-0052 | Self-approval payroll ditolak sistem | MVP |
| E2E-0148/0149/0151 | Attendance→payroll, overtime→payroll, claim→payroll end-to-end | MVP |
| PAY-0043 | Salary increment planning workflow | Wave 2 |

---

## 9. Keputusan Teknis

- **Kalkulasi = queued job** (chunk 100 pegawai), progress via polling `usePoll` Inertia. Tidak ada WebSocket.
- **Formula engine deterministik**, bukan eval string: formula_items dievaluasi berurutan dengan struktur data, hasil dibulatkan ke Rupiah penuh (BIGINT) per komponen dengan aturan pembulatan configurable (default: round half up).
- **Snapshot dilakukan saat calculate, di-freeze saat lock.** Re-calculate pada status draft menghapus dan menulis ulang snapshot.
- **Semua tanggal disimpan UTC**, ditampilkan WIB. Periode payroll pakai `date` murni (tanpa jam).
- **Seed default per tenant baru:** komponen standar (Gaji Pokok, Tunjangan Jabatan, Tunjangan Makan, Tunjangan Transport, Lembur, BPJS employee-portion, PPh 21), PTKP & TER tahun berjalan, 1 payroll group bulanan default — tenant SME bisa langsung jalan tanpa setup dari nol.
