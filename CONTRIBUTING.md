# Contributing to Hysam Ventures

Thank you for your interest in contributing to **Hysam Ventures Inventory Management System**. Please read this guide carefully before submitting any changes.

> **Note:** All AI agents working on this project MUST also read [`docs/ai_agent_rules.md`](docs/ai_agent_rules.md) before making any change.

---

## Stack & Constraints

- **Framework:** Laravel 10, PHP 8.3
- **UI:** Blade templates only — **no Node.js, Vite, React, or Vue**
- **Database:** MySQL 8.0 / MariaDB
- **No external AI APIs** — Gemini has been permanently removed

---

## Development Setup

```bash
git clone <repo-url>
cd hysam
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

---

## Workflow

1. **Check out a feature branch** from `master`:
   ```bash
   git checkout -b feat/my-feature
   ```
2. **Run tests** before and after your change:
   ```bash
   php artisan test
   ```
3. **Follow PSR-12** — run the linter:
   ```bash
   vendor/bin/phpcs
   ```
4. **Write tests** — every new feature needs at least one Feature test.
5. **Commit with Conventional Commits** format:
   ```
   feat: add low-stock alert notification
   fix: correct stock transfer validation
   docs: update Whogohost deployment steps
   ```
6. **Submit a Pull Request** with a clear description of what changed and why.

---

## Rules Summary

| Rule | Requirement |
|------|------------|
| Tests | All tests must pass (`php artisan test`) |
| Coverage | ≥ 80 % |
| Linting | PSR-12, zero violations |
| Node.js | Not allowed |
| Gemini / AI API | Not allowed |
| Raw SQL | Not allowed without comment |
| Secrets in Git | Never |
| Direct push to master | Never |

---

*See [`docs/ai_agent_rules.md`](docs/ai_agent_rules.md) for the full engineering rulebook.*
