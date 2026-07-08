# AvanaHR — Design System & Engineering Conventions

Dokumen ini WAJIB dipatuhi di seluruh codebase. Claude Code: baca file ini sebelum membuat halaman/komponen/query apa pun.

---

# BAGIAN A — PERFORMA (WEB HARUS CEPAT)

## A1. Query & Eloquent — aturan keras

1. **Eager loading WAJIB.** Setiap query yang relasinya diakses di view/resource harus `with()` eksplisit. Aktifkan guard global di `AppServiceProvider`:
```php
Model::preventLazyLoading(!app()->isProduction());
Model::preventSilentlyDiscardingAttributes(!app()->isProduction());
```
   N+1 di development = exception, bukan warning.
2. **Select kolom yang dipakai saja** pada list besar: `Employee::select(['id','employee_code','full_name','position_id','status'])->with('position:id,name')`.
3. **Dilarang query di dalam loop.** Butuh data per item → eager load, `loadMissing`, atau satu query agregat (`->withCount()`, `->withSum()`).
4. **Pagination server-side WAJIB** untuk semua list: `->paginate(perPage)` — default 20, opsi 20/50/100. Dilarang `->get()` lalu paginate di frontend. Tabel > 100 ribu baris dengan infinite scroll → `cursorPaginate()`.
5. **Filter & sort di database**, bukan di collection. `->when($request->status, fn($q,$s) => $q->where('status',$s))`.
6. **Search**: `LIKE 'term%'` (prefix, kena index) untuk kode; nama pakai kolom terindeks atau FULLTEXT — hindari `LIKE '%term%'` pada tabel besar.
7. **Agregat dashboard** pakai query agregat langsung (raw `selectRaw`/`DB::table`), bukan load collection lalu hitung di PHP.
8. **Chunk untuk proses massal**: payroll calculate, export, rekap → `chunkById(100)` di dalam queued job. Dilarang load ribuan model sekaligus.
9. **Transaksi DB** untuk operasi multi-tabel (approval apply, payroll lock, movement apply): `DB::transaction()`.
10. Setiap penambahan query list baru → cek `EXPLAIN` memakai index (lihat 04_ERD.md aturan indexing). FK selalu ber-index.

## A2. Cache (Redis)

- Dashboard KPI per tenant: cache 15 menit, key `tenant:{id}:dashboard`, invalidate saat payroll lock/karyawan berubah signifikan (cukup TTL untuk MVP).
- Master jarang berubah (leave_types, shifts, komponen aktif, settings): `Cache::remember` 1 jam, invalidate on write (observer `Cache::forget`).
- Permission spatie: aktifkan cache bawaan spatie.
- Tarif referensi (TER, PTKP, BPJS): cache 24 jam.
- JANGAN cache data transaksional (absensi hari ini, saldo cuti) — selalu fresh.

## A3. Queue (Redis) — semua yang > 1 detik masuk queue

Wajib queued: kalkulasi payroll run, generate slip PDF massal, export Excel/PDF > 3.000 baris, kirim email/push massal, rekap absensi harian, import fingerprint. Job idempotent + `WithoutOverlapping` untuk payroll run. Progress polling via kolom status/persentase di record.

## A4. Frontend (Inertia + React)

- **Partial reload** untuk filter/sort/paginate: `router.get(url, params, { only: ['employees','filters'], preserveState: true, preserveScroll: true })` — jangan reload seluruh props.
- **Deferred props** (Inertia v2) untuk data sekunder halaman (chart dashboard, count badge): `Inertia::defer()`.
- **Debounce 300 ms** pada input search sebelum request.
- **Polling** hanya di halaman yang butuh (progress payroll run, dashboard absensi hari ini): `usePoll(5000)` dan berhenti saat selesai.
- `useMemo`/`useCallback` untuk kolom DataTable & handler; komponen berat di-`lazy()`.
- Aset: Vite code-splitting per halaman (bawaan Inertia); gambar avatar pakai thumbnail (intervention/image, 128px) bukan file asli.
- Response Inertia hanya kirim field yang dipakai UI → selalu lewat **API Resource / DTO**, jangan lempar model mentah (mencegah bocor field + payload besar).

## A5. Lain-lain

- OPcache on, `config:cache`, `route:cache`, `event:cache` di production.
- Kolom encrypted tidak bisa difilter DB → sediakan kolom hash (lihat ERD) bila perlu dicari.
- File user (lampiran, slip) diakses via **signed URL** temporary, bukan path publik.

---

# BAGIAN B — UI/UX

## B1. Fondasi

