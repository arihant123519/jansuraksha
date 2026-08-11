# JanSuraksha — Frontend/Backend Integration Audit & Remediation

**Date:** 2026-06-04
**Scope:** End-to-end audit of the Next.js frontend (`/frontend`) and Laravel backend (`/backend`), all client↔server integration points, reproduction of connectivity failures, root-cause analysis, fixes, and validation.

**Stack:** Laravel 12 + Sanctum 4 (token auth, MySQL + spatial) · Next.js 16.2.7 / React 19 + Axios · two principals (citizen via mobile OTP, police via username/password).

---

## 1. Executive summary

The two apps were largely wired correctly at the transport layer — **CORS, Sanctum bearer-token auth, the OTP test bypass, quota, and police login all worked on first contact.** However, **four of the application's core user journeys were broken end-to-end**, three of them returning HTTP 500 and one returning HTTP 401. All four are now fixed and covered by automated tests.

| # | Severity | Symptom (reproduced) | Root cause | Status |
|---|----------|----------------------|-----------|--------|
| 1 | **Critical** | Public "Track complaint" page always failed | Frontend called auth-protected `/api/complaints/{id}` instead of public `/api/track/{id}` → **401** | ✅ Fixed |
| 2 | **Critical** | "Submit report" (the core feature) failed | Evidence storage hard-coded `Storage::disk('s3')`; the S3 flysystem adapter was **not installed** and AWS creds were empty → **500** | ✅ Fixed |
| 3 | **High** | Police PDF export failed | `barryvdh/laravel-dompdf` referenced but **not installed** → **500** | ✅ Fixed |
| 4 | **High** | Fresh `migrate` / CI / `composer setup` fails | Custom migrations named `000002…` sort **before** the stock `users` table (`0001_01_01_000000`), so `complaints` FK to `users` can't form → **migration aborts** | ✅ Fixed |
| 5 | Medium | TS contract drift; weak error UX | `Complaint` type missing `is_flagged`/`evidence_count` (patched with `as unknown as`); generic catch-all toasts; no 401 handling | ✅ Fixed |

**Validation:** 21 backend tests (unit + feature/integration) pass; frontend `tsc` and production build are green; every fix was also reproduced and re-verified live with `curl` against a running server.

---

## 2. Method

1. **Mapped** every integration surface — backend routes/controllers/middleware/CORS/auth, and the frontend Axios layer + every page that calls the API.
2. **Ran both apps.** Backend via `php artisan serve`; confirmed PHP 8.2.12, Node 22.19, MySQL up, DB migrated and seeded.
3. **Reproduced** each journey with `curl`, mirroring exactly what the frontend sends (headers, multipart, tokens, origins).
4. **Root-caused** each failure from the stack traces and source.
5. **Fixed**, then **re-ran** the same reproductions and added regression tests.

> **Environment note:** On this machine port **8000 is already occupied by a different project** (`social-team-automation`). Laravel's `artisan serve` silently reported `:8000` while another server answered — so the audit ran the backend on `:8001`. This is not a code defect but it *will* misroute the frontend (whose default base URL is `localhost:8000`). See Risks §6.

---

## 3. Findings, root causes, and fixes

### Issue 1 — Public complaint tracker returns 401 (Critical)
**Reproduction**
```
GET /api/complaints/JS-2026-XXXX     (no token, as the track page sent) → 401
GET /api/track/JS-2026-XXXX          (the public route)                  → 200/404
```
**Root cause** — `routes/api.php` exposes two handlers for the same controller method: `GET complaints/{id}` behind `auth:sanctum` (for a logged-in citizen) and a **public** `GET track/{id}`. The `/track` page is unauthenticated but called `getComplaint()` → `/complaints/{id}`, so every anonymous lookup hit the protected route and 401'd.

**Fix** — Added a dedicated public client function and pointed the track page at it.
- `frontend/lib/api.ts`: new `trackComplaint(id) → GET /track/${id}` (kept `getComplaint` for authenticated use).
- `frontend/app/track/page.tsx`: uses `trackComplaint`; distinguishes 404 ("no complaint with that ID") from network errors.

### Issue 2 — Complaint submission & evidence retrieval return 500 (Critical)
**Reproduction**
```
POST /api/complaints (multipart, 1 image) → 500
  "Class League\Flysystem\AwsS3V3\PortableVisibilityConverter not found"
```
**Root cause** — `ComplaintController::store()` and `::show()` and `ExportController::pdf()` hard-coded `Storage::disk('s3')`. But:
- `league/flysystem-aws-s3-v3` was **never installed** (only `flysystem-local` is in `vendor/`), so the `s3` disk can't even be constructed; and
- AWS credentials in `.env` are empty.

