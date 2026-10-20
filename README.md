# 🚗 Clásicos Autos

**Clásicos Autos** is a public marketplace for classic cars (model year 1989 or older), with a Cuban "Postal" visual identity.
Sellers list a car with up to eight photos, and buyers browse, search, compare, bookmark and message the seller. An admin approves every listing before it goes public. The core rule is that **nothing unreviewed is ever public**: a listing is visible only while it is approved and its owner's account is active.

---

## 🌐 Live Demo

**[khaki-donkey-838388.hostingersite.com](https://khaki-donkey-838388.hostingersite.com/)**

| Role | Email | Password | Notes |
|---|---|---|---|
| 🛡️ Admin | `admin@admin.com` | `123456` | Lands on `/admin/users` |
| 👤 App user | `review@example.com` | `password` | Listings in every state (approved, pending, rejected), bookmarks, conversations, an open report |
| 👤 App user | `stranger@example.com` | `password` | Seller with 13 approved listings and a custom-model listing |
| 👤 App user | `buyer@example.com` | `password` | Buyer in a conversation with `review@example.com` |

Sign in with each role to see how access changes. This is a shared public demo with made-up data. It may be reset at any time, and it sends no real email.

---

## ✨ Features

### 👀 Guest
- 📜 Browse approved listings, with search (name, maker, model), multi-select brand chips and pagination
- 🔎 Advanced search across five fieldsets
- 🖼️ Listing detail with photo gallery, specs and the seller's contact details
- ⚖️ Compare two listings side by side, with the differences highlighted
- 📄 Privacy policy, terms and conditions, cookie policy
- 🔐 Sign up, Sign in, Forgot/Reset password

### 👤 App User
- 🚘 Create listings with up to eight photos, optimized into WebP variants with **Intervention Image**
- ✏️ Edit listings and their photos (any edit sends the listing back to approval), delete own listings
- 📋 Profile with "My car listings", status stamps and rejection reasons
- ⭐ Bookmark listings
- 💬 Message sellers, with new messages arriving live and an unread badge in the header
- 🚩 Report the other party's message
- ✉️ Contact the admin
- 👤 Change password, delete own account

### 🛡️ Admin
- ✅ Listings queue: approve, reject with a written reason, promote a custom model to the list
- 👥 Users: search, view listings, suspend (7 days, 30 days, indefinite) / unsuspend, delete
- 🚩 Reports: dismiss, delete the message, or suspend the author in one action
- 💬 Read every conversation and delete any message
- 📥 Feedback page for contact messages, marked as handled
- 🏭 Makers and models: create, edit, activate / deactivate, delete when unreferenced
- 🔑 Change own password

### ⚙️ Platform
- 🔄 Every new or edited listing returns to `pending`
- 🙈 Suspending a user hides their listings without changing them; unsuspending restores each listing's own status
- ⏰ Expired suspensions are lifted at sign-in, on the user's next request, or by the hourly `suspensions:lift` command
- 🔎 Server-rendered SEO: meta and Open Graph tags, `sitemap.xml`, `Car` + `Offer` structured data on listing pages

---

## 🛠️ Tech Stack
- **Framework**: [Laravel 13](https://laravel.com/) on PHP 8.4
- **Database**: [MySQL](https://www.mysql.com/)
- **Templating**: [Blade Templates](https://laravel.com/docs/blade) + vanilla JavaScript modules (progressive enhancement, no frontend framework)
- **Styling**: [Tailwind CSS 4](https://tailwindcss.com/), built with [Vite 8](https://vite.dev/)
- **Image Handling**: [Intervention Image 4](https://image.intervention.io/) (full, card and thumbnail WebP variants)
- **Real-time**: [Pusher Channels](https://pusher.com/channels) + [Laravel Echo](https://laravel.com/docs/broadcasting)
- **Queue**: [Laravel Queues](https://laravel.com/docs/queues) (database driver) for the password-reset email and broadcasts
- **Testing**: [PHPUnit](https://phpunit.de/), run in parallel with [ParaTest](https://github.com/paratestphp/paratest)

---

## 🚀 Getting Started

### 1. Clone the Repository
```bash
git clone https://github.com/bojan-ski/laravel-js--clasicos-autos.git
cd laravel-js--clasicos-autos
```

### 2. Install Dependencies
```bash
composer install
npm install
```

### 3. Create the Databases
Create two MySQL databases: `laravel_clasicos_autos` for the app and `laravel_clasicos_autos_testing` for the test suite (configured in `phpunit.xml`).

### 4. Environment Setup - .env
```bash
cp .env.example .env
php artisan key:generate
```

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_clasicos_autos
DB_USERNAME=
DB_PASSWORD=

# The admin account (created by the seeder, password at least 6 characters)
ADMIN_EMAIL=
ADMIN_PASSWORD=

QUEUE_CONNECTION=database

# Live messaging (use "log" in development)
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=
```

### 5. Run Migrations & Seed Database
```bash
php artisan migrate --seed
php artisan storage:link
```
The seeder creates the admin from `ADMIN_EMAIL` and `ADMIN_PASSWORD` (it refuses to run without them), plus the makers and models.

**Demo data.** For the full demo set, seed the walkthrough scenario on a fresh database. It runs only in the `local` and `testing` environments:
```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=WalkthroughScenarioSeeder
```

### 6. Start Servers
```bash
composer run dev
```
This starts the web server, the queue worker and Vite. The app runs at http://localhost:8000.

### 7. Scheduled Tasks
Expired suspensions are lifted hourly by the scheduler:
```bash
php artisan schedule:work
```

---

## 🔑 Dummy Users

All dummy accounts use the password **`password`**. The admin uses the `ADMIN_EMAIL` / `ADMIN_PASSWORD` from `.env`.

| Role | Email | State |
|---|---|---|
| 👤 App user | `review@example.com` | Listings in every state, bookmarks, conversations, an open report, a contact message |
| 👤 App user | `stranger@example.com` | 13 approved listings and a custom-model listing waiting for promotion |
| 👤 App user | `buyer@example.com` | Buyer in a conversation with `review@example.com` |
| 👤 App user | `seller01@demo.test` … `seller29@demo.test` | Active sellers with listings, bookmarks and conversations |
| 🚫 App user | `seller30@demo.test` | Suspended for 30 days (sign-in is refused, listings hidden) |
| 👤 App user | `seller@walkthrough.test` | Active, in a thread with the abuser below |
| 😠 App user | `abuser@walkthrough.test` | Active, two approved listings, sent an abusive message to report |
| ⌛ App user | `expired@walkthrough.test` | Suspension expired an hour ago, lifted on the next sign-in |
| ➕ — | `buyer@walkthrough.test` | Left free on purpose: register a new account with it |

> Any install reachable from the internet should delete these accounts or change their passwords: anyone who reads this README knows them.

---

## 🧪 Testing
```bash
php artisan test --parallel --processes=8 --compact
```
Each process uses its own `laravel_clasicos_autos_testing_test_N` database, created on first use. Add `--recreate-databases` if a run reports missing tables after a migration change. To run a single file while you work, run it without `--parallel`.

---

## 👨‍💻 Author
Developed with ❤️ by BPdevelopment (bojan-ski)