| Aspek | Ketentuan |
| --- | --- |
| Font | **Poppins** (Google Fonts / self-host), fallback sans-serif |
| Mode | **Light mode only** — jangan implement dark mode |
| Komponen | **shadcn/ui** untuk SEMUA komponen. Dilarang buat komponen primitif sendiri kalau shadcn punya |
| Icon | **lucide-react**, konsisten ukuran `h-4 w-4` di button, `h-5 w-5` di menu |
| Notifikasi | **Sonner** (`<Toaster richColors position="top-right" />`) |
| Bahasa UI | Bahasa Indonesia seluruhnya (label, placeholder, pesan error, empty state) |
| Warna brand | Primary biru `#2563EB` (blue-600), sesuai identitas AvanaHR |
| Radius | `rounded-lg` untuk card, `rounded-md` untuk kontrol |

## B2. Struktur Halaman — WAJIB di setiap page

Setiap halaman Inertia mengikuti template ini tanpa kecuali:

```tsx
<AppLayout>
  {/* 1. Breadcrumb — WAJIB di semua halaman */}
  <Breadcrumb>
    <BreadcrumbList>
      <BreadcrumbItem><BreadcrumbLink href={route('dashboard')}>Dashboard</BreadcrumbLink></BreadcrumbItem>
      <BreadcrumbSeparator />
      <BreadcrumbItem><BreadcrumbLink href={route('employees.index')}>Karyawan</BreadcrumbLink></BreadcrumbItem>
      <BreadcrumbSeparator />
      <BreadcrumbItem><BreadcrumbPage>Tambah Karyawan</BreadcrumbPage></BreadcrumbItem>
    </BreadcrumbList>
  </Breadcrumb>

  {/* 2. Header halaman: judul + deskripsi singkat + action utama di kanan */}
  <div className="flex items-center justify-between">
    <div>
      <h1 className="text-2xl font-semibold">Karyawan</h1>
      <p className="text-sm text-muted-foreground">Kelola data seluruh karyawan perusahaan</p>
    </div>
    <Button asChild><Link href={route('employees.create')}><Plus className="h-4 w-4" /> Tambah Karyawan</Link></Button>
  </div>

  {/* 3. Konten WAJIB dibungkus Card, dan halaman WAJIB full width */}
  <Card className="w-full">
    <CardHeader>...</CardHeader>
    <CardContent>...</CardContent>
  </Card>
</AppLayout>
```

Aturan keras:
- **Semua page full width** — container konten `w-full` (`max-w-none`), tidak ada max-width sempit. Padding layout `px-4 md:px-6`.
- **Semua konten dibungkus `<Card>`** — tabel, form, detail, chart. Tidak ada elemen "telanjang" di atas background.
- **Breadcrumb di semua halaman**, konsisten dari Dashboard → modul → halaman. Buat helper `<PageBreadcrumb items={[...]} />` supaya seragam.
- Layout: sidebar kiri (shadcn Sidebar) dengan menu grouped per modul + topbar (search, notifikasi bell, profil).

## B3. Tabel Data — WAJIB pakai shadcn Data Table (Base UI + TanStack Table)

Referensi: https://ui.shadcn.com/docs/components/base/data-table — implement satu komponen generik `<DataTable<T>>` dipakai semua modul.

Fitur wajib di setiap tabel list:
1. Kolom via `ColumnDef<T>` TanStack, header sortable (klik header toggle asc/desc → server-side).
2. Toolbar: input search (debounced, placeholder "Cari nama atau kode..."), filter dropdown (status/cabang/unit — `Select` shadcn), tombol Reset filter.
3. Pagination server-side (Laravel paginator → komponen pagination shadcn): info "Menampilkan 1–20 dari 134 data", pilihan per halaman 20/50/100.
4. Kolom aksi paling kanan, sticky bila tabel scroll horizontal.
5. Empty state: icon + teks "Belum ada data" + tombol aksi tambah bila relevan.
6. Loading state: skeleton rows (shadcn `Skeleton`), bukan spinner kosong.
7. Row klik (area nama) = shortcut ke halaman detail.
8. Kolom uang rata kanan, format Rupiah; kolom tanggal format `d MMM yyyy` WIB; kolom status pakai `Badge`.

## B4. Warna Tombol Aksi — STANDAR SELURUH APLIKASI

Semua tombol aksi WAJIB icon + teks (lucide + label), ukuran `size="sm"` di dalam tabel, default di halaman. Warna via varian kustom (tambahkan ke `buttonVariants`):