So **every** submission 500'd, and any complaint detail/track that generated `temporaryUrl()` for evidence would 500 too. This broke the application's primary purpose.

**Fix** — Made evidence storage disk-agnostic and installed the adapter for production.
- New `config/evidence.php` — `disk` (env `EVIDENCE_DISK`, default `public` for dev) and `url_ttl_minutes`.
- New `app/Services/EvidenceStorageService.php` — centralizes `store()` and `url()`. `url()` uses a **pre-signed `temporaryUrl()` when the disk supports it (S3), and falls back to a permanent `url()` for local disks** — never throwing on a single bad file.
- Refactored `Api\ComplaintController` (store + show) and `Police\ExportController` (pdf) to use the service.
- `EVIDENCE_DISK=public` added to `.env` / `.env.example`; ran `php artisan storage:link`.
- Installed `league/flysystem-aws-s3-v3` so production (`EVIDENCE_DISK=s3`) works without code changes.

### Issue 3 — Police PDF export returns 500 (High)
**Reproduction**
```
GET /api/police/export/pdf → 500  "Class Barryvdh\DomPDF\Facade\Pdf not found"
```
**Root cause** — `ExportController` imports `Barryvdh\DomPDF\Facade\Pdf`, but `barryvdh/laravel-dompdf` was not in `composer.json` or `vendor/`. (CSV export was unaffected — it streams natively — and returned 200.)

**Fix** — Installed `barryvdh/laravel-dompdf:^3.1`. Export now returns `200 application/pdf` (verified: an 858 KB `%PDF` document).

### Issue 4 — Fresh database migration fails (High, latent)
**Reproduction** — A clean `migrate` (CI, `composer setup`, the test DB) aborts:
```
SQLSTATE[HY000]: errno 150 "Foreign key constraint is incorrectly formed"
  alter table `complaints` add constraint … foreign key (`user_id`) references `users`(`id`)
```
**Root cause** — Laravel runs migrations in filename order. The custom files were named `000002_…`–`000007_…`, which sort **before** the stock `0001_01_01_000000_create_users_table`. So `complaints` (and `rate_limit…`) tried to FK `users` before it existed. The live dev DB only worked because it had been migrated in an unusual historical batch order.

**Fix** — Renamed the six custom migrations to `0001_01_01_0001NN_…` so they sort **after** the stock `users`/`cache`/`jobs` tables while preserving their relative order, and reconciled the existing dev DB's `migrations` table (UPDATE of the recorded names) so it neither re-runs nor reports pending. `migrate:status` is clean; the fresh test DB now migrates successfully.

### Issue 5 — Type-contract drift & error-handling resilience (Medium)
**Root cause / fix**
- `frontend/types/index.ts`: added `is_flagged?`, `evidence_count?`; made `evidence_files?`/`status_logs?` optional (they only appear on detail endpoints); corrected `id` to `number`. Removed the `as unknown as {…}` cast in the police dashboard.
- `frontend/lib/api.ts`: added an Axios **response interceptor** that clears a stale token on 401 and tags network/CORS failures (`isNetworkError`) so the UI can tell "server said no" from "couldn't reach server."
- `frontend/app/police/dashboard/page.tsx`: on 401/403 it clears creds and redirects to login instead of a generic toast.
- Backend `bootstrap/app.php`: unexpected API exceptions now return a stable JSON envelope `{message, reference}` with the reference logged (no stack-trace leakage), while validation/auth/HTTP/model-not-found keep their correct status codes.
- `ComplaintController::store()` wraps the transaction; an evidence-storage failure now rolls back cleanly and returns **502** with a clear message (no orphaned complaint or quota burn) instead of a raw 500.

---

## 4. What was verified working (no change needed)
- **CORS** — preflight correctly echoes `Access-Control-Allow-Origin: http://localhost:3000` with `Access-Control-Allow-Credentials: true` (driven by `CORS_ALLOWED_ORIGINS`).
- **Auth** — citizen OTP verify (incl. the `000000` local-only bypass), police login, bearer-token enforcement, and the `EnsureRole` guard (a citizen token gets **403** on police routes).
- **Quota** — IST daily reset and remaining-count reporting.
- **Police list scoping & status FSM** — jurisdiction filtering, forward-only transitions (invalid → 422), reject-requires-reason (→ 422), and audit-log writes.
- **CSV export** — streams `text/csv` with BOM.

---

## 5. Validation

