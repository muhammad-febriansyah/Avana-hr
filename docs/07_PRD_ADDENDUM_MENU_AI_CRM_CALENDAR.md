# AvanaHR — PRD Addendum: Menu Management, AI Config, CRM Pipeline, Calendar

Dokumen tambahan atas 03_PRD.md dan 04_ERD.md. Semua konvensi (tenant scope, indexing, UI/UX 05_DESIGN_SYSTEM.md) berlaku penuh.

---

## M10 — Dynamic Menu Management (2 level: Super Admin & Admin Tenant)

### Konsep
Sidebar TIDAK hardcode. Menu dirender dari database dengan 3 lapis penentu, dievaluasi berurutan:

```
1. Menu Registry (platform)   → daftar semua menu yang ADA di sistem (dikelola Super Admin)
2. Plan/Tenant availability   → menu mana yang TERSEDIA untuk tenant (Super Admin, ikut paket)
3. Tenant menu settings       → menu mana yang TAMPIL, urutan, label, grouping per role (Admin Tenant)
4. Runtime                    → permission user + feature gate (final filter)
```

Prinsip: Admin Tenant hanya bisa mengatur di dalam batas yang di-enable Super Admin. Admin Tenant tidak bisa memunculkan menu yang tidak ada di paketnya.

### Kemampuan Super Admin (panel `/platform/menus`)
1. CRUD menu registry: kode unik, label default, icon (nama lucide), route name, parent (nested max 2 level), sort order, `permission_code` yang dibutuhkan, `feature_code` (gate paket), flag `is_core` (tidak bisa disembunyikan tenant, mis. Dashboard, Pengaturan).
2. Mengatur ketersediaan menu per paket (Essential/Professional/Enterprise 360) — otomatis mengikuti `plan_features`, bisa override per tenant (enable/disable menu spesifik untuk 1 tenant, mis. trial fitur).
3. Preview sidebar "sebagai tenant X role Y".

### Kemampuan Admin Tenant (halaman `/settings/menus`)
1. Show/hide menu (kecuali `is_core`).
2. Ubah urutan (drag & drop, dnd-kit) dan pindah grouping.
3. Rename label (alias per tenant, mis. "Karyawan" → "SDM").
4. Atur visibilitas per role (checklist role default + custom) — ini lapisan UX di atas permission; permission tetap otoritas akhir (menu tampil tapi user tanpa permission → tetap 403).
5. Reset ke default.

### Skema
```
menus (platform, tanpa tenant_id)
  id, code UNIQUE, parent_id FK self nullable, label_default, icon VARCHAR,
  route_name VARCHAR nullable (null = group header), permission_code VARCHAR nullable,
  feature_code VARCHAR nullable, sort_order SMALLINT, is_core BOOLEAN, is_active BOOLEAN
  INDEX(parent_id, sort_order)

tenant_menu_overrides (Super Admin per-tenant override)
  tenant_id FK, menu_id FK, is_enabled BOOLEAN — UNIQUE(tenant_id, menu_id)

tenant_menu_settings (Admin Tenant)
  tenant_id FK, menu_id FK, is_visible BOOLEAN DEFAULT true, label_alias VARCHAR nullable,
  sort_order SMALLINT nullable, parent_override_id FK nullable
  UNIQUE(tenant_id, menu_id)

tenant_menu_role_visibility
  tenant_menu_setting_id FK, role_id FK — UNIQUE(setting_id, role_id)
  (tidak ada baris = tampil untuk semua role yang punya permission)
```

