# KiraBisnes — Project Plan

> Web app pengurusan kewangan untuk freelancer solo (software / web / IT).
> Urus duit masuk & keluar, modal, perbelanjaan bisnes, tabung agihan automatik, dan "gaji sendiri".
> **Dwibahasa (Bahasa Melayu / English)** dan **Light / Dark / System mode**.
>
> **Untuk Claude Code:** Baca fail ini sepenuhnya sebelum mula. Bina **satu fasa pada satu masa**, ikut turutan.
> Jangan mula fasa seterusnya sebelum semua checklist & *acceptance criteria* fasa semasa lulus dan test hijau.
> Tandakan `[x]` pada checklist dalam fail ini bila siap. Kalau ada keputusan yang tak dinyatakan di sini,
> pilih yang paling ringkas & konsisten dengan seksyen **Konvensyen**, dan catat dalam seksyen **Decision Log** di bawah.

---

## 1. Ringkasan Produk

| Perkara | Keterangan |
|---|---|
| Nama | KiraBisnes |
| Pengguna | 1 pemilik bisnes (single-user). Multi-tenant **di luar skop** buat masa ini. |
| Bahasa UI | **Dwibahasa: Bahasa Melayu (`ms`, default) & English (`en`)** — boleh tukar bila-bila masa. Kod, nama table/column, komen kod dalam English. |
| Tema | **Light / Dark / System** — pilihan disimpan per user |
| Mata wang | MYR sahaja (Fasa 1–5). |
| Zon masa | `Asia/Kuala_Lumpur` |
| Format tarikh | Papar `d/m/Y` (cth 30/10/2026). Simpan `Y-m-d`. |

### Konsep teras: Tabung (Funds) & Agihan Automatik
Setiap **duit masuk** (income) diagihkan secara automatik ke "tabung maya" ikut peratus:

| Tabung (slug) | Nama (ms / en) | Default % | Guna untuk |
|---|---|---|---|
| `operating` | Operasi / Operating | 30 | Hosting, SaaS, internet, minyak/tol, subkontrak |
| `owner_pay` | Gaji Sendiri / Owner Pay | 40 | Duit yang pemilik ambil (owner's draw) |
| `tax` | Cukai / Tax | 10 | Simpanan untuk cukai LHDN |
| `emergency` | Kecemasan / Emergency | 10 | Buffer bulan perlahan |
| `capital` | Modal / Capital | 10 | Laptop, peralatan, kursus, pelaburan semula |

- Jumlah peratus **mesti = 100**.
- Duit fizikal kekal dalam **akaun sebenar** (bank / e-wallet / tunai). Tabung cuma label maya.
- Perbelanjaan (expense) tolak dari tabung yang dipilih (default `operating`).
- Owner's draw tolak dari `owner_pay`.
- Baki sen akibat pembundaran masuk ke tabung pertama (`sort_order` terkecil).

---

## 2. Tech Stack

| Lapisan | Pilihan |
|---|---|
| Backend | **Laravel 13** |
| PHP | **PHP 8.5** (Laravel 13 support 8.3–8.5; guna 8.5 untuk projek baru) |
| Database | MySQL 8 |
| Frontend | Inertia.js 2 + Vue 3 (`<script setup>`, **TypeScript**) |
| Starter | **Laravel Vue Starter Kit** (`laravel new` → Vue) — sudah ada Inertia, TS, Tailwind CSS v4, shadcn-vue, auth, appearance (light/dark) |
| UI components | shadcn-vue (sedia ada dalam starter kit) + `lucide-vue-next` icons |
| i18n | **`laravel-vue-i18n`** (kongsi fail `lang/*.json` Laravel dengan Vue) + **`spatie/laravel-translatable`** untuk data dwibahasa dalam DB (nama kategori/tabung sistem) |
| Chart | `vue-chartjs` + `chart.js` |
| Routes di frontend | Laravel Wayfinder atau Ziggy (ikut apa starter kit sediakan) |
| PDF | `spatie/laravel-pdf` (Browsershot) — kalau susah setup di Mac, guna `barryvdh/laravel-dompdf` |
| Media/resit | Laravel filesystem (`local` disk dulu, `public` untuk thumbnail) |
| Backup | `spatie/laravel-backup` |
| Test | Pest |
| Code style | Laravel Pint (PHP), ESLint + Prettier (sedia ada dalam starter kit) |
| Queue | `database` driver |
| Mail (local) | Mailpit / `log` driver |

> **Nota untuk Claude Code:** Sebelum pasang package, semak versi terkini yang support Laravel 13 / PHP 8.5 (`composer show -a <package>`). Kalau package tak support lagi, guna alternatif & catat dalam Decision Log.

### Persekitaran local (MacBook)
- **Laravel Herd** dengan **PHP 8.5** (Herd → Settings → PHP → install 8.5, set sebagai default atau `herd isolate 8.5` dalam folder projek). Domain auto: `kirabisnes.test`.
- **MySQL 8**: DBngin / Herd Pro / `brew install mysql`.
- Node 20+ (`npm`).

```bash
# 1. Pastikan versi betul
php -v            # mesti PHP 8.5.x
composer -V
laravel --version # kalau lama: composer global update laravel/installer

# 2. Cipta projek
laravel new kirabisnes        # pilih: Vue starter kit, Laravel built-in auth, Pest
cd kirabisnes

# 3. Database
mysql -u root -e "CREATE DATABASE kirabisnes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# .env → DB_CONNECTION=mysql, DB_DATABASE=kirabisnes
#        APP_TIMEZONE=Asia/Kuala_Lumpur
#        APP_LOCALE=ms
#        APP_FALLBACK_LOCALE=en
#        APP_FAKER_LOCALE=ms_MY

php artisan migrate --seed
composer run dev              # serve + queue + vite serentak
```

---

## 3. Konvensyen (WAJIB ikut)

### 3.1 Duit
- Semua amaun disimpan sebagai **integer sen** dalam column `BIGINT` (RM12.50 → `1250`). **Jangan guna float/decimal.**
- Nama column amaun: `amount`, `total`, `subtotal`, dll. — semuanya dalam sen.
- Backend: buat `App\Support\Money` dengan helper:
  - `Money::toSen(string|float $ringgit): int`
  - `Money::format(int $sen): string` → `"RM 1,250.00"`
- Frontend: `resources/js/lib/money.ts` dengan `formatMoney(sen: number): string` (guna `Intl.NumberFormat(currentLocaleTag(), { style: 'currency', currency: 'MYR' })` — `ms-MY` atau `en-MY` ikut bahasa aktif) dan `toSen(input: string): number`.
- Input borang: pengguna taip ringgit (`12.50`), FormRequest tukar ke sen dalam `prepareForValidation()`.

### 3.2 Backend
- Business logic dalam **Action / Service class** (`app/Actions/...` atau `app/Services/...`), bukan dalam controller.
- Validation guna **FormRequest**.
- Status/jenis guna **PHP backed enum** (`app/Enums`) + cast dalam model. Setiap enum ada method `label(): string` yang guna `__()` supaya label ikut bahasa.
- Data ke Inertia guna **API Resource** atau array eksplisit — jangan hantar model mentah dengan semua column.
- Operasi kewangan yang sentuh lebih dari satu table **mesti** dalam `DB::transaction()`.
- Soft deletes untuk: `clients`, `projects`, `invoices`, `transactions`.
- Setiap route bisnes di bawah middleware `auth` + `verified`.
- Penomboran dokumen: `QUO-2026-0001`, `INV-2026-0001` — reset setiap tahun, guna table `document_sequences` dengan `lockForUpdate()`.
- Manfaatkan ciri Laravel 13 / PHP 8.5 bila sesuai (cth PHP attributes Laravel, pipe operator `|>`, `array_first()`/`array_last()`), tapi utamakan kebolehbacaan.

### 3.3 Frontend
- Pages: `resources/js/pages/{Module}/{Index,Create,Edit,Show}.vue`
- Komponen kongsi: `resources/js/components/app/...` (cth `MoneyInput.vue`, `StatusBadge.vue`, `StatCard.vue`, `EmptyState.vue`, `ConfirmDialog.vue`, `DataTable.vue`, `LanguageSwitcher.vue`, `ThemeToggle.vue`)
- Types: `resources/js/types/models.ts`
- Borang guna `useForm()` Inertia. Paparkan error per field.
- Senarai: server-side pagination + filter melalui query string (`?search=&status=&from=&to=`), guna `router.get(..., { preserveState: true, replace: true })` dengan debounce 300ms untuk carian.
- Flash message → toast (shadcn-vue `Sonner`). Mesej flash dari backend mesti guna `__()`.
- **Tiada teks UI hardcoded** — semua guna `trans()` / `$t()` (lihat 3.5).

### 3.4 Testing
- Setiap fasa: **Feature test** untuk CRUD utama + **Unit test** untuk logik kewangan (agihan, baki, untung).
- `php artisan test` mesti hijau sebelum tandakan fasa siap.
- Jalankan `./vendor/bin/pint` dan `npm run lint` sebelum habis fasa.
- Test i18n: command `php artisan lang:check` (buat sendiri, Fasa 0) mesti lulus — semua key dalam `lang/ms.json` wujud dalam `lang/en.json` dan sebaliknya.

### 3.5 Dwibahasa (i18n) — WAJIB
- **Sumber terjemahan tunggal:** `lang/ms.json` & `lang/en.json` (key = ayat English ringkas atau dot-key, pilih satu & konsisten — **guna dot-key**, cth `"invoice.status.paid": "Dibayar"`). Validation messages: `lang/ms/validation.php` & `lang/en/validation.php`.
- Frontend guna `laravel-vue-i18n` (`trans('invoice.status.paid')`, `wTrans()` untuk reaktif). Plugin baca fail JSON yang sama melalui Vite plugin — **jangan duplikasi** terjemahan dalam folder JS.
- **Penentuan bahasa** (middleware `SetLocale`, urutan keutamaan):
  1. `users.locale` (kalau login)
  2. Cookie `locale` (untuk halaman login/guest)
  3. Header `Accept-Language` (`ms`/`en` sahaja)
  4. `config('app.locale')` = `ms`
- `HandleInertiaRequests` share `locale` & `availableLocales`. Bila tukar bahasa → `PATCH /settings/locale` → simpan `users.locale` + cookie → `loadLanguageAsync(locale)` di frontend (tanpa reload penuh).
- **Format ikut bahasa:** tarikh (`ms`: "30 Okt 2026", `en`: "30 Oct 2026") guna `Intl.DateTimeFormat`, nombor/duit guna `Intl.NumberFormat` (`ms-MY` / `en-MY`). Backend guna `Carbon::setLocale()` dalam middleware yang sama.
- **Data dwibahasa dalam DB:** kategori & tabung **sistem** (seed) simpan `name` sebagai JSON `{ "ms": "...", "en": "..." }` dengan `spatie/laravel-translatable`. Kategori/tabung yang user cipta sendiri: borang ada 2 field (BM wajib, EN pilihan → fallback ke BM).
- **Dokumen ke klien (invois, sebut harga, e-mel):** ikut `clients.preferred_locale` (bukan bahasa UI pemilik). Pemilik boleh override semasa jana PDF/hantar e-mel.
- Enum label, nama bulan dalam chart, empty state, toast, notifikasi, PDF laporan — **semua** mesti dwibahasa.

### 3.6 Dark Mode — WAJIB
- Mod: **Light / Dark / System** (ikut OS, dengar perubahan `prefers-color-scheme` secara live).
- Guna mekanisme appearance sedia ada dalam starter kit (`useAppearance` + class `.dark` pada `<html>`) — **jangan cipta sistem baru**. Lanjutkan supaya:
  - Pilihan disimpan dalam `users.appearance` (sync antara device) **dan** cookie/localStorage (elak *flash* semasa load — skrip inline dalam `app.blade.php` set class `.dark` sebelum Vue mount).
  - `ThemeToggle` di top bar (ikon Sun / Moon / Monitor) + pilihan penuh di **Tetapan › Paparan**.
- Semua warna guna **CSS variables / token** (Seksyen 4) — dilarang hardcode `bg-white` / `text-black` tanpa padanan `dark:`.
- **Chart** (`chart.js`): warna grid, label, tooltip ambil dari CSS variables & re-render bila tema bertukar.
- **PDF & e-mel** sentiasa **light** (untuk cetakan), tak ikut dark mode.
- Semak kontras teks ≥ WCAG AA dalam kedua-dua mod.

---

## 4. Design System — Modern Minimalist

**Prinsip:** banyak ruang putih, satu warna aksen, tipografi jelas, angka mudah dibaca, tiada hiasan berlebihan. Sama cantik dalam light & dark.

### 4.1 Token warna (definisikan dalam `resources/css/app.css` melalui `@theme` + `:root` / `.dark`)

| Token | Light | Dark |
|---|---|---|
| `--background` | `white` | `zinc-950` |
| `--surface` (kad) | `white` | `zinc-900` |
| `--surface-muted` | `zinc-50` | `zinc-900/60` |
| `--border` | `zinc-200` | `zinc-800` |
| `--foreground` | `zinc-900` | `zinc-50` |
| `--muted-foreground` | `zinc-500` | `zinc-400` |
| `--primary` (aksen) | `emerald-600` | `emerald-500` |
| `--primary-foreground` | `white` | `zinc-950` |
| `--income` / positif | `emerald-600` | `emerald-400` |
| `--expense` / negatif | `rose-600` | `rose-400` |
| `--warning` / tertunggak | `amber-600` | `amber-400` |
| `--info` | `sky-600` | `sky-400` |
| Badge latar lembut | `emerald-50` | `emerald-500/10` |

> Starter kit shadcn-vue dah ada token asas (`--background`, `--foreground`, `--primary` …). **Ubah nilai** token sedia ada & tambah token baru (`--income`, `--expense`, `--warning`, `--info`, `--surface-muted`) — jangan buat sistem warna kedua.

### 4.2 Elemen lain

| Elemen | Spesifikasi |
|---|---|
| Font | **Inter** (UI), `tabular-nums` untuk semua angka duit |
| Radius | `rounded-xl` untuk kad, `rounded-lg` untuk input/butang |
| Border & bayang | Border 1px token `--border`; **elak shadow berat** — `shadow-sm` paling banyak (dark mode: tiada shadow, guna border) |
| Spacing | Kad `p-6`, jarak antara seksyen `gap-6` |
| Ikon | Lucide, saiz 16–18px, stroke 1.75 |
| Transisi tema | `transition-colors duration-200` pada `body`, tiada animasi lain yang berat |

### 4.3 Layout
- **Sidebar kiri** (collapsible) + top bar ringkas: breadcrumb, carian, butang **"+ Transaksi"**, **LanguageSwitcher** (BM | EN), **ThemeToggle**, loceng notifikasi, menu user.
- Menu sidebar (label dwibahasa):
  1. Dashboard
  2. Transaksi / Transactions
  3. Invois / Invoices
  4. Sebut Harga / Quotations
  5. Klien / Clients
  6. Projek / Projects
  7. Tabung / Funds
  8. Akaun / Accounts
  9. Langganan Berulang / Recurring
  10. Laporan / Reports
  11. Tetapan / Settings
- **Mobile**: sidebar jadi drawer; senarai jadi kad (bukan table). LanguageSwitcher & ThemeToggle masuk dalam menu user.
- Angka besar di dashboard: `text-3xl font-semibold tracking-tight tabular-nums`.
- Status badge: pill kecil, latar lembut, tiada border tebal.
- Empty state: ikon, satu ayat, satu butang tindakan.

---

## 5. Skema Database

> Semua amaun dalam **sen (BIGINT)**. Semua table ada `timestamps`. `→` = foreign key.

```
users  (tambah column pada table sedia ada)
  locale varchar(5) default 'ms'               -- 'ms' | 'en'
  appearance varchar(10) default 'system'      -- 'light' | 'dark' | 'system'

settings
  id, key (unique), value (json)
  -- business_name, business_address, business_phone, business_email,
  -- business_reg_no, logo_path, bank_details, invoice_footer_note (json ms/en),
  -- default_payment_terms_days (14), invoice_prefix, quotation_prefix,
  -- default_document_locale ('ms')

accounts
  id, name, type enum(bank, ewallet, cash), bank_name nullable,
  opening_balance bigint default 0, is_active bool, sort_order

funds
  id, name json (translatable ms/en), slug unique, percentage decimal(5,2),
  color, sort_order, is_active bool, is_system bool

categories
  id, name json (translatable ms/en), type enum(income, expense),
  default_fund_id → funds nullable, tax_deductible_default bool,
  color, icon nullable, is_system bool

clients
  id, name, company nullable, email nullable, phone nullable,
  address text nullable, reg_no nullable, notes text nullable,
  preferred_locale varchar(5) default 'ms',   -- bahasa invois/e-mel untuk klien ini
  softDeletes

projects
  id, client_id → clients, name, description nullable,
  contract_amount bigint default 0, status enum(prospect, active, completed, on_hold, cancelled),
  start_date nullable, end_date nullable, internal_hourly_rate bigint nullable,
  softDeletes

document_sequences
  id, type (quotation|invoice), year, last_number
  unique(type, year)

quotations
  id, client_id, project_id nullable, number unique, issue_date, valid_until,
  status enum(draft, sent, accepted, rejected, expired),
  locale varchar(5),                            -- bahasa dokumen (default dari klien)
  subtotal, discount, total, notes, terms, converted_invoice_id nullable

quotation_items
  id, quotation_id, description, quantity decimal(10,2), unit_price bigint, amount bigint, sort_order

invoices
  id, client_id, project_id nullable, quotation_id nullable, recurring_item_id nullable,
  number unique, issue_date, due_date,
  status enum(draft, sent, partial, paid, overdue, void),
  locale varchar(5),                            -- bahasa dokumen (default dari klien)
  subtotal, discount, total, paid_amount bigint default 0,
  notes, terms, sent_at nullable, paid_at nullable, softDeletes

invoice_items
  id, invoice_id, description, quantity decimal(10,2), unit_price bigint, amount bigint, sort_order

transactions
  id, type enum(income, expense, transfer, owner_draw, capital_injection),
  account_id → accounts,
  to_account_id → accounts nullable          -- untuk transfer
  amount bigint (sentiasa positif),
  date, description, reference nullable,
  category_id nullable, project_id nullable, invoice_id nullable, client_id nullable,
  fund_id nullable                            -- tabung yang ditolak (expense/owner_draw)
  tax_deductible bool default false,
  receipt_path nullable,
  recurring_item_id nullable,
  softDeletes
  index(date), index(type)

fund_movements
  id, fund_id → funds, transaction_id → transactions nullable,
  type enum(allocate, spend, adjust, transfer_in, transfer_out),
  amount bigint (signed: + masuk tabung, - keluar tabung),
  note nullable, date
  index(fund_id, date)

recurring_items
  id, kind enum(client_billing, own_cost),
  client_id nullable, project_id nullable, category_id nullable, account_id nullable,
  name, description nullable, amount bigint,
  interval enum(monthly, quarterly, yearly), next_date,
  auto_generate bool,                          -- client_billing: auto jana invois draf
  remind_days_before int default 14,
  is_active bool

time_entries   (Fasa 5)
  id, project_id, started_at, ended_at nullable, minutes int nullable,
  note nullable, billable bool default true
```

### Peraturan baki
- **Baki akaun** = `opening_balance` + Σ income + Σ capital_injection + Σ transfer masuk − Σ expense − Σ owner_draw − Σ transfer keluar.
- **Baki tabung** = Σ `fund_movements.amount` bagi tabung itu.
- Kira melalui query (`AccountBalanceService`, `FundBalanceService`). Jangan simpan baki dalam column (elak data tak sync). Boleh cache kemudian kalau perlu.

### Peraturan transaksi → tabung
| Jenis transaksi | Kesan pada tabung |
|---|---|
| `income` | `allocate` ke semua tabung aktif ikut % |
| `capital_injection` (pemilik masukkan duit sendiri) | `allocate` 100% ke `capital` (atau tabung dipilih) |
| `expense` | `spend` (negatif) dari `fund_id` (default ikut kategori → `operating`) |
| `owner_draw` | `spend` (negatif) dari `owner_pay` |
| `transfer` (antara akaun) | Tiada kesan pada tabung |
| Pindah antara tabung | `transfer_out` + `transfer_in` (tiada transaksi akaun) |

- **Edit/padam** transaksi → padam `fund_movements` lama dan jana semula (dalam DB transaction).
- Benarkan tabung jadi negatif, tapi papar **amaran merah** di UI.

---

## 6. Fasa Pembinaan

---

### FASA 0 — Setup, Asas, Dwibahasa & Dark Mode
**Matlamat:** Projek boleh run di local, layout & design system siap (light + dark), dwibahasa berfungsi dari hari pertama, seeders asas.

**Setup**
- [x] Projek Laravel 13 + Vue starter kit, PHP 8.5, sambung MySQL, `APP_TIMEZONE=Asia/Kuala_Lumpur`, `APP_LOCALE=ms`, `APP_FALLBACK_LOCALE=en`
- [x] Pasang font Inter

**Design system & Dark mode**
- [x] Token warna Seksyen 4.1 dalam `app.css` (`:root` + `.dark`), ubah token shadcn-vue sedia ada
- [x] Migration: tambah `users.appearance` (default `system`)
- [x] Lanjutkan `useAppearance` starter kit: simpan ke `users.appearance` melalui `PATCH /settings/appearance` + cookie; skrip inline anti-flash dalam `app.blade.php`
- [x] Komponen `ThemeToggle` (Light / Dark / System) di top bar
- [x] Halaman **Tetapan › Paparan** (pilihan tema + bahasa)

**Dwibahasa**
- [x] Pasang `laravel-vue-i18n` (+ Vite plugin) & `spatie/laravel-translatable`
- [x] Cipta `lang/ms.json`, `lang/en.json`, `lang/ms/validation.php`, `lang/en/validation.php` (+ `auth.php`, `passwords.php`, `pagination.php` untuk `ms`)
- [x] Terjemah semua teks halaman auth & layout starter kit (login, register, forgot password, profile, sidebar, user menu)
- [x] Migration: tambah `users.locale` (default `ms`)
- [x] Middleware `SetLocale` (urutan Seksyen 3.5) + `Carbon::setLocale()`; daftar dalam stack `web`
- [x] `HandleInertiaRequests` share `locale`, `availableLocales`, `appearance`
- [x] Route `PATCH /settings/locale` + komponen `LanguageSwitcher` (BM | EN) — tukar tanpa reload penuh
- [x] `resources/js/lib/format.ts`: `formatDate()`, `formatDateTime()`, `formatNumber()` ikut locale aktif; `money.ts` guna locale aktif
- [x] Command `php artisan lang:check` — laporkan key yang hilang antara `ms.json` & `en.json` (exit code ≠ 0 kalau ada)

**Komponen & asas lain**
- [x] Ubah suai `AppSidebar` ikut menu Seksyen 4.3 (item belum siap boleh `disabled`)
- [x] Komponen kongsi: `MoneyInput`, `StatusBadge`, `StatCard`, `EmptyState`, `ConfirmDialog`, `PageHeader`, `DataTable` (ringkas, dengan pagination) — semua support dark mode & i18n
- [x] `App\Support\Money` + `resources/js/lib/money.ts` + unit test
- [x] Toast untuk flash message (`success`, `error`) dikongsi melalui `HandleInertiaRequests`
- [x] Table `settings` + halaman **Tetapan › Profil Bisnes** (nama, alamat, no. pendaftaran, telefon, e-mel, logo, maklumat bank, bahasa dokumen default)
- [x] Seeder: 1 user admin (`admin@kirabisnes.test` / `password`), tabung default (nama ms/en), kategori default (nama ms/en), 1 akaun tunai
- [x] Matikan pendaftaran awam (single-user) — route `register` disable selepas user pertama wujud

**Kategori default (ms / en):**
- *Income:* Projek Web / Web Project, Projek Sistem / System Project, Penyelenggaraan / Maintenance, Hosting & Domain (jual semula) / Hosting & Domain (resale), Konsultasi / Consulting, Lain-lain / Others
- *Expense:* Hosting & Server / Hosting & Server, Domain / Domain, Perisian & SaaS / Software & SaaS, Perkakasan / Hardware, Internet & Telefon / Internet & Phone, Minyak / Tol / Parking / Fuel / Toll / Parking, Subkontrak / Subcontract, Kursus & Buku / Courses & Books, Caj Bank / Gateway / Bank & Gateway Fees, Iklan & Pemasaran / Ads & Marketing, Lain-lain / Others

**Tests**
- [x] `SetLocale` ikut urutan keutamaan; tukar bahasa simpan ke user & cookie
- [x] Tukar appearance simpan ke user
- [x] `lang:check` lulus

**Acceptance:** Login → layout sidebar minimalist. Tukar BM ⇄ EN → semua teks (termasuk validation error) bertukar tanpa reload penuh. Tukar Light / Dark / System → tiada *flash* putih semasa refresh dalam dark mode, pilihan kekal selepas logout/login. `php artisan test` & `lang:check` hijau.

---

### FASA 1 — Akaun, Tabung & Transaksi (TERAS)
**Matlamat:** Rekod duit masuk/keluar, agihan tabung automatik berfungsi.

- [x] Enums: `AccountType`, `TransactionType`, `CategoryType`, `FundMovementType` (dengan `label()` dwibahasa)
- [x] Migrations + models + relationships: `accounts`, `funds`, `categories`, `transactions`, `fund_movements` (`funds.name` & `categories.name` guna `HasTranslations`)
- [x] **Akaun**: CRUD, papar baki semasa setiap akaun
- [x] **Kategori**: CRUD ringkas di Tetapan (field nama BM wajib + EN pilihan; kategori `is_system` tak boleh dipadam)
- [x] **Tabung**: halaman senarai tabung (kad dengan baki + bar peratus), edit peratus (validation jumlah = 100), pindah antara tabung
- [x] `FundAllocationService::allocate(Transaction)` — integer math, baki sen ke tabung pertama
- [x] `FundAllocationService::spend(Transaction)`
- [x] `TransactionObserver` (atau Action eksplisit) — create/update/delete sync `fund_movements`
- [x] **Transaksi**: senarai (filter: jenis, akaun, kategori, tarikh dari–hingga, carian), tambah/edit/padam
  - Borang dinamik ikut jenis (`income`, `expense`, `owner_draw`, `transfer`, `capital_injection`)
  - Upload resit (jpg/png/pdf, max 5MB) untuk expense
  - Checkbox "Boleh tolak cukai" (default ikut kategori)
- [x] Dialog **"+ Transaksi Pantas"** dari top bar (boleh dibuka dari mana-mana halaman)
- [x] Pratonton agihan dalam borang income ("RM3,000 → Operasi RM900, Gaji Sendiri RM1,200, ...")
- [x] Semua halaman disemak dalam **BM, EN, light, dark**
- [x] Tests: agihan tepat (termasuk kes pembundaran, cth RM100.01), edit/padam re-sync, baki akaun & tabung betul, transfer tak sentuh tabung

**Acceptance:** Rekod income RM3,000 → 5 tabung terima jumlah betul. Rekod expense RM50 → tabung Operasi berkurang RM50. Edit income ke RM2,000 → agihan dikira semula.

---

### FASA 2 — Dashboard & Gaji Sendiri
**Matlamat:** Satu skrin untuk tahu keadaan kewangan bisnes.

- [x] `DashboardService` — semua query dalam satu service
- [x] Stat cards (bulan semasa, dengan perbandingan % vs bulan lepas):
  - Duit Masuk, Duit Keluar, Untung Bersih, Jumlah Baki Semua Akaun
- [x] Kad baki setiap tabung (warna tabung, amaran jika negatif)
- [x] Chart: Masuk vs Keluar 12 bulan (bar), Perbelanjaan ikut kategori bulan ini (donut)
  - Label bulan ikut bahasa; warna ikut token & re-render bila tema bertukar
- [x] Senarai 5 transaksi terkini
- [x] **Gaji Sendiri** (`/owner-pay`):
  - Baki tabung `owner_pay`
  - **Cadangan jumlah selamat** = `min(baki tabung owner_pay, purata income 3 bulan × % owner_pay)`
  - Butang "Ambil Gaji" → borang `owner_draw` pra-isi
  - Sejarah owner draw + jumlah tahun ini
- [x] Penapis tempoh dashboard: Bulan ini / Bulan lepas / Tahun ini / Custom

**Acceptance:** Dashboard load < 500ms dengan 1,000 transaksi seed (buat factory + `DemoSeeder`), semua angka padan dengan kiraan manual dalam test. Chart kelihatan jelas dalam light & dark.

---

### FASA 3 — Klien, Projek, Sebut Harga & Invois
**Matlamat:** Dari sebut harga → invois → bayaran → agihan tabung, semua bersambung.

- [x] **Klien**: CRUD (termasuk `preferred_locale` BM/EN), halaman Show (projek, invois, jumlah dibayar, jumlah tertunggak)
- [x] **Projek**: CRUD, status, nilai kontrak; halaman Show (invois, transaksi bertag, ringkasan untung kasar)
- [x] `DocumentNumberService::next('invoice'|'quotation')` — selamat untuk concurrent (`lockForUpdate`)
- [x] **Sebut Harga**: CRUD dengan line items dinamik (tambah/buang baris, auto-kira), diskaun (RM), status, bahasa dokumen (default dari klien)
  - Butang **"Tukar ke Invois"** → salin item & locale, pautkan `quotation_id`, status quotation → `accepted`
- [x] **Invois**: CRUD dengan line items, `due_date` default = `issue_date` + `default_payment_terms_days`, bahasa dokumen (default dari klien)
  - Status auto: `paid_amount = 0` → `sent`/`draft`; `0 < paid < total` → `partial`; `paid >= total` → `paid`
  - **Rekod Bayaran** dari halaman invois → cipta transaksi `income` (pautkan `invoice_id`, `client_id`, `project_id`) → agihan tabung berlaku automatik → kemas kini `paid_amount` & status
  - Sokong bayaran separa (deposit + baki)
  - `void` invois (bukan padam) bila ada bayaran
- [x] **PDF** untuk invois & sebut harga — reka bentuk minimalist, **sentiasa light**, **ikut `locale` dokumen** (label, format tarikh & nombor). Logo, maklumat bisnes, klien, table item, jumlah, maklumat bank, nota. Butang Muat Turun & Pratonton
- [x] Hantar invois melalui e-mel (Mailable + lampiran PDF, `->locale($invoice->locale)`) → set `sent_at`, status `sent`
- [x] Scheduled command `invoices:mark-overdue` (harian) → status `overdue` bila lepas `due_date` & belum `paid`
- [x] Dashboard: tambah kad **"Invois Tertunggak"** (jumlah + bilangan) & senarai 5 invois hampir/lepas due
- [x] Tests: penomboran, aliran quotation → invoice → bayaran separa → paid, agihan dari bayaran invois, overdue command, PDF/e-mel ikut locale klien walaupun UI dalam bahasa lain

**Acceptance:** Cipta sebut harga → tukar ke invois → rekod deposit 50% (status `partial`, tabung terima agihan) → rekod baki (status `paid`). Klien EN terima PDF & e-mel dalam English walaupun UI dalam BM. PDF kemas & boleh dicetak.

---

### FASA 4 — Langganan Berulang & Peringatan
**Matlamat:** Tak terlepas caj renew klien & tahu kos berulang sendiri.

- [x] **Recurring Items** CRUD, dua tab: *Caj Klien* (`client_billing`) & *Kos Sendiri* (`own_cost`)
- [x] Command `recurring:process` (harian):
  - `client_billing` + `auto_generate` → jana **invois draf** (locale dari klien) bila `next_date - remind_days_before <= hari ini` (elak duplikasi untuk tempoh sama), kemudian majukan `next_date` ikut `interval`
  - `own_cost` → hantar notifikasi peringatan (database notification + e-mel, ikut `users.locale`) sebelum tarikh caj
- [x] Butang "Tandakan Dibayar" pada `own_cost` → cipta transaksi `expense` pra-isi + majukan `next_date`
- [x] Ikon loceng notifikasi di top bar (senarai notifikasi belum dibaca) — teks notifikasi dirender ikut bahasa semasa dibaca, bukan semasa dicipta (simpan key + params)
- [x] Dashboard: kad **"Akan Datang 30 Hari"** (caj klien dijangka masuk vs kos berulang dijangka keluar)
- [x] Daftar scheduler dalam `routes/console.php`
- [x] Tests: penjanaan invois tak duplikasi, kiraan `next_date` (termasuk hujung bulan, cth 31 Jan → 28/29 Feb)

**Acceptance:** Item hosting tahunan klien dengan `next_date` 10 hari lagi → selepas `recurring:process`, satu invois draf wujud & notifikasi muncul. Jalankan lagi → tiada invois kedua.

---

### FASA 5 — Laporan, Cukai, Untung Projek & Time Tracking
**Matlamat:** Faham prestasi bisnes & sedia untuk isi cukai.

- [x] **Laporan Untung Rugi (P&L)**: ikut bulan / tahun, pecahan ikut kategori, export CSV & PDF (pilih bahasa laporan)
- [x] **Laporan Aliran Tunai**: masuk/keluar ikut akaun
- [x] **Ringkasan Cukai Tahunan**: jumlah pendapatan, jumlah perbelanjaan boleh tolak (ikut kategori), anggaran pendapatan bercukai, baki tabung `tax`. Export CSV/PDF. Paparkan nota: *"Anggaran sahaja — rujuk LHDN / perunding cukai."* / *"Estimate only — consult LHDN / a tax advisor."*
- [x] **Untung per Projek**: income − expense bertag − (minit × `internal_hourly_rate`). Senarai projek susun ikut margin
- [x] **Time Tracking**: migration `time_entries`, timer start/stop (satu timer aktif sahaja), entri manual, paparan di halaman projek
  - Widget timer kecil di top bar bila timer aktif
- [x] **Ramalan Aliran Tunai 30/60/90 hari**: baki semasa + invois belum bayar (ikut `due_date`) + caj klien berulang − kos berulang. Chart garisan
- [x] Header CSV ikut bahasa yang dipilih semasa export
- [x] Tests: angka P&L padan, untung projek, ramalan

**Acceptance:** Laporan tahun 2026 boleh dimuat turun sebagai PDF & CSV dalam BM dan EN, angka sama dengan dashboard.

---

### FASA 6 — Integrasi & Kemasan (Pilihan)
- [x] **Import penyata bank CSV**: upload → map column (tarikh, keterangan, amaun) → pratonton → padankan dengan transaksi sedia ada (amaun + tarikh ±3 hari) → import yang baru
- [ ] **Payment gateway** (ToyyibPay atau Billplz): link bayaran dalam invois/e-mel, webhook → auto rekod bayaran
  - *Ditangguh atas keputusan pemilik (2026-10-30).* Perlukan akaun merchant + kunci API. Buat masa ini portal klien memaparkan maklumat bank untuk bayaran manual, dan bayaran direkod melalui butang "Rekod Bayaran" pada invois.
- [x] **Portal klien awam**: URL bertanda tangan (`URL::signedRoute`) untuk klien lihat & bayar invois tanpa login — ikut `invoice.locale`, ada LanguageSwitcher & ikut tema sistem pelawat
- [x] **Push owner_draw ke Money Tracker peribadi** melalui API (webhook keluar, dikonfigurasi di Tetapan)
- [x] Backup harian (`spatie/laravel-backup`) ke disk luaran (S3 / DO Spaces)
- [x] 2FA untuk login
- [x] PWA (manifest + ikon, `theme_color` berbeza untuk light/dark) supaya boleh "install" di telefon
- [x] Audit log ringkas untuk perubahan transaksi & invois

---

## 7. Struktur Folder (rujukan)

```
app/
  Actions/            # cth RecordInvoicePayment, ConvertQuotationToInvoice
  Console/Commands/   # LangCheck, MarkOverdueInvoices, ProcessRecurringItems
  Enums/
  Http/Controllers/
  Http/Middleware/    # SetLocale
  Http/Requests/
  Http/Resources/
  Models/
  Observers/
  Services/           # FundAllocationService, AccountBalanceService, FundBalanceService,
                      # DashboardService, DocumentNumberService, ReportService
  Support/Money.php
lang/
  ms.json  en.json
  ms/ (validation.php, auth.php, passwords.php, pagination.php)
  en/ (validation.php, auth.php, passwords.php, pagination.php)
resources/js/
  components/app/     # komponen kongsi KiraBisnes (LanguageSwitcher, ThemeToggle, ...)
  composables/        # useAppearance (lanjutan), useLocale
  layouts/
  lib/money.ts  lib/format.ts
  pages/
    Dashboard.vue
    Accounts/ Funds/ Transactions/ Clients/ Projects/
    Quotations/ Invoices/ Recurring/ Reports/ Settings/ OwnerPay/
  types/models.ts
resources/views/pdf/  # invoice.blade.php, quotation.blade.php, report.blade.php
tests/Feature/  tests/Unit/
```

---

## 8. Arahan Kerja untuk Claude Code

1. Mula dengan **Fasa 0**. Sebelum tulis kod, semak struktur starter kit yang sedia ada (terutama appearance & layout) dan ikut pattern dia.
2. Untuk setiap fasa: migrations → enums → models → services/actions (+ unit test) → controllers/requests/routes → Vue pages → terjemahan (`ms.json` + `en.json`) → feature tests → pint/lint/`lang:check`.
3. Setiap teks baru yang dipaparkan kepada pengguna **mesti** ada dalam kedua-dua `ms.json` & `en.json` dalam commit yang sama.
4. Setiap komponen/halaman baru **mesti** disemak dalam light & dark mode.
5. Selepas setiap fasa:
   - Jalankan `php artisan migrate:fresh --seed`, `php artisan test`, `php artisan lang:check`
   - Tandakan checklist `[x]` dalam fail ini
   - Beri ringkasan apa yang dibina + cara test manual di browser
   - **Berhenti dan tunggu pengesahan** sebelum mula fasa seterusnya
6. Jangan tambah package baharu di luar Seksyen 2 tanpa catat sebab dalam Decision Log.
7. Jangan guna float untuk duit. Jangan letak business logic dalam Vue atau controller.
8. Kekalkan UI konsisten dengan Seksyen 4 — rujuk komponen shadcn-vue sedia ada dahulu sebelum buat baru.

---

## 9. Decision Log

| Tarikh | Keputusan | Sebab |
|---|---|---|
| 2026-10-30 | Laravel 13 + PHP 8.5 | Versi stabil terkini; Laravel 13 support PHP 8.3–8.5 |
| 2026-10-30 | Duit disimpan dalam sen (BIGINT) | Elak ralat pembundaran float |
| 2026-10-30 | Baki akaun/tabung dikira, bukan disimpan | Elak data tak sync |
| 2026-10-30 | Single-user, tiada multi-tenant | Skop awal untuk kegunaan sendiri |
| 2026-10-30 | Dwibahasa dari Fasa 0 (`laravel-vue-i18n`, fail `lang/*.json` dikongsi) | Elak retrofit terjemahan kemudian; satu sumber terjemahan untuk backend & frontend |
| 2026-10-30 | Bahasa dokumen ikut klien, bukan bahasa UI | Klien terima invois dalam bahasa yang mereka faham |
| 2026-10-30 | Dark mode guna mekanisme appearance starter kit + simpan di `users` | Tiada sistem tema kedua; pilihan sync antara device |
| 2026-10-30 | PDF & e-mel sentiasa light | Sesuai untuk cetakan & klien e-mel |
| 2026-10-30 | Laravel 13.39 + PHP 8.5.12 + MySQL 9.7 (bukan 8) | Versi yang ada di mesin ini; skema tidak guna ciri khusus MySQL 8 |
| 2026-10-30 | Starter kit guna Fortify (bukan Breeze), dan sudah sertakan 2FA + passkey | Itu yang `laravel new --vue` sediakan sekarang; semuanya diterjemah sekali |
| 2026-10-30 | Ikon dari `@lucide/vue` (bukan `lucide-vue-next`) | Pakej itu yang starter kit bawa |
| 2026-10-30 | Lint/format guna `vite-plus` (`npm run check`) bukan ESLint + Prettier | Starter kit tiada ESLint; `vp check` sudah lint + format + type-aware |
| 2026-10-30 | Routes frontend guna Wayfinder (bukan Ziggy) | Disediakan oleh starter kit |
| 2026-10-30 | `laravel-vue-i18n` dipasang dari npm sahaja (tiada pakej Composer) | Pakej itu JS sahaja; ia baca terus `lang/*.json` dan `lang/{locale}/*.php` |
| 2026-10-30 | Terjemahan dimuatkan secara *eager* (`import.meta.glob(..., { eager: true })`) | Inertia 3 render di pelayan; plugin i18n selesaikan mesej secara sinkron, jadi import malas akan render kunci mentah dahulu |
| 2026-10-30 | Jadual `accounts`, `funds`, `categories` + model dibina awal dalam Fasa 0 | Diperlukan oleh seeder Fasa 0; CRUD, enum penuh & servis tetap dalam Fasa 1 |
| 2026-10-30 | Flash toast guna `Inertia::flash('toast', ...)` | Inertia 3 simpan flash dalam kunci sesi sendiri; `->with('toast')` tidak sampai ke frontend |
| 2026-10-30 | `config/app.php` ditukar supaya `timezone` baca `env('APP_TIMEZONE')` | Skeleton Laravel 13 hardcode `'UTC'`, jadi `APP_TIMEZONE` dalam `.env` diabaikan |
| 2026-10-30 | LanguageSwitcher & ThemeToggle kekal di top bar pada mobile (tidak dipindah ke menu user) | Kedua-duanya hanya butang ikon; lagi mudah dicapai dan satu komponen sahaja untuk semua saiz skrin |
| 2026-10-30 | `Welcome.vue` & varian layout header starter kit dibuang; `/` redirect ke `/dashboard` | Aplikasi single-user tiada halaman pemasaran; kod mati itu penuh teks tak diterjemah |
| 2026-10-30 | Pendaftaran awam ditutup melalui middleware `EnsureRegistrationIsOpen` (404) | Config Fortify dimuatkan sebelum DB sedia; middleware menyemak secara malas pada route berkenaan sahaja |
| 2026-10-30 | PHPStan dijalankan dengan `--memory-limit=1G` | Larastan lebihi had CLI lalai 128M |
| 2026-10-30 | Transaksi `client_id` / `project_id` / `invoice_id` / `recurring_item_id` dicipta sebagai kolum nullable tanpa foreign key | Jadual rujukan belum ada; constraint akan ditambah bersama migration Fasa 3 & 4 |
| 2026-10-30 | Agihan tabung diurus oleh Action eksplisit (`StoreTransaction` / `UpdateTransaction` / `DeleteTransaction`), bukan `TransactionObserver` | Pelan benarkan kedua-dua; Action lebih jelas bila sync mesti berlaku dalam `DB::transaction()` yang sama |
| 2026-10-30 | Tabung untuk expense / owner_draw diselesaikan oleh `ResolveTransactionFund` dan disimpan dalam `transactions.fund_id` | Pilihan tabung kelihatan dalam UI dan boleh diaudit, bukan tersembunyi dalam servis agihan |
| 2026-10-30 | Pratonton agihan income guna endpoint `GET /allocation-preview` (debounce 300ms), bukan kiraan dalam Vue | Satu sumber kiraan sahaja (`FundAllocationService::split()`); elak logik duit terpisah dua tempat |
| 2026-10-30 | Dialog transaksi pantas muat `GET /transaction-options` bila dibuka kali pertama | Butang ada pada setiap halaman; share pilihan dalam setiap respons akan menambah query pada semua permintaan |
| 2026-10-30 | Update transaksi & profil bisnes guna POST, bukan PATCH | Inertia 3 tiada method spoofing (`_method`), dan PHP tidak isi `$_FILES` untuk PATCH — upload resit/logo akan hilang |
| 2026-10-30 | Resit disimpan pada disk `local` (peribadi) dan dihidang melalui route `transactions.receipt` | Resit boleh mengandungi data peribadi; tiada URL awam |
| 2026-10-30 | Baki akaun dikira dengan `SUM(CASE ...)` dua query agregat, bukan per-akaun | Satu halaman akaun tidak boleh jadi N+1 bila bilangan akaun bertambah |
| 2026-10-30 | Akaun yang sudah ada transaksi tidak boleh dipadam — hanya dimatikan | Memadam akan meninggalkan transaksi tanpa akaun; `restrictOnDelete` pada foreign key menguatkan lagi |
| 2026-10-30 | `i18nVue` di-reset pada setiap render (`resetI18n()` dalam `withApp`) | Instance dikongsi menyimpan mesej boleh-ubah dalam module state; proses SSR berkekalan boleh bocorkan bahasa satu permintaan ke permintaan lain, dan kunci baharu tidak dimuatkan |
| 2026-10-30 | Chart guna `chart.js` + `vue-chartjs`, dimuatkan secara malas (`defineAsyncComponent` dalam `ClientOnly`) | Chart melukis pada canvas, jadi tiada guna semasa SSR; chunk 70 kB (gzip) tidak dibebankan pada halaman tanpa chart |
| 2026-10-30 | Warna chart dibaca dari CSS custom properties melalui `useChartTheme`, dipantau dengan `MutationObserver` pada kelas `<html>` | Seksyen 3.6 mewajibkan chart ikut token dan re-render bila tema bertukar; memantau kelas menangkap toggle, tetapan OS, dan kelas yang ditulis pelayan |
| 2026-10-30 | Nama bulan pada paksi chart diformat di browser (`Intl.DateTimeFormat`), pelayan hantar tarikh ISO | Label bertukar bahasa serta-merta tanpa permintaan baharu ke pelayan |
| 2026-10-30 | Donut hanya tunjuk 5 kategori terbesar; selebihnya digabung jadi satu hirisan 'Kategori lain' | Donut dengan belasan hirisan halus tidak boleh dibaca; warna ikut token `--chart-1..5` + neutral |
| 2026-10-30 | 'Duit Masuk' = `income` sahaja, 'Duit Keluar' = `expense` sahaja; `owner_draw` & `capital_injection` tidak dikira dalam Untung Bersih | Kedua-duanya duit pemilik masuk/keluar, bukan urus niaga; mengiranya menjadikan 'untung' bukan untung lagi |
| 2026-10-30 | Perbandingan % ikut tempoh kalendar yang sepadan (bulan ini vs bulan lepas penuh, tahun ini vs tahun lepas); tempoh custom vs jendela sama panjang sebelumnya | Itu yang pembaca jangkakan daripada perkataan 'berbanding bulan lepas'; tempoh custom tiada padanan kalendar |
| 2026-10-30 | Perubahan % jadi `null` bila tempoh sebelumnya kosong, bukan 0% atau 100% | Peratusan daripada sifar tiada makna; kad hanya tinggalkan baris perbandingan |
| 2026-10-30 | Penapis tempoh dashboard tidak guna FormRequest — input tak sah jatuh semula ke bulan ini | URL yang disimpan atau diubah tangan masih patut tunjuk dashboard, bukan 422; sama seperti penapis transaksi Fasa 1 |
| 2026-10-30 | Chart 12 bulan tidak melepasi bulan semasa walaupun tempoh 'Tahun ini' dipilih | Bulan yang belum berlaku hanya menghasilkan deretan bar kosong |
| 2026-10-30 | Cadangan gaji selamat = `min(baki tabung owner_pay, purata income 3 bulan kalendar × % tabung)`, purata dibahagi 3 walaupun bulan semasa belum habis | Baki tabung ialah had sebenar; purata menahan satu bulan besar daripada diambil sekali gus, dan bulan separuh mencondongkan purata ke arah yang selamat |
| 2026-10-30 | Kumpulan `SUBSTR(date, 1, 7)` untuk bucket bulanan | Berfungsi pada MySQL (app) dan SQLite (test); `DATE_FORMAT` hanya MySQL |
| 2026-10-30 | Query agregat guna `->toBase()` selepas scope Eloquent | Baris agregat bukan `Transaction`; `toBase()` kekalkan scope soft delete tetapi memulangkan baris biasa, jadi tiada property hantu pada model |
| 2026-10-30 | `DemoSeeder` jalan melalui `StoreTransaction`, bukan insert terus; kategori perbelanjaan diberi berat | Pergerakan tabung yang dihasilkan betul-betul sama seperti kegunaan sebenar; kategori rata memberi donut yang tiada maklumat |
| 2026-10-30 | **Pembetulan Fasa 1:** borang menghantar sen sedangkan FormRequest menjangka ringgit — semua amaun yang dimasukkan melalui UI tersimpan 100× besar. Borang kini guna `form.transform(asRinggit(...))`, dan `allocation-preview` juga terima ringgit | Seksyen 3.1 menetapkan borang hantar ringgit; ralat ini senyap (tiada ralat validasi) jadi ujian `MoneyWireFormatTest` mengunci kontrak pada setiap endpoint yang terima duit |
| 2026-10-30 | 'Gaji Sendiri' diletakkan dalam menu sisi selepas 'Tabung', walaupun tiada dalam senarai Seksyen 4.3 | Senarai itu ditulis sebelum halaman ini dirancang; ia tabung yang halaman itu ambil daripadanya |
| 2026-10-30 | PDF guna `barryvdh/laravel-dompdf` (bukan `spatie/laravel-pdf`/Browsershot) | Seksyen 2 benarkan alternatif ini; Browsershot perlukan Puppeteer + muat turun Chromium ~170 MB, jadi penjanaan PDF bergantung pada pemasangan browser. dompdf PHP tulen, berfungsi luar talian, dan cukup untuk invois minimalis |
| 2026-10-30 | Satu template Blade (`pdf/document.blade.php`) untuk invois & sebut harga, dengan CSS inline tulisan tangan | Dua dokumen itu berbeza pada label dan beberapa baris sahaja, bukan pada bentuk; dompdf susun atur dengan table bukan flexbox, jadi binaan Tailwind app tiada gunanya di sini |
| 2026-10-30 | `DocumentPdfService` tukar locale app sekitar render, dan pulihkannya dalam `finally` | Setiap `__()`, tarikh dan amaun dalam template terus ikut bahasa dokumen tanpa perlu dihantar satu-satu; bahasa pemilik sentiasa kembali walaupun render gagal |
| 2026-10-30 | Tarikh dokumen diformat dengan **ICU** (`IntlDateFormatter`), bukan `Carbon::isoFormat()` | Locale Melayu Carbon singkatkan Ogos sebagai 'Ogs' sedangkan CLDR — dan `Intl.DateTimeFormat` di skrin — guna 'Ogo'. PDF daripada satu halaman tidak boleh mengeja tarikh berbeza daripada halaman itu |
| 2026-10-30 | Nombor dokumen: `insertOrIgnore` dengan semakan `exists()` dahulu, kemudian `lockForUpdate` dalam transaksi dengan `attempts: 3` | **Pepijat dijumpai semasa ujian concurrent:** `firstOrCreate` melanggar kunci unik dan membuang exception bila 4 proses mula pada tahun baharu; insert tanpa syarat pula menyebabkan deadlock InnoDB terhadap kunci baris. Semakan baca dahulu tidak mengambil lock, jadi selepas dokumen pertama tahun itu hanya `FOR UPDATE` yang mengunci. Disahkan: 6 proses × 25 nombor = 150 unik, berturutan, sifar ralat |
| 2026-10-30 | Status invois ditentukan semula daripada jumlah bayaran (`RefreshInvoiceState`), bukan ditambah pada `paid_amount` | Bayaran yang diubah atau dipadam tidak boleh meninggalkan invois mendakwa duit yang ia tidak pernah terima |
| 2026-10-30 | Invois 'tertunggak' hanya bila `due_date` **sebelum** hari ini, bukan `isPast()` | `due_date` ialah tarikh bukan saat; `isPast()` pada tengah malam menjadikan invois yang jatuh tempoh hari ini tertunggak dari pukul 12:01 pagi |
| 2026-10-30 | Invois separuh berbayar yang lewat ditunjukkan sebagai `overdue`, bukan `partial` | Pelan ada kedua-dua peraturan untuk invois yang sama; memutuskannya dalam satu tempat menghalang status berkelip-kelip antara keduanya setiap kali command harian berjalan |
| 2026-10-30 | Batalkan (`void`) invois juga membuang transaksi bayarannya | Duit itu tidak pernah menjadi milik bisnes; membiarkannya akan menyimpan duit dalam tabung yang invois itu sendiri kata tidak pernah diperoleh |
| 2026-10-30 | Bayaran invois melalui `StoreTransaction` yang sama seperti borang transaksi | Tiada laluan kedua untuk duit invois, jadi agihan tabung sama sahaja tanpa mengira dari mana income itu datang |
| 2026-10-30 | Bayaran dihadkan kepada jumlah tertunggak (`max:outstanding`) | Bayaran lebih ialah kredit, dan fasa ini tiada tempat untuk menyimpannya; menyimpannya senyap akan menjadikan `paid_amount` melebihi `total` |
| 2026-10-30 | Item dokumen diganti sepenuhnya semasa edit, bukan dipadankan satu-satu | Invois ialah senarai pendek yang pemilik baru taip semula; mengekalkan id menambah langkah penyelarasan tanpa faedah |
| 2026-10-30 | `amount` setiap baris dikira dan **disimpan** pada baris itu | Dokumen yang dikeluarkan tahun lepas kekal dengan angka yang ia dikeluarkan dengannya, walaupun peraturan pembundaran disentuh kemudian |
| 2026-10-30 | Sebut harga yang sudah ditukar ke invois tidak boleh diedit, ditukar status atau dipadam | Ia rekod apa yang ditawarkan pada hari itu; mengeditnya akan menulis semula sejarah yang invois merujuk kepadanya |
| 2026-10-30 | Kuantiti didarab sebagai perseratus integer (`intdiv($hundredths * $unitPrice + 50, 100)`) | 2.5 jam ialah kuantiti sebenar untuk diinvois, tetapi 7 × RM33.33 dalam float mendarat pada 233.30999999999997 |
| 2026-10-30 | Diskaun dihadkan kepada jumlah kecil, dan diskaun negatif diabaikan | Salah taip dalam kotak diskaun tidak boleh menghasilkan invois bernilai negatif |
| 2026-10-30 | Projek hanya boleh dilampirkan pada dokumen klien yang sama (`Rule::exists(...)->where('client_id', ...)`) | Menanda projek orang lain akan meletakkan dokumen itu pada buku klien yang salah |
| 2026-10-30 | Koleksi item bersarang diselesaikan dengan `->resolve()` dalam resource | **Pepijat dijumpai semasa pengesahan hidup:** koleksi resource bersarang menjadi `{"data": [...]}`, dan halaman tidak boleh melelarinya — baris hanya render kosong dan `RMNaN`, tanpa sebarang ralat. `DocumentPayloadTest` kini mengikat bentuknya |
| 2026-10-30 | `memory_limit` 512M dalam `phpunit.xml` | dompdf memuatkan metrik fontnya pada setiap render, dan ujian PDF melakukan berpuluh dalam satu proses; 128M lalai kehabisan di tengah suite |
| 2026-10-30 | `smalot/pdfparser` sebagai dependency **dev** | Satu-satunya cara mengesahkan PDF betul-betul mengandungi label bahasa yang betul ialah membaca lapisan teksnya; ia tidak digunakan oleh aplikasi |
| 2026-10-30 | Tambah column `anchor_day` pada `recurring_items` (di luar skema Seksyen 5) | Tanpanya, item bulanan yang mula pada 31 Jan tersekat pada 28 Feb selama-lamanya: membaca hari semula daripada `next_date` kehilangan hari yang pemilik pilih. Dengan anchor: 31 Jan → 28 Feb → 31 Mac → 30 Apr, diuji sepanjang satu tahun penuh |
| 2026-10-30 | Tambah column `last_reminded_at` (di luar skema Seksyen 5) | `own_cost` hanya dimajukan apabila pemilik tandakan dibayar, jadi tanpa penanda ini peringatan yang sama tiba setiap hari sepanjang tempoh amaran (sampai 14 hari berturut-turut) |
| 2026-10-30 | Payload notifikasi simpan **kunci + parameter**, dan amaun sebagai `amount_sen` bukan 'RM 49.90' | Pelan mewajibkan teks dirender semasa dibaca. Amaun atau tarikh yang sudah diformat tersekat dalam bahasa semasa ia ditulis — masalah yang sama seperti ayat tersimpan. Halaman memformat kedua-duanya dengan locale pembaca |
| 2026-10-30 | `User` laksana `HasLocalePreference` | Notifikasi daripada command berjadual tiada bahasa permintaan; Laravel guna `preferredLocale()` untuk menghantar e-mel dalam bahasa yang pemilik pilih |
| 2026-10-30 | Hanya **kiraan belum dibaca** dikongsi pada setiap halaman; senarai diambil bila loceng dibuka | Loceng ada pada setiap halaman tetapi jarang dibuka; kiraan ialah satu query terindeks, senarai ialah query + resource |
| 2026-10-30 | `recurring:process` mengejar setiap caj yang terlepas (sampai 24), bukan melompat ke masa kini | App yang tidak dibuka selama tiga bulan memang berhutang satu invois bagi setiap bulan yang lepas; had 24 cuma menghalang tarikh yang tersalah taip daripada berpusing |
| 2026-10-30 | Invois berulang bertarikh pada **tarikh caj**, bukan hari ia dijana | Itu tarikh yang klien jangka baca, dan ia menjadikan jalanan kedua tidak berbahaya: satu invois per item per tarikh caj, dan pasangan itu disemak sebelum menulis |
| 2026-10-30 | Invois yang dipadam (soft deleted) masih dikira sebagai 'sudah diinvois' | Membuang draf itu satu keputusan; menjananya semula akan membatalkan keputusan pemilik |
| 2026-10-30 | Invois dijana sebagai **draf**, bukan terus dihantar | Caj automatik masih patut dipandang sebelum pergi kepada klien |
| 2026-10-30 | `own_cost` dimajukan hanya oleh butang 'Tandakan Dibayar', bukan oleh command | Kos tidak boleh dilangkau hanya kerana peringatan sudah keluar; command hanya memberitahu |
| 2026-10-30 | Tambah butang 'Majukan' untuk caj klien manual (tiada dalam pelan) | Tanpanya, item `client_billing` dengan `auto_generate` dimatikan tersekat pada tarikh yang sudah lepas selama-lamanya — command memberitahu sekali dan item itu tidak pernah bergerak |
| 2026-10-30 | Kad dashboard 30 hari mengira satu caj bulanan **dua kali** kalau ia jatuh dua kali dalam tingkap | Caj pada 14 hari bulan jatuh pada kedua-dua hujung tingkap 30 hari, dan kedua-duanya duit sebenar |
| 2026-10-30 | `PayRecurringCost` guna `StoreTransaction` yang sama seperti borang | Tabung yang ditolak diselesaikan dengan cara yang sama seperti perbelanjaan yang ditaip tangan; tiada laluan kedua |
| 2026-10-30 | Jadual: `invoices:mark-overdue` 01:00, `recurring:process` 01:15 | Status tertunggak diselaraskan dahulu, supaya jalanan berulang melihat keadaan invois hari itu |
| 2026-10-30 | Satu `ReportSheet` menerangkan setiap laporan sekali; PDF dan CSV membacanya | PDF dan CSV laporan yang sama mesti membawa angka yang sama. Menerangkannya sekali menjadikan itu benar, bukan diharapkan — menambah kolum tidak boleh mendarat dalam satu fail sahaja. Disahkan hidup: keempat-empat fail (PDF/CSV × BM/EN) menunjukkan RM311,199.89 / RM86,473.40 / RM224,726.49 |
| 2026-10-30 | Amaun CSV sebagai nombor biasa (`1250.00`), bukan 'RM 1,250.00' | String berformat masuk sel sebagai teks dan enggan dijumlahkan, iaitu satu-satunya kegunaan CSV |
| 2026-10-30 | CSV bermula dengan BOM UTF-8 | Excel perlukannya untuk membaca UTF-8; nama kategori Melayu ada aksara beraksen |
| 2026-10-30 | Bahasa export dipilih semasa export, bukan diambil daripada bahasa UI | Laporan sering dihantar kepada akauntan yang membaca bahasa yang satu lagi. Keseluruhan binaan berjalan dalam satu pertukaran locale, jadi label, tarikh dan amaun semuanya ikut satu pilihan itu |
| 2026-10-30 | Setiap laporan ialah halaman dan permintaan sendiri, berkongsi satu strip tab | Melawat Untung Rugi tidak patut membayar kos mengira Ramalan Tunai |
| 2026-10-30 | P&L tidak mengira `owner_draw`/`capital_injection`; Aliran Tunai mengira semuanya | Yang pertama ialah untung, yang kedua ialah penyata bank. Diuji: angka P&L sama tepat dengan dashboard |
| 2026-10-30 | Pindahan antara akaun sendiri muncul pada kedua-dua belah Aliran Tunai dan saling membatalkan | Itu yang penyata bank katakan; jumlah bersihnya sifar kerana tiada duit meninggalkan bisnes. Diuji: baki akhir laporan sama tepat dengan `AccountBalanceService::total()` |
| 2026-10-30 | Ringkasan Cukai hanya menolak perbelanjaan yang ditanda boleh tolak | Pelan minta 'perbelanjaan boleh tolak (ikut kategori)'; perbelanjaan tidak boleh tolak ditunjukkan berasingan supaya pemilik nampak kedua-duanya |
| 2026-10-30 | **Dibuang semasa pengesahan:** baris 'tabung cukai kurang RM X daripada anggaran' | Ia membandingkan baki tabung dengan *pendapatan bercukai*, sedangkan cukai yang perlu dibayar hanya sebahagian daripada angka itu. Baris itu akan menakutkan pemilik tanpa sebab. Dua angka kini berdiri bersebelahan, dan nota menjelaskan hubungannya |
| 2026-10-30 | Untung projek tolak masa pemilik: minit boleh-bil × kadar sejam projek | Kerja yang nampak berbaloi atas hasil masih boleh merugikan setelah jam dikira. Data demo menunjukkannya: 'Sistem Stok' pada margin **−4%** (52 jam × RM80 = RM4,160 terhadap RM4,000 diterima) |
| 2026-10-30 | Masa tidak boleh-bil dan pemasa yang sedang berjalan tidak dikira sebagai kos | Minit tidak boleh-bil nyata, tetapi bukan kos menyampaikan kerja itu; pemasa berjalan belum diputuskan apa-apa, jadi angka tidak patut bergerak |
| 2026-10-30 | Projek tanpa kadar sejam tiada kos tenaga kerja, bukan ralat | Marginnya sama dengan margin kasar — jujur, walaupun kurang berguna; nota pada halaman mengajak pemilik menetapkan kadar |
| 2026-10-30 | Margin `null` (dipapar '—') bila tiada duit masuk, bukan 0% | Projek tanpa hasil tiada margin untuk diperkatakan |
| 2026-10-30 | `minutes` disimpan pada `time_entries`, bukan dikira daripada dua timestamp | Sama seperti amaun baris invois: peraturan pembundaran yang bertukar kemudian tidak boleh menulis semula kerja yang sudah dilog |
| 2026-10-30 | Satu pemasa sahaja; memulakan yang kedua menghentikan yang pertama | Pemilik jelas sudah berpindah kerja; membiarkan dua berjalan akan mengira minit yang sama dua kali |
| 2026-10-30 | Pemasa dihentikan dalam beberapa detik masih dikira satu minit | Sifar akan terbaca seperti tiada apa-apa berlaku |
| 2026-10-30 | Pemasa berjalan dikongsi pada setiap halaman; masa berlalu dikira dalam browser | Pelayan sudah beritahu bila ia mula; jam yang perlukan satu permintaan setiap detik untuk berdetik ialah pertukaran yang buruk |
| 2026-10-30 | Ramalan: invois tertunggak dan caj berulang yang sudah lepas dibawa ke awal tingkap | Duit itu terhutang sekarang; meninggalkannya akan terbaca seperti ia sudah dihapus kira |
| 2026-10-30 | Chart ramalan bertukar merah bila baki jatuh bawah sifar | Itu satu-satunya perkara pada graf itu yang memerlukan tindakan |
| 2026-10-30 | Import bank: pratonton dahulu, tiada apa-apa ditulis sebelum pemilik lihat | Statement ialah fail besar daripada pihak ketiga; menulisnya terus akan menggandakan transaksi tanpa jalan pulang |
| 2026-10-30 | Padanan import: amaun sama + dalam ±3 hari, dan setiap transaksi sedia ada hanya boleh dituntut **sekali** | Tarikh bayaran dibuat dan tarikh bank catat jarang sama. Tanpa 'sekali', dua caj parking RM50 dalam minggu sama akan padan dengan transaksi yang sama dua kali dan satu daripadanya hilang |
| 2026-10-30 | Pembaca CSV mengesan pembatas sendiri dan membuang BOM | Export bank Malaysia sama berkemungkinan guna titik bertindih sebagai koma; BOM daripada Excel akan menjadikan tajuk kolum pertama tidak boleh dibaca |
| 2026-10-30 | Tarikh samar dibaca **hari dahulu** secara lalai (03/04 = 3 April), dengan pilihan override | Meneka arah lain akan menyalah-tarikhkan satu penyata penuh secara senyap |
| 2026-10-30 | `(120.50)` dan `120.50 DR` dibaca sebagai negatif | Itu cara penyata bank menulis debit |
| 2026-10-30 | **Pepijat dijumpai semasa ujian:** import menulis terus ke model, memintas `StoreTransaction` | Duit masuk yang diimport tidak sampai ke tabung langsung — premis seluruh aplikasi pecah secara senyap. Kini melalui action yang sama seperti borang |
| 2026-10-30 | Pratonton import disimpan dalam **sesi**, bukan fail pada disk | Import ialah satu duduk; penyata bank bukan fail yang patut ditinggalkan bersepah |
| 2026-10-30 | Portal klien: URL bertanda tangan, tiada login, draf tidak pernah ditunjukkan | Memegang pautan itulah kebenarannya, dan ia tidak boleh diedit untuk menunjuk invois orang lain. Pautan ke draf bermakna bil yang pemilik belum siap tulis |
| 2026-10-30 | Penukar bahasa portal guna pautan yang **ditandatangani pelayan**, bukan gabungan string | Tanda tangan meliputi query string; menambah `&lang=` selepas itu membatalkannya. Diuji kedua-dua arah |
| 2026-10-30 | Portal tidak pernah menyentuh bahasa pemilik | Pelawat tiada akaun; menukar locale aplikasi akan menjejaskan tab pemilik yang terbuka |
| 2026-10-30 | Audit log guna event Eloquent, bukan hook pada Action | Tiada laluan kod yang boleh mengubah transaksi atau invois tanpa direkod — termasuk `->update()` yang ditaip dalam tinker. Diuji |
| 2026-10-30 | Audit log simpan hanya medan yang **bergerak**, dan nama pengguna disalin | Log yang mengulang seluruh baris tidak boleh dibaca; nama disalin supaya baris masih berbunyi penuh selepas akaun itu dipadam |
| 2026-10-30 | Webhook `owner_draw`: beratur, https sahaja, ditandatangani HMAC, kegagalan dilog bukan dibangkitkan | Payload itu menyatakan berapa pemilik bayar diri sendiri. Tracker yang tergendala tidak boleh menghalang pemilik merekod bayaran itu |
| 2026-10-30 | Service worker **tidak cache apa-apa** | Inertia menghidangkan bundle berversi dan payload yang mencerminkan pangkalan data sekarang. Cache yang menghidangkan shell lapuk akan menunjukkan baki semalam tanpa sebarang tanda — lebih teruk daripada halaman yang tidak dimuat. Sokongan luar talian untuk aplikasi perakaunan perlukan reka bentuk sync, bukan fetch handler |
| 2026-10-30 | Manifest dihidangkan daripada route, bukan fail statik | Nama pada skrin utama patut nama bisnes itu sendiri, yang ada dalam tetapan |
| 2026-10-30 | Dua meta `theme-color` dengan `media`, bukan satu dalam manifest | Pelan minta warna berbeza untuk light/dark; manifest hanya memegang satu |
| 2026-10-30 | Backup: hanya `storage/app/private` + `storage/app/public` + dump DB, bukan `base_path()` | Kod ada dalam version control. Yang tiada di sana ialah buku-buku dan fail yang dilampirkan padanya — resit dan logo |
| 2026-10-30 | Disk destinasi backup dibaca daripada `BACKUP_DISKS` dalam `.env` | Menghalakannya ke S3 atau Spaces kemudian ialah satu baris `.env`, bukan suntingan config |
| 2026-10-30 | `backup:clean` 02:00 sebelum `backup:run` 02:15 | Supaya backup malam itu tidak pernah menjadi yang dibuang oleh clean-up |
| 2026-10-30 | **Payment gateway ditangguh** — keputusan pemilik | ToyyibPay dan Billplz ada API berbeza, dan keduanya perlukan akaun merchant, kunci API, dan URL webhook yang boleh dicapai dari internet. Membinanya tanpa itu bermakna kod integrasi yang kelihatan siap tetapi tidak pernah diuji terhadap perkhidmatan sebenar. Bayaran manual sudah berfungsi: portal klien memaparkan maklumat bank, dan "Rekod Bayaran" mengagihkan duit ke tabung seperti biasa |
| | | |