# UNOFFICIAL IEEE Volunteering — Volunteering of the Future

A modernised take on [volunteer.ieee.org](https://volunteer.ieee.org): IEEE members find and apply to volunteering opportunities, log their hours, collect endorsements and download a volunteer CV, while anyone can create opportunities (with co-owners) and see the impact of their volunteers. Admins get an analytics suite and tools to manage users, skills, opportunity types and the daily sync with volunteer.ieee.org.

Built on Laravel 13 (PHP 8.3+), MySQL, Blade + Tailwind + Alpine.js, Chart.js and Dompdf. It started from the Society of RSE site's codebase and was adapted to the IEEE Volunteering brand and workflows. The design follows the recommendations of the UX review in `../IEEE-volunteer.ieee.org`.

## Features

**Volunteers and organisers (every account can do both)**
- **Opportunity search** with all of volunteer.ieee.org's filters: skills, upskills, category, region, section, society, duration, experience level, online, accepting applicants, and matching your grade. It adds an active-filter count, removable chips, an explained sort and a **map view**.
- **Explainable match score** on every opportunity: skills 60%, membership grade 20%, region 20%, shown with a "why" popover.
- **Opportunity page** with key facts, eligibility, the skills you'll build, organisers, confirmed volunteers, similar opportunities, save, share and **clone ("use as template")**.
- **Create/edit wizard** in four steps with help text, inline skill creation, eligible grades, and up to 9 **co-owners**. You can save a draft or publish, and clone one of your past opportunities.
- **Owner workspace**:
  - Applicants with match score and motivation; accept or decline with a note.
  - Mark completed with a rating, an endorsement and endorsed skills.
  - Hour approvals and team (owner) management.
  - Impact KPIs and charts, plus a CSV export.
- **Applications and hours**: apply with motivation, withdraw or re-apply, log hours (approved by owners), and rate your experience.
- **My Opportunities** is split into Volunteering, Managing and Saved, with "needs action" badges.
- **Dashboard**:
  - Your impact: hours, completed work, endorsements, an hours-per-month chart and skills used.
  - Recommendations, an onboarding checklist, profile strength and an activity feed.
  - For organisers, the impact of *their* volunteers: people engaged, hours, countries, completion rate and top volunteers.
- **Volunteer directory** with filters (skills, region, section, society, grade, country, availability, endorsed) and labelled metrics.
- **Profile = web CV**, plus a branded multi-page **PDF CV** where you choose the sections.
- Email notifications (log mailer by default) for applications, decisions, completions and co-owner invites. All templates are editable by admins.
- Accessibility widget: text size, contrast, legible font, reduced motion and more. Every chart has a table view.
- The site is **not indexable**: `robots.txt` disallows all, plus `noindex` meta and an `X-Robots-Tag` header. Admins can change this in Settings.

**Admins** (`role = admin`; there are only two roles, `user` and `admin`)
- **Analytics**: KPIs with change versus the previous period, stats over time, the engagement funnel, category performance, skills supply versus demand, search analytics (top and zero-result terms), cohort retention and leaderboards, with CSV export.
- **IEEE sync**: refresh opportunities from the public volunteer.ieee.org API on demand, with run history. It also runs daily via the scheduler.
- Opportunities (feature, delete), users (role, suspend, delete with a summary first, CSV export), inline skill management (merge, CSV import/export), opportunity types, and an audit log.
- Pages, menus, media, settings and email templates (from the original CMS).

## Local setup

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
mysql -uroot -e "CREATE DATABASE ieee_volunteering CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --seed
php artisan storage:link
php artisan serve --port=8002
```

Demo accounts (local only — the password is `password`):

| Account | Email |
|---|---|
| Admin | `admin@example.com` |
| Showcase volunteer and organiser | `volunteer@volunteer-demo.test` |
| ~420 other demo volunteers | `*@volunteer-demo.test` |

## Data: fake history

`php artisan migrate --seed` always loads the reference data: opportunity types, the skills taxonomy, pages, menus, email templates and the first admin.

Unless `SEED_DEMO_DATA=false`, it also generates **backdated demo data** spanning 30 months, so every chart has history:
- About 420 volunteers across all 10 regions.
- The real opportunities from a bundled volunteer.ieee.org snapshot.
- About 160 locally created opportunities.
- Applications, hours, ratings, endorsements, saves, searches and the activity stream.

All demo accounts use the reserved `@volunteer-demo.test` domain, so they can never receive email. Everything people do after deployment is real and accumulates alongside the demo data. When you no longer want it:

```bash
php artisan demo:purge
```

This removes demo accounts, their opportunities and history. Real accounts and imported IEEE opportunities are kept.

## Refreshing opportunities from volunteer.ieee.org

- **Admin → IEEE sync → Refresh now**, or `php artisan opportunities:sync`.
- It runs daily at 03:15 when the scheduler cron is installed (see below). Set `IEEE_VOLUNTEER_SYNC_DAILY=false` to disable that.
- Opportunities are matched on the API's `opportunityId`. New ones are created, changed ones updated, and ones that disappear from the feed are marked completed.
- If a user adds their **IEEE member number** to their profile, opportunities they created on volunteer.ieee.org are linked to their account on the next sync. Until then, applications to imported opportunities are reviewed by admins.

## Deploying (MySQL)

1. PHP 8.3+ with `pdo_mysql`, `mbstring`, `dom`, `gd`, `fileinfo`. MySQL 8+ (or MariaDB 10.6+). `public/build` is committed, so Node isn't needed on the server.
2. Point the web root at `public/`.
3. Configure `.env`:
   - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`
   - `DB_*`
   - `MAIL_*` (e.g. SMTP)
   - `SEED_ADMIN_EMAIL` and `SEED_ADMIN_PASSWORD`. In production a random admin password is generated and printed if none is set.
   - Optionally `DEMO_PASSWORD` (demo accounts get a random password in production unless set).
4. Run:
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan key:generate
   php artisan migrate --force --seed
   php artisan storage:link
   php artisan optimize
   ```
5. Add the scheduler cron so the daily sync runs:
   ```
   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
   ```

Timestamps are stored in UTC (`DB_TIMEZONE=+00:00`), whatever the server's timezone.

## Tests

```bash
php artisan test
```

Tests run on in-memory SQLite. The query layer is portable: time bucketing goes through `App\Support\DateBucket`.

## Where things live

| Area | Files |
|---|---|
| Domain models | `app/Models` (Opportunity, Application, HourLog, Endorsement, Profile, Skill, Category, Activity, SearchLog, SyncRun) |
| Matching, search | `app/Support/MatchScore.php`, `app/Support/OpportunitySearch.php` |
| Impact and CV | `app/Services/ImpactStats.php`, `app/Services/VolunteerCv.php`, `resources/views/cv/pdf.blade.php` |
| IEEE API sync | `app/Services/IeeeOpportunitySync.php`, `app/Console/Commands/SyncIeeeOpportunities.php` |
| Admin analytics | `app/Services/PlatformAnalytics.php`, `app/Http/Controllers/Admin` |
| Reference data | `config/volunteering.php` (regions, sections, grades, societies, durations, upskills, statuses) |
| Demo data | `database/seeders/DemoDataSeeder.php`, `database/seeders/data/ieee-opportunities.json` |
| Charts | `resources/js/charts.js`, `<x-chart>` |