### Implementasi
- `MenuService::forUser(User $user)`: registry → filter plan/tenant availability → apply tenant settings (visible, alias, order) → filter role visibility → filter `can(permission_code)` + `hasFeature(feature_code)` → tree JSON.
- **Cache per tenant per role**: key `tenant:{id}:menu:role:{roleIds-hash}`, TTL 1 jam, invalidate on write (observer di 4 tabel menu).
- Sidebar React menerima tree dari shared Inertia props (`HandleInertiaRequests`), render icon via mapping nama-lucide → komponen (`lucide-react/dynamicIconImports` atau map manual, jangan import semua icon).
- Seeder registry menu = seluruh menu Wave 1 + addendum ini. Menu baru di rilis berikutnya = tambah baris seeder (bukan ubah kode sidebar).
- Route TETAP diproteksi permission middleware — menu hanya presentasi.

---

## M11 — AI Configuration (Super Admin) + fondasi AI Features

### Konsep
Super Admin mengatur provider & model AI di level platform; pemakaian fitur AI oleh tenant di-gate paket (Enterprise 360). Arsitektur provider-agnostic via driver pattern (`AiProviderInterface`: `chat()`, `stream()`), implementasi awal: Anthropic (Claude), OpenAI, Google Gemini, dan Custom (OpenAI-compatible base URL — untuk 9Router/OpenRouter/self-hosted).

### Halaman Super Admin `/platform/ai-settings`
1. **Providers**: CRUD koneksi — nama, driver (anthropic/openai/gemini/openai_compatible), base_url (untuk compatible), API key (encrypted, tampil masked, tombol "Test Koneksi"), status aktif.
2. **Model mapping per fitur AI**: tiap `ai_feature_code` dipetakan ke provider + model + parameter:
   - `hr_copilot` (chat tanya data HR) → mis. claude-sonnet-4-6, temperature 0.3, max_tokens 2000
   - `ai_insights` (narasi insight dashboard) → model hemat
   - `document_summary`, `job_description_writer` (Wave 3) → dst.
   Field: system_prompt_template (textarea), temperature, max_tokens, monthly_token_budget per tenant (0 = unlimited), fallback_provider_id nullable.
3. **Kontrol per tenant**: enable/disable AI per tenant (override gate paket), lihat pemakaian token bulan berjalan per tenant.
4. **Log & biaya**: tabel pemakaian (tenant, fitur, model, tokens in/out, durasi, status) + agregat bulanan.

### Skema
```
ai_providers (platform)
  id, name, driver ENUM(anthropic,openai,gemini,openai_compatible), base_url nullable,
  api_key TEXT encrypted, is_active BOOLEAN

ai_feature_configs (platform)
  id, feature_code VARCHAR UNIQUE, ai_provider_id FK, model VARCHAR,
  system_prompt TEXT, temperature DECIMAL(3,2), max_tokens INT,
  monthly_token_budget_per_tenant BIGINT DEFAULT 0, fallback_provider_id FK nullable,
  fallback_model VARCHAR nullable, is_active BOOLEAN

tenant_ai_settings
  tenant_id FK UNIQUE, is_enabled BOOLEAN, monthly_token_budget_override BIGINT nullable

ai_usage_logs (volume tinggi)
  tenant_id, user_id, feature_code, provider, model, input_tokens INT, output_tokens INT,
  duration_ms INT, status ENUM(success,error,budget_exceeded), error_message nullable, created_at
  INDEX(tenant_id, feature_code, created_at), INDEX(tenant_id, created_at)
```

### Aturan keamanan AI (WAJIB)
1. HR Copilot menjawab HANYA via tool calling ke query layer yang menjalankan scope tenant + permission user — AI tidak pernah diberi akses SQL bebas. Jawaban tidak boleh memuat data yang user-nya sendiri tidak boleh lihat (selaras QA-0110/0111).
2. Field sensitif (NIK, NPWP, rekening) TIDAK pernah dikirim ke provider AI.
3. Budget check sebelum call; lampaui budget → response ramah "Kuota AI bulan ini habis" (toast warning oranye).
4. Semua call tercatat di `ai_usage_logs`. API key hanya bisa dilihat ulang oleh Super Admin (masked by default).
5. Disclaimer tetap di UI: "AI dapat membuat kesalahan. Verifikasi hasilnya." (sesuai mockup HumanCore).

