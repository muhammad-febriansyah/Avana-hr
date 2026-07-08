# AvanaHR — User Stories (Wave 1 MVP)

Role: **Super Admin** (platform), **Company Admin**, **HR Admin**, **Payroll Admin**, **Approver/Finance**, **Manager**, **Employee**.
Format: As a [role], I want [goal], so that [benefit]. AC = Acceptance Criteria (dirujuk ke ID Skenario UAT bila ada).

---

## EPIC 0 — Platform & Fondasi

**US-001** — As a Super Admin, I want membuat tenant baru dengan paket (Essential/Professional/Enterprise 360), so that klien baru langsung bisa dipakai.
AC: tenant aktif dengan seed default (role, permission, komponen payroll standar, jenis cuti standar, 1 payroll group); data kosong terisolasi (QA-0112); fitur di luar paket tersembunyi/terkunci.

**US-002** — As a Company Admin, I want mengelola role & permission custom granular (menu/aksi), so that akses sesuai kebijakan perusahaan.
AC: role custom bisa dibuat dari daftar permission; user role view-only tidak bisa edit (QA-0113).

**US-003** — As a Company Admin, I want mengkonfigurasi workflow approval per jenis transaksi tanpa coding, so that rute persetujuan sesuai struktur kami.
AC: rule by grade/jumlah/lokasi; multi-level berurutan benar (QA-0010); perubahan rule hanya berlaku untuk pengajuan baru (QA-0119).

**US-004** — As a Manager, I want mendelegasikan approval saat cuti/dinas, so that persetujuan tim tidak macet.
AC: delegasi berlaku sesuai periode; audit mencatat approver asli + delegasi (QA-0011, BR-31).

**US-005** — As a Company Admin, I want setiap perubahan data sensitif tercatat di audit trail, so that ada jejak untuk audit.
AC: old value, new value, actor, timestamp, IP/device (QA-0114).

**US-006** — As a system, semua query ter-scope `tenant_id`; percobaan akses lintas tenant ditolak & dicatat (QA-0111). MFA opsional per tenant (QA-0116).

## EPIC 1 — Organisasi & Karyawan

**US-101** — As an HR Admin, I want membuat struktur organisasi (company → divisi → departemen → unit) dan posisi dengan garis pelaporan, so that org chart & rute approval terbentuk.
AC: unit bisa dipakai pada assignment (QA-0007); circular reporting ditolak dengan pesan jelas (QA-0009); perubahan mendukung tanggal efektif (BR-02.4).

**US-102** — As an HR Admin, I want membuat profil karyawan lengkap (personal, employment, payroll, kontak darurat), so that ada single source of truth.
AC: Employee ID unik auto-generate; duplikasi NIK/KTP/NPWP/email ditolak per field (QA-0001, QA-0002); custom field per tenant (BR-01.3).

**US-103** — As an Employee, I want mengajukan perubahan data pribadi (alamat, rekening) via ESS, so that data saya selalu benar.
AC: field sensitif masuk status Pending → aktif setelah HR approve (maker-checker, QA-0003, QA-0092).

**US-104** — As an HR Admin, I want memproses mutasi/promosi/demosi dengan tanggal efektif via workflow, so that riwayat karir tercatat dan payroll terdampak benar.
AC: struktur & atasan ikut berubah per effective date (QA-0004, QA-0005); dampak cost center terlihat di payroll periode berjalan (E2E-0146).

**US-105** — As an HR Admin, I want memproses resign + exit clearance, so that status, akses, dan final settlement beres.
AC: status → resigned per effective date, akses ESS nonaktif, data historis tetap ada (QA-0006, E2E-0147).

**US-106** — As an HR Admin, I want mengelola cabang/lokasi kerja dengan koordinat + radius geofence, so that absensi tervalidasi lokasi.
AC: karyawan terikat minimal satu lokasi; absensi divalidasi terhadap geofence lokasinya (BR-27).

**US-107** — As an HR Admin, I want data master (grade, posisi) effective-dated, so that transaksi memakai nilai yang berlaku pada tanggalnya, bukan tanggal input (QA-0016).

## EPIC 2 — Shift, Kehadiran & Cuti

**US-201** — As an HR Admin, I want membuat pola shift (office/shift/roster, termasuk rotasi 2-2-3) dan meng-assign ke karyawan/tim, so that jadwal jadi acuan kehadiran & lembur.
AC: jadwal ter-generate sesuai pola, tidak bentrok libur/cuti (QA-0024).

**US-202** — As an Employee, I want clock-in/out dari aplikasi Flutter dengan verifikasi wajah + GPS, so that absensi saya sah dan cepat.
AC: face match on-device (MobileFaceNet, cosine similarity, threshold configurable), confidence score tersimpan (QA-0018); wajah orang lain ditolak & dicatat suspicious (QA-0019); koordinat + waktu server tersimpan (QA-0017); di luar geofence → ditandai untuk review (BR-06); duplicate clock-in ditolak (QA-0021).

**US-203** — As an Employee, I want absen tetap bisa saat offline, so that sinyal buruk tidak menghalangi.
AC: event masuk queue lokal dengan UUID; sync tanpa duplikasi (QA-0020).

**US-204** — As an Employee, I want mengajukan koreksi kehadiran (lupa absen/clock-out) dengan alasan, so that rekap saya benar.
AC: masuk workflow, data final tidak berubah sebelum approve (QA-0022); setelah approve record berubah + audit (QA-0023, BR-35).

**US-205** — As an Employee, I want mengajukan cuti dengan cek saldo otomatis, so that saya tahu langsung disetujui/tidak.
AC: saldo pending berkurang sementara sesuai policy (QA-0026); melebihi saldo ditolak/dialihkan unpaid sesuai konfigurasi (QA-0027); saldo & kalender tim terupdate saat approve/cancel + audit (E2E-0150); saldo menampilkan available/pending/used/expired (QA-0095).

