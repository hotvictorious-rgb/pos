# Hysam Ventures – AI Agent Engineering Rules

> **Version:** 1.1.0  
> **Effective Date:** 2026-08-20  
> **Repository:** `https://github.com/hotvictorious-rgb/hysam.git`  
> **Applies to:** All AI agents (Antigravity, Copilot, Cursor, GPT, Claude, Gemini) working on this repository.  
> **Status:** MANDATORY – Every AI agent MUST strictly read and adhere to these rules before, during, and after making any change.

---

## 1. Project Identity & Governance

This repository is **Hysam Ventures Inventory Management System**.

- **Repository Remote:** `https://github.com/hotvictorious-rgb/hysam.git`
- **Framework:** Laravel 10 (PHP 8.3)
- **Frontend:** Blade templates ONLY – no React, no Vite, no Node.js
- **Database:** MySQL 8.0 / MariaDB (Eloquent ORM)
- **Cache/Queues:** Redis on VPS, file driver on shared hosting
- **Authentication:** Laravel Sanctum
- **No AI/external APIs:** Gemini and all external AI services have been permanently removed; system is 100% self-contained and offline-capable.
- **Target hosting:** Whogohost VPS (primary), Whogohost Shared Hosting (limited)

---

## 2. Non-Negotiable Mandatory Rules

These rules are absolute. Any deviation or drift is strictly prohibited.

### 2.1 Always Commit All Changes & Update CHANGELOG (MANDATORY)

```
✅ MUST record every single change made and append it to CHANGELOG.md immediately.
✅ MUST run `git add` and `git commit` for all changes with descriptive Conventional Commits.
✅ NEVER leave uncommitted working changes or unrecorded modifications.
✅ Ensure Git history and CHANGELOG.md remain 100% synchronized with codebase state.
```

### 2.2 No Node.js, Vite, or React

```
❌ DO NOT install npm packages or add package.json dependencies
❌ DO NOT run `npm install`, `npm run dev`, or `npm run build`
❌ DO NOT create React or Vue components
❌ DO NOT add Vite or Webpack build configurations
✅ DO use Blade templates for all UI work
✅ DO use vanilla CSS or CDN assets for styling
```

### 2.3 No External AI APIs

```
❌ DO NOT add GeminiService or any third-party AI integration
❌ DO NOT add GEMINI_API_KEY or AI tokens to .env files
❌ DO NOT make external HTTP calls for AI-assisted features
✅ DO keep the system completely offline-functional and self-hosted
```

### 2.4 No Breaking the Main Branch

```
❌ DO NOT push directly to main/master without a clean, passing test suite
❌ DO NOT merge destructive or unverified migrations
✅ DO keep main/master clean, deployable, and verified at all times
✅ DO verify routes and migrations before committing
```

### 2.5 Mandatory In-App User Guide & FAQ Synchronization (MANDATORY)

```
✅ Whenever a new feature, workflow, or route is added or modified in the application:
   1. The AI agent MUST read `resources/views/help/index.blade.php` FIRST.
   2. The AI agent MUST update the in-app FAQ accordions and visual step-by-step guides.
   3. Ensure the in-app guide reflects the exact current state of the application cleanly so workers never get confused.
```

---

## 3. Mandatory AI Workflow Protocol

Every AI agent working on this project MUST follow this exact sequence:

```
Step 1: SELF-CHECK
   - Run `git status` to verify current branch status and clean tree.
   - Confirm origin remote is https://github.com/hotvictorious-rgb/hysam.git.

Step 2: PLAN FIRST
   - For multi-file changes (>2 files) or architectural changes, create/update implementation plan.

Step 3: IMPLEMENT & VALIDATE
   - Make precise, idempotent changes according to PSR-12 and Laravel standards.
   - Clear and verify caches (`php artisan route:list`, `php artisan config:clear`).
   - Run test suite if applicable.

Step 4: RECORD IN CHANGELOG
   - Append concise, categorized details of what was added/changed/fixed to `CHANGELOG.md`.

Step 5: GIT COMMIT
   - Stage all modified/untracked files: `git add .`
   - Commit with atomic Conventional Commit message: `git commit -m "..."`.
```

---

## 4. Coding Standards

### 4.1 PHP & Laravel