| Aksi | Warna | Kelas Tailwind | Icon |
| --- | --- | --- | --- |
| **Tambah / Simpan / Submit** | Biru (primary) | `bg-blue-600 hover:bg-blue-700 text-white` | `Plus` / `Save` / `Send` |
| **Edit** | **Hijau** | `bg-green-600 hover:bg-green-700 text-white` | `Pencil` |
| **Hapus** | **Merah** | `bg-red-600 hover:bg-red-700 text-white` | `Trash2` |
| **Detail / Lihat** | **Oranye** | `bg-orange-500 hover:bg-orange-600 text-white` | `Eye` |
| **Approve / Setujui** | Teal | `bg-teal-600 hover:bg-teal-700 text-white` | `CheckCircle2` |
| **Reject / Tolak** | Rose outline | `border border-rose-600 text-rose-600 hover:bg-rose-50` | `XCircle` |
| **Export / Download** | Ungu | `bg-violet-600 hover:bg-violet-700 text-white` | `Download` |
| **Import / Upload** | Indigo | `bg-indigo-600 hover:bg-indigo-700 text-white` | `Upload` |
| **Print / Cetak** | Sky | `bg-sky-600 hover:bg-sky-700 text-white` | `Printer` |
| **Proses / Hitung (payroll)** | Cyan | `bg-cyan-600 hover:bg-cyan-700 text-white` | `Calculator` |
| **Lock / Kunci** | Slate gelap | `bg-slate-700 hover:bg-slate-800 text-white` | `Lock` |
| **Batal / Kembali** | Outline netral | `variant="outline"` | `ArrowLeft` / `X` |
| **Filter / Reset** | Ghost | `variant="ghost"` | `Filter` / `RotateCcw` |

Aturan:
- Konsistensi mutlak — Edit selalu hijau di mana pun, tanpa pengecualian.
- **Hapus & aksi destruktif WAJIB konfirmasi** `AlertDialog` shadcn: judul "Hapus data ini?", deskripsi menyebut nama data, tombol konfirmasi merah "Ya, Hapus", batal outline.
- Aksi ireversibel besar (Lock payroll, Approve payroll) → AlertDialog + ringkasan dampak (jumlah karyawan, total net).
- Tombol disabled saat submit + spinner `Loader2 animate-spin` + teks berubah ("Menyimpan...").

## B5. Form — standar

1. **Halaman terpisah** untuk create/edit (no-modal CRUD). Modal hanya untuk konfirmasi & quick-view ringan.
2. **Placeholder WAJIB di semua input**: contoh nyata — "Masukkan nama lengkap", "cth: 3201xxxxxxxxxxxx", "Pilih departemen".
3. **Field wajib**: label + tanda **`*` merah** — komponen `<RequiredLabel>Nama Lengkap</RequiredLabel>` → `Nama Lengkap <span className="text-red-500">*</span>`.
4. Error validasi: border input merah + pesan merah kecil di bawah field (dari error bag Inertia, Bahasa Indonesia) + Sonner error "Periksa kembali isian formulir".
5. Layout form: grid 2 kolom di desktop (`grid md:grid-cols-2 gap-4`), 1 kolom mobile; section dipisah `Separator` + sub-judul; tombol aksi kanan-bawah dalam `CardFooter` (Batal outline kiri, Simpan primary kanan).
6. **Date picker WAJIB shadcn** (`Calendar` + `Popover`), locale `id`, format tampil `d MMMM yyyy`, kirim ke server ISO `yyyy-MM-dd`. Range picker untuk periode (cuti, payroll).
7. **Select** pakai shadcn `Select`; daftar panjang (karyawan, posisi) pakai `Combobox` (Command + Popover) dengan search server-side async.
8. **Input Rupiah — komponen wajib `<CurrencyInput>`**:
   - Tampilan terformat live: ketik `5000000` → tampil `5.000.000`, prefix tetap `Rp` di kiri dalam input.
   - `inputMode="numeric"`, hanya digit; paste dibersihkan dari non-digit.
   - Value dikirim ke server sebagai **integer** (BIGINT), bukan string terformat.
   - Rata kanan. Helper display global: `formatRupiah(5000000)` → `Rp 5.000.000` (Intl.NumberFormat 'id-ID', tanpa desimal).
9. Upload file: dropzone + preview nama/ukuran, validasi tipe & max size di client + server, progress bar.
10. Form kotor + navigasi keluar → konfirmasi "Perubahan belum disimpan".

## B6. Sonner — standar notifikasi

`<Toaster richColors position="top-right" closeButton />` di layout. Pemakaian:

| Kejadian | Pemanggilan | Warna |
| --- | --- | --- |
| Berhasil simpan/update/hapus/approve | `toast.success('Data karyawan berhasil disimpan')` | **Hijau** |
| Peringatan (di luar geofence, mendekati batas, out-of-range band) | `toast.warning('Gaji di luar rentang band grade G3')` | **Oranye** |
| Gagal / error server / validasi | `toast.error('Gagal menyimpan data. Periksa kembali isian.')` | **Merah** |
| Informasi netral (proses dimulai) | `toast.info('Perhitungan payroll dimulai...')` | Biru |
| Proses panjang | `toast.promise(promise, { loading: 'Menghitung...', success: 'Selesai', error: 'Gagal' })` | — |