**US-206** — As a Manager, I want melihat kalender cuti tim dengan warning overlap, so that staffing aman (QA-0028).

**US-207** — As an Employee, I want mengajukan lembur sebelum bekerja; jam aktual dihitung dari attendance, so that lembur dibayar akurat.
AC: pengajuan masuk workflow (QA-0029); clock-out lebih awal → jam lembur mengikuti aktual + policy (QA-0030); hanya lembur approved masuk payroll (E2E-0149).

**US-208** — As an HR Admin, I want mengimpor file mesin fingerprint sebagai sumber tambahan, so that perangkat lama tetap terpakai.
AC: data valid ter-import, error masuk exception list; import ulang tidak duplikat (QA-0033, QA-0034).

**US-209** — As a Manager, I want approve pengajuan tim secara bulk dari mobile/web, so that cepat.
AC: yang eligible approved, yang gagal ada alasan jelas (QA-0097).

## EPIC 3 — Payroll (mengadopsi arsitektur BPR 4 lapis)

**US-301** — As a Payroll Admin, I want mengelola Master Komponen penerimaan & potongan dengan dasar perhitungan Formula/Tabel/Fixed, flag pajak, prorate, tampilan slip, dan perilaku pasca-nonaktif, so that komponen gaji fleksibel per tenant tanpa coding.
AC: kode unik per tenant; min/max hasil; effective date.

**US-302** — As a Payroll Admin, I want merakit Master Formula dari kombinasi komponen (× pengali, + penambah, opsi prorate per item), so that rumus tunjangan bisa apa saja.

**US-303** — As a Payroll Admin, I want mengelola Master Gaji (payroll group): checklist komponen, periode, tanggal cut-off, pembagi hari kerja, sumber absensi/lembur (bulan berjalan vs bulan lalu), so that aturan hitung per kategori pegawai jelas.

**US-304** — As a Payroll Admin, I want setting Nilai Komponen via mapping atribut (grade/posisi/status/lokasi) + override khusus per karyawan, so that nilai ter-resolve otomatis.
AC: prioritas: override karyawan > mapping paling spesifik; override butuh approval + lampiran SK.

**US-305** — As a Payroll Admin, I want mencatat riwayat gaji pokok (tanggal, No. SK, sesuai UMR ya/tidak, nilai, lampiran), so that riwayat kenaikan terdokumentasi (pola BPR). AC: append-only; divalidasi terhadap band SUSU dengan warning out-of-range (QA-0042).

**US-306** — As a Payroll Admin, I want menjalankan payroll run (bulanan/mingguan/2-mingguan): tarik absensi terkunci + lembur approved → hitung gross, PPh 21 TER, BPJS, potongan → review rekap & anomali, so that gaji akurat.
AC: QA-0035; run memakai attendance final/locked (E2E-0148); TER konsisten per kategori (QA-0038); rekalkulasi tahunan Desember (QA-0039); BPJS otomatis + effective date kepesertaan (QA-0040/0041); prorate join/resign tengah periode.

**US-307** — As an Approver/Finance, I want approve payroll run yang bukan buatan saya, so that kontrol berjalan.
AC: self-approval ditolak sistem (QA-0052); setelah approve → lock, data tidak berubah tanpa unlock berotorisasi (QA-0036).

**US-308** — As a Payroll Admin, I want koreksi gaji pasca-lock hanya via pengajuan ber-approval yang jadi adjustment/retro periode berikutnya, so that run final tak pernah diedit (QA-0037, pola BPR).

**US-309** — As an Employee, I want melihat & mengunduh slip gaji saya dari ESS (PDF terproteksi), so that slip aman.
AC: hanya slip milik sendiri; akses URL orang lain ditolak & dicatat security log (QA-0044, QA-0045).

**US-310** — As a Payroll Admin, I want generate bank transfer file multi-format setelah final, so that pembayaran cepat.
AC: total = net final; rekening invalid masuk exception, tak ikut file (QA-0050, QA-0051).

**US-311** — As a Payroll Admin, I want run THR prorata & bonus (run irregular, slip terpisah), so that pembayaran khusus rapi (QA-0048, QA-0049).

**US-312** — As a Payroll Admin, I want laporan proses gaji, rekap bulanan, gaji detail, laporan PPh 21 & BPJS dari data final saja (QA-0108, QA-0109).

## EPIC 4 — ESS / MSS / Dashboard

**US-401** — As an Employee, I want dashboard ESS: status absen hari ini, saldo cuti, pengajuan terakhir, pengumuman, so that semua kebutuhan harian satu layar.

**US-402** — As a Manager, I want dashboard MSS: pending approval, kehadiran tim hari ini, kalender cuti, so that keputusan cepat; data hanya bawahan sesuai garis pelaporan (QA-0098, QA-0110).

**US-403** — As a Company Admin/HR, I want dashboard perusahaan: total karyawan, biaya payroll & tren, kehadiran rata-rata, kontrak akan berakhir, so that kondisi organisasi terlihat (referensi mockup HumanCore).

**US-404** — As a user, I want notifikasi (push mobile + email) untuk approval, hasil pengajuan, dan slip terbit, dengan fallback antar channel (QA-0101).

---

## Prioritas

P0 (blocker rilis): US-001, 002, 003, 005, 006, 101, 102, 103, 104, 105, 106, 201, 202, 204, 205, 207, 301–310, 402.
P1: US-004, 107, 203, 206, 208, 209, 311, 312, 401, 403, 404.