**Backend — `php artisan test`: 21 passed (55 assertions).** New coverage:
- `tests/Unit/EvidenceStorageServiceTest.php` — store persists; `url()` falls back to a public URL on local disks without throwing (the old 500); null-safe.
- `tests/Feature/ComplaintFlowTest.php` — public `/track` works anonymously; `/complaints` requires auth; submit → 201 with the file actually on disk; evidence retrievable via track; validation rejects bad plates.
- `tests/Feature/PoliceWorkflowTest.php` — login, jurisdiction scoping, valid/invalid transitions, reject-reason rule, **PDF→200 `application/pdf`**, CSV stream, and citizen-token-forbidden.
- Test infra: `phpunit.xml` points feature tests at a MySQL `jansuraksha_test` DB (SQLite can't create the spatial `POINT`/spatial index); fixed the stale `UserFactory` (it referenced non-existent email/password columns) and added `PoliceUser`/`Complaint` factories.

**Frontend** — `tsc --noEmit` clean; `next build` succeeds (all 7 routes).

**Live re-checks (curl, port 8001):** submit → **201**; anonymous track → **200** with a working `file_url`; PDF export → **200 %PDF**; police transition → **200** + audit row; invalid transition / missing reason → **422**.

---

## 6. Remaining risks & recommendations

**Operational**
1. **Port 8000 conflict (this machine).** Another project answers on `:8000`, so the frontend's default base URL would hit the wrong backend. Free port 8000 for JanSuraksha, or set `frontend/.env.local` `NEXT_PUBLIC_API_URL` (a documented `.env.example` was added). `NEXT_PUBLIC_*` is build-time — rebuild after changing.
2. **Production S3 readiness.** Set `EVIDENCE_DISK=s3` and real `AWS_*` creds in production. The S3 adapter is now installed and the URL code already prefers pre-signed URLs there.

**Security / correctness (pre-existing, out of the connectivity scope — recommend follow-up)**
3. **OTP bypass.** Backend correctly gates `000000` to `local`/`testing`, but the **frontend login always sends `000000`** and never calls `sendOtp()` — real OTP delivery is never exercised. Wire the two-step flow before any non-local deploy.
4. **Geocoding disabled in dev.** With no `GOOGLE_MAPS_API_KEY`, new complaints get `area_state = null`, so **non-admin officers (jurisdiction-scoped) won't see them**. Expected given the missing key, but worth a seeded/fallback area for local testing.
5. **`changed_by_type` dead code.** `Police\ComplaintController::update()` writes `changed_by_type => 'police'`, but the column doesn't exist and the field isn't in `$fillable`, so it's silently dropped (no error). Either add the column (if polymorphic audit is intended) or remove the line.
6. **No rate-limiting on police endpoints** (list/export) — consider a per-officer throttle to limit bulk exfiltration.
7. **Evidence integrity** — no content hash stored; consider persisting a SHA-256 per file for tamper-evidence.

---

## 7. Changed files

**Backend**
- `bootstrap/app.php` — structured JSON error envelope for unhandled API exceptions.
- `config/evidence.php` *(new)* — configurable evidence disk + URL TTL.
- `app/Services/EvidenceStorageService.php` *(new)* — disk-agnostic store + resilient URL.
- `app/Http/Controllers/Api/ComplaintController.php` — use service; transactional storage failure → 502.
- `app/Http/Controllers/Police/ExportController.php` — use service for thumbnails.
- `app/Models/{Complaint,PoliceUser}.php` — `HasFactory`.
- `database/migrations/0001_01_01_0001NN_*` *(renamed ×6)* — correct FK ordering.
- `database/factories/{UserFactory(fixed),PoliceUserFactory(new),ComplaintFactory(new)}.php`.
- `phpunit.xml` — MySQL test DB + `EVIDENCE_DISK`.
- `tests/Unit/EvidenceStorageServiceTest.php`, `tests/Feature/{ComplaintFlowTest,PoliceWorkflowTest}.php` *(new)*, `tests/Feature/ExampleTest.php` (health check).
- `.env`, `.env.example` — `EVIDENCE_DISK`, documented CORS/Sanctum keys.
- `composer.json` / `composer.lock` — `barryvdh/laravel-dompdf`, `league/flysystem-aws-s3-v3`.

**Frontend**
- `lib/api.ts` — `trackComplaint()`; response interceptor (401 + network tagging).
- `app/track/page.tsx` — use public tracker; better error messages.
- `app/police/dashboard/page.tsx` — 401/403 redirect; removed unsafe cast.
- `types/index.ts` — accurate `Complaint` contract.
- `.env.example` *(new)* — documents `NEXT_PUBLIC_API_URL`.