MVP addendum ini: bangun fondasi (config + driver + usage log) + 1 fitur pertama `ai_insights` (narasi rule-based metrics → LLM merangkai kalimat). HR Copilot chat = fase berikutnya.

---

## M12 — CRM Pipeline (Admin Tenant)

Sesuai kolom CRM di List_Modul: Pipeline Deal, Aktivitas & Riwayat Follow-up, Task Sales, Assign PIC & Kolaborasi.
Scope: CRM ringan per tenant (bukan pengganti CRM full). Gate fitur: `crm` (Professional ke atas — konfirmasi penempatan paket).

### Fitur
1. **Pipeline & Stage configurable**: tenant membuat pipeline (default: "Sales") dengan stages berurutan (default: Lead → Kontak → Penawaran → Negosiasi → Menang / Kalah). Stage punya nama, warna, urutan, flag `is_won` / `is_lost`.
2. **Deals**: judul, perusahaan/kontak (nama, telepon, email), nilai estimasi (BIGINT Rupiah, CurrencyInput), stage, PIC (user), expected close date, sumber, catatan.
3. **Kanban board** (`/crm/pipeline`): kolom per stage, drag & drop deal antar stage (dnd-kit) → update server + toast; header kolom menampilkan jumlah deal + total nilai per stage. Filter PIC/sumber/rentang nilai. Tampilan alternatif: list DataTable.
4. **Aktivitas & follow-up**: timeline per deal (catatan, telepon, meeting, email — manual log) + task follow-up ber-due date & assignee; task jatuh tempo muncul di dashboard CRM + notifikasi.
5. **Kolaborasi**: multiple member per deal (PIC utama + kolaborator), mention sederhana di catatan (Wave berikut).
6. **CRM Insights ringkas** (`/crm/dashboard`): total pipeline value per stage (funnel), deal menang/kalah bulan ini, win rate, task overdue.
7. Won/Lost wajib isi alasan; deal won → opsional trigger notifikasi ke Super Admin flow provisioning (selaras E2E-0156, integrasi penuh Wave berikut).

### Skema
```
crm_pipelines   : tenant_id, name, is_default BOOLEAN, deleted_at — UNIQUE(tenant_id, name)
crm_stages      : tenant_id, pipeline_id FK, name, color VARCHAR(7), sort_order SMALLINT,
                  is_won BOOLEAN, is_lost BOOLEAN — UNIQUE(pipeline_id, sort_order)
crm_deals       : tenant_id, pipeline_id FK, stage_id FK, title, company_name, contact_name,
                  contact_phone, contact_email, value BIGINT, source VARCHAR nullable,
                  owner_user_id FK, expected_close_date DATE nullable,
                  won_lost_reason TEXT nullable, closed_at nullable, deleted_at
                  INDEX(tenant_id, pipeline_id, stage_id), INDEX(tenant_id, owner_user_id),
                  INDEX(tenant_id, expected_close_date)
crm_deal_members: deal_id FK, user_id FK, role ENUM(owner,collaborator) — UNIQUE(deal_id, user_id)
crm_activities  : tenant_id, deal_id FK, user_id FK, type ENUM(note,call,meeting,email,stage_change),
                  body TEXT, occurred_at — INDEX(deal_id, occurred_at)
crm_tasks       : tenant_id, deal_id FK, title, assignee_user_id FK, due_date DATE,
                  status ENUM(open,done), completed_at nullable
                  INDEX(tenant_id, assignee_user_id, status, due_date)
```
Permission: `crm.view`, `crm.manage`, `crm.manage-pipeline`, `crm.view-all` (tanpa ini user hanya lihat deal miliknya/kolaborasinya).

---

## M13 — Calendar (Admin Tenant & semua user)

Kalender perusahaan terpadu (COE — Calendar of Events) di `/calendar`, view bulan/minggu (library: FullCalendar React atau react-big-calendar, styling disesuaikan shadcn).

