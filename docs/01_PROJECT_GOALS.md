# AvanaHR — Project Goals

**Produk:** AvanaHR — Multi-tenant HRIS/HCM SaaS untuk pasar Indonesia
**Tagline:** Advancing People, Empowering Growth
**Pemilik:** PT Simetric Consulting Group
**Referensi:** BRD HCMS Detail v3 (36 modul, 8 domain), Manual Book BPR-HRMS Modul Payroll, Skenario UAT (168 test case), List Modul & Paket

---

## 1. Visi Produk

Platform Human Capital Management hire-to-retire yang lengkap, patuh regulasi Indonesia (PPh 21 TER, BPJS, UU Ketenagakerjaan, UU PDP), namun cukup sederhana untuk diadopsi UMKM/SME — dengan jalur upgrade mulus ke kebutuhan enterprise.

## 2. Masalah yang Diselesaikan

1. Perusahaan Indonesia masih mengelola HR manual & terfragmentasi (Excel, WhatsApp, mesin absen offline).
2. Solusi enterprise (SAP, Workday) mahal & kompleks; solusi murah tidak patuh regulasi lokal (TER, BPJS, THR, SUSU Permenaker).
3. Payroll manual rawan salah hitung pajak & iuran → risiko denda dan sengketa karyawan.
4. Absensi mudah dimanipulasi (titip absen) tanpa validasi wajah + lokasi.

## 3. Target Pengguna

| Segmen | Ukuran | Paket |
| --- | --- | --- |
| SME/UMKM 10–100 karyawan | Pasar utama | Essential (HC Starter) |
| Perusahaan menengah 100–500 karyawan | Pertumbuhan | Professional (HC Growth) |
| Enterprise / grup multi-entitas 500+ | Strategis | Enterprise 360 (HC Strategic, termasuk AI Features) |

## 4. Tujuan Bisnis (dari BRD)

1. Platform HCMS terintegrasi hire-to-retire.
2. Kepatuhan penuh regulasi ketenagakerjaan, perpajakan (PPh 21 TER), dan BPJS.
3. Mengurangi biaya & waktu administrasi HR secara signifikan.
4. Keputusan berbasis data (workforce analytics + AI insights) & pengalaman karyawan yang baik (ESS mobile).
5. SaaS multi-tenant scalable dari SME ke enterprise dengan recurring revenue.

## 5. Lingkup MVP (Wave 1 — dari BRD Bagian 10)

Modul: BR-01 (HR Core), BR-02 (Organisasi), BR-03 (Siklus Karyawan), BR-06 (Kehadiran), BR-07 (Cuti), BR-08 (Shift), BR-09 (Lembur), BR-11 (Payroll & PPh 21 TER), BR-12 (BPJS), BR-13 (Struktur & Skala Upah), BR-24 (ESS/MSS + Mobile), BR-27 (Cabang & Geofence), BR-35 (Koreksi Kehadiran).

Platform lintas-modul MVP: multi-tenancy single-DB row-level, RBAC granular (spatie), approval engine polymorphic multi-level + delegasi, audit trail, notifikasi (push + email; WhatsApp via interface pluggable), feature gating per paket.

**Di luar MVP (Wave 2/3):** Rekrutmen/ATS, Onboarding, Klaim, Pinjaman lanjutan, SPPD, Dokumen, Helpdesk, Performance/OKR, LMS, Talent & Succession, Survei, Analytics lanjutan + AI Features, Multi-entitas grup, Asset, CRM.

## 6. Keputusan Arsitektur Kunci

| Keputusan | Pilihan | Alasan |
| --- | --- | --- |
| Tenancy | Single DB, row-level (`tenant_id` + global scope) | Sederhana dioperasikan, cukup untuk isolasi + murah di shared infra |
| Web stack | Laravel 13 + React + Inertia + TypeScript + Tailwind v4 + shadcn/ui | Stack standar tim |
| Mobile karyawan | Flutter (GetX) | ESS: absensi, cuti, lembur, slip |
| Face recognition | On-device: ML Kit (deteksi + liveness) + MobileFaceNet TFLite (embedding) | Kepatuhan UU PDP — foto wajah mentah tidak dikirim/disimpan server |
| Realtime | Polling (`usePoll` Inertia / TanStack Query) | Tanpa WebSocket, kompatibel shared hosting |
| Pola kode | Service-Repository-Action, no-modal CRUD (halaman terpisah) | Konvensi tim |
| Uang | BIGINT Rupiah | Tanpa floating point |
| Waktu | Simpan UTC, tampil WIB | Konsisten |
| Payroll engine | Adopsi arsitektur 4 lapis BPR-HRMS + TER + snapshot immutable | Terbukti di lapangan |
| Data transaksional | Snapshot fields, immutable setelah final | Audit & kepatuhan |
| Master data | Soft delete | Integritas historis |

## 7. Kriteria Sukses

| Indikator | Target |
| --- | --- |
| Akurasi payroll | 0 kesalahan hitung PPh 21/BPJS pada UAT (19/19 test case PAY lolos) |
| UAT keseluruhan | 84 P0 test case lolos 100% sebelum go-live |
| Performa web | TTFB < 300 ms, halaman list < 1 detik (p95), payroll run 1.000 karyawan < 5 menit |
| Absensi mobile | Verifikasi wajah < 2 detik on-device; false accept rate < 0,1% |
| Adopsi | > 80% karyawan tenant aktif memakai ESS dalam 30 hari |
| Bisnis | Tenant berbayar bertambah tiap kuartal (MRR tumbuh) |

## 8. Risiko & Mitigasi (dari BRD Bagian 11)

| Risiko | Mitigasi |
| --- | --- |
| Kesalahan kepatuhan pajak/BPJS | Tabel tarif configurable + seed resmi, rekalkulasi tahunan, validasi ahli, 19 test case payroll |
| Scope creep 36 modul | Disiplin wave; MVP hanya 13 modul |
| Adopsi rendah | Mobile-first ESS, onboarding wizard tenant, seed data default |
| Perubahan regulasi | Tarif TER/PTKP/BPJS/UMR sebagai data (effective-dated), bukan hardcode |
| Manipulasi absensi | Face recognition + liveness + geofence server-side + suspicious attendance log |