- Follow **PSR-12** coding standards at all times.
- All database queries MUST use Eloquent or Query Builder – **no raw SQL strings** unless explicitly documented.
- All inputs MUST be validated using **Form Request classes** or explicit validation rules.
- Use **dependency injection** and the Laravel service container.

### 4.2 Models

- All models that support deletion MUST use `SoftDeletes`.
- Always define `$fillable` or `$guarded` explicitly – never leave models with unshielded mass-assignment.
- Define relationships explicitly with proper return types (`BelongsTo`, `HasMany`, etc.).

### 4.3 Controllers

- Keep controllers thin – business logic belongs in service classes or actions.
- API controllers live in `App\Http\Controllers\Api\`.
- Blade web controllers live in `App\Http\Controllers\Web\` or specialized namespaces (e.g. `App\Http\Controllers\Installer\`).
- Always return structured, consistent JSON responses for API endpoints.

### 4.4 Routes

- All API routes MUST live in `routes/api.php` under the `/v1` prefix group.
- All Blade routes MUST live in `routes/web.php`.
- Use named routes consistently: `route('installer.welcome')`, `route('dashboard')`.

### 4.5 Migrations

- Migration file names MUST follow the pattern: `YYYY_MM_DD_HHMMSS_create_table_name_table.php`.
- Every migration MUST have a complete, reversible `down()` method.
- Always add `$table->softDeletes()` to auditable tables (products, suppliers, stock movements).

### 4.6 Security Rules

- Never commit secrets (passwords, tokens, API keys) to any file tracked by Git.
- `.env` is in `.gitignore` – never remove it.
- Escape all Blade output with `{{ }}`.
- Protect web forms with `@csrf` tokens.

---

## 5. Changelog & Git Standards

### 5.1 CHANGELOG.md Conventions

- Every session / change MUST update `CHANGELOG.md` under `## [Unreleased]`.
- Categories to use:
  - `### Added` for new features, files, routes, or documentation.
  - `### Changed` for updates to existing functionality or configuration.
  - `### Fixed` for bug fixes and patches.
  - `### Removed` for deprecated or deleted code/services.
  - `### Security` for vulnerability fixes or permission updates.

### 5.2 Commit Message Format

- Format: `<type>(<scope>): <short summary>`
- Types: `feat`, `fix`, `refactor`, `docs`, `test`, `chore`, `style`
- Examples:
  - `feat(installer): add 6-step web installation wizard`
  - `docs(rules): add mandatory changelog and git commit rules`
  - `chore(deps): clean up composer setup script for pure Laravel`

---

## 6. Hosting & Deployment Rules

### 6.1 Whogohost VPS (Primary – Recommended)

- Point document root to `/var/www/hysam/public`.
- Configure Nginx with standard Laravel rewrite rules (`try_files $uri $uri/ /index.php?$query_string;`).
- Use Supervisor to manage `php artisan queue:work`.
- Set permissions: `chown -R www-data:www-data storage bootstrap/cache`.

### 6.2 Whogohost Shared Hosting (Fallback)

- Switch to file-based drivers:
  ```dotenv
  CACHE_STORE=file
  QUEUE_CONNECTION=sync
  SESSION_DRIVER=file
  APP_ENV=production
  APP_DEBUG=false
  ```
- Upload codebase and use Web Installer wizard at `/install` for initial setup.

---

## 7. What Is Permanently Off-Limits

| Prohibited Item | Reason |
|----------------|--------|
| Uncommitted changes / Skipping CHANGELOG | Violates tracking & governance rules |
| Node.js / npm packages | Pure PHP/Laravel stack |
| React / Vue / Angular | Blade templates only |
| Vite / Webpack | No front-end build step |
| Gemini API / Any external AI API | Removed – offline-only system |
| Raw SQL strings without comment | Security / maintainability |
| Open mass-assignment on models | Security |
| `APP_DEBUG=true` in production | Security |
| Secrets committed to Git | Security |

---

## 8. Violation Handling

If an AI agent discovers it has violated any rule above:

1. **STOP immediately** – do not continue or mask the issue.
2. **Report the violation** clearly to the user.
3. **Propose a rollback plan** and resolve the issue.
4. **Log the fix in CHANGELOG.md** and commit.

---

*Maintained for Hysam Ventures. Remote: https://github.com/hotvictorious-rgb/hysam.git*