Aturan: pesan spesifik menyebut objeknya ("Cuti Andi Pratama disetujui"), bukan generik ("Berhasil"). Flash message dari Laravel (`session()->flash('success', ...)`) di-render otomatis ke Sonner via middleware `HandleInertiaRequests` + hook di layout.

## B7. Komponen & Pola Lain

- **Badge status** konsisten: pending = `bg-amber-100 text-amber-800`, approved/aktif/hadir = `bg-green-100 text-green-800`, rejected/nonaktif/alfa = `bg-red-100 text-red-800`, draft = `bg-slate-100 text-slate-700`, locked/paid = `bg-blue-100 text-blue-800`, terlambat = `bg-orange-100 text-orange-800`. Buat `<StatusBadge status={...} />` terpusat.
- **Halaman detail**: header Card (nama + badge status + tombol aksi kanan), `Tabs` shadcn untuk section (Profil / Kepegawaian / Payroll / Kehadiran / Riwayat).
- **Skeleton** untuk semua loading (list, card KPI, chart) — dilarang layar putih.
- **Empty state** ilustratif: icon lucide besar muted + judul + deskripsi + CTA.
- **Angka & tanggal**: Rupiah `Rp 1.234.567`; tanggal `5 Juli 2026`; datetime `5 Jul 2026, 08:30 WIB`; durasi `2 jam 30 menit`.
- **Chart** (dashboard): recharts, warna dari palet brand (blue/teal/violet/orange), tooltip berformat Rupiah/persen.
- **Responsif**: sidebar collapse di mobile (Sheet), tabel scroll horizontal dengan kolom aksi sticky, form 1 kolom.
- **Aksesibilitas dasar**: semua input berlabel (`htmlFor`), tombol icon-only diberi `aria-label` + `Tooltip`, fokus ring jangan dihilangkan.
- **Konfirmasi navigasi keluar payroll run yang sedang draft-edit**.

## B8. Struktur Kode Frontend

```
resources/js/
  components/ui/          ← shadcn (generated, jangan edit manual berlebihan)
  components/shared/      ← DataTable, PageBreadcrumb, StatusBadge, CurrencyInput,
                             RequiredLabel, DatePicker, ConfirmDialog, EmptyState, PageHeader
  layouts/AppLayout.tsx
  pages/{modul}/{Index,Create,Edit,Show}.tsx
  lib/format.ts           ← formatRupiah, formatDate(WIB), formatDuration
  types/                  ← interface per entity (generate dari resource)
```

## B9. Struktur Kode Backend (Service-Repository-Action)

```
app/
  Actions/{Modul}/        ← satu aksi = satu class (CalculatePayrollRun, ApplyEmployeeMovement)
  Services/{Modul}/       ← orkestrasi bisnis (PayrollCalculationService, AttendanceSummaryService)
  Repositories/{Modul}/   ← akses data + eager loading terpusat
  Http/Controllers/       ← tipis: validasi (FormRequest) → action/service → response Inertia
  Http/Requests/          ← pesan validasi Bahasa Indonesia di method messages()
  Http/Resources/         ← DTO ke frontend, HANYA field yang dipakai
  Models/ (trait: BelongsToTenant, Auditable)
  Enums/                  ← status, permission, tipe komponen
```

Route: resource per modul, nama konsisten `employees.index|create|store|show|edit|update|destroy` + custom action (`payroll-runs.calculate`, `.approve`, `.lock`). Permission middleware per route.

---

# BAGIAN C — CHECKLIST SETIAP HALAMAN BARU (Claude Code wajib verifikasi)

- [ ] Breadcrumb ada dan benar
- [ ] Konten dibungkus Card, halaman full width
- [ ] Tabel pakai DataTable generik: search + filter + sort + pagination server-side
- [ ] Semua input ada placeholder; field wajib ada `*` merah
- [ ] Tombol aksi sesuai tabel warna (edit hijau, hapus merah, detail oranye, dst) + icon + teks
- [ ] Hapus/aksi destruktif pakai AlertDialog konfirmasi
- [ ] Date picker shadcn locale id; input uang pakai CurrencyInput
- [ ] Sonner: success hijau / warning oranye / error merah, pesan spesifik Bahasa Indonesia
- [ ] Query: eager loading eksplisit, select kolom perlu, paginate server-side, filter via `when()`
- [ ] Index DB tersedia untuk filter/sort yang dipakai (cek 04_ERD.md)
- [ ] Response lewat Resource (bukan model mentah)
- [ ] Loading skeleton + empty state
- [ ] Authorization: permission check di route + policy
- [ ] Feature test Pest untuk happy path + validasi utama