### Sumber event (aggregated, toggle per layer dengan warna berbeda)
| Layer | Sumber | Warna | Siapa lihat |
| --- | --- | --- | --- |
| Hari libur | `holidays` (nasional + tenant) | Merah | Semua |
| Event perusahaan | `company_events` (CRUD Admin/HR) | Biru | Sesuai target |
| Cuti tim (approved) | `leave_requests` | Hijau | Manager: timnya; HR: semua; Employee: dirinya |
| Kontrak berakhir | `employee_contracts.end_date` | Oranye | HR/Admin |
| Tanggal gajian | `payroll_runs.payment_date` | Ungu | HR/Payroll |
| Ulang tahun karyawan | `employees.birth_date` | Pink | Semua (toggle off default) |
| Task CRM due | `crm_tasks` | Cyan | Pemilik task |

### Fitur
1. CRUD event perusahaan (Admin Tenant/HR): judul, deskripsi, tanggal/waktu (all-day atau berjam), lokasi, target (semua / unit / cabang), reminder H-1 (notifikasi push+email ke target).
2. Klik event → popover detail; event cuti link ke pengajuan; kontrak link ke karyawan.
3. Filter layer (checkbox berwarna di sisi kiri kalender), tersimpan per user (localStorage).
4. Mobile Flutter: kalender ESS versi ringkas (libur, event perusahaan, cuti sendiri).
5. Performa: endpoint `/calendar/events?start=&end=` per rentang tampilan — SATU query per layer dengan rentang tanggal terindeks; response digabung backend; jangan load setahun penuh sekaligus.

### Skema
```
company_events : tenant_id, title, description TEXT, starts_at, ends_at, is_all_day BOOLEAN,
                 location nullable, target ENUM(all,org_unit,branch), target_id BIGINT nullable,
                 created_by FK users, remind_before_hours SMALLINT nullable, deleted_at
                 INDEX(tenant_id, starts_at)
```

---

## Catatan: Struktur Organisasi untuk Admin Tenant

Sudah tercakup di **03_PRD.md M01.1** — CRUD hierarki Company→Division→Department→Unit + posisi + garis pelaporan + org chart visual, dan itu memang hak Admin Tenant / HR Admin (permission `organization.manage`). Tidak ada perubahan skema; addendum ini hanya menegaskan: menu "Struktur Organisasi" masuk registry menu dengan `permission_code = organization.manage` sehingga tampil untuk Admin Tenant, dan tersedia di semua paket.

---

## Penambahan Menu Registry (seed)

```
Platform (Super Admin): Dashboard Platform, Tenants, Plans & Fitur, Menu Registry, AI Settings, Usage & Logs
Tenant: Dashboard, Karyawan, Struktur Organisasi, Cabang & Lokasi, Kehadiran, Jadwal & Shift, Cuti,
        Lembur, Payroll (grup: Komponen, Master Gaji, Proses Gaji, Koreksi, Laporan), CRM (grup:
        Pipeline, Dashboard CRM, Task), Kalender, Pengumuman, Laporan, Pengaturan (grup: Menu,
        Role & Permission, Approval Workflow, Perusahaan)
```

## Dampak ke dokumen lain
- 04_ERD.md: tambahkan tabel M10–M13 di atas (bagian baru "Addendum").
- 02_USER_STORIES.md: epic baru EPIC 5 (Menu), EPIC 6 (AI Config), EPIC 7 (CRM), EPIC 8 (Calendar) — AC utama sudah tersirat di spesifikasi tiap modul di dokumen ini.
- 05_DESIGN_SYSTEM.md: kanban drag & drop (dnd-kit) mengikuti aturan yang sama — card, toast success saat pindah stage, konfirmasi saat drop ke stage Won/Lost.
- Feature gate: `menu_management` (semua paket, tenant-level), `ai_features` (Enterprise 360), `crm` (Professional+ — konfirmasi), `calendar` (semua paket).
