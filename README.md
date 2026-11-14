# Laravel Blog — Refactoring Project

A phased refactoring of an educational Laravel blog application, with a focus on **maintainability, separation of concerns, authorization, data integrity, and automated testing**.

The goal is to improve the existing codebase incrementally, selecting solutions based on actual requirements rather than adding unnecessary complexity.

**Current stable milestone:** Phase 06 — Category Management.  
**Next milestone:** Phase 07 — File Manager (in development; not included in this release).

## Key improvements

### Authentication & authorization

- User registration, login, logout, and password-reset flows.
- Role-based access to dashboard features (`admin`, `author`, and `subscriber`).
- Article-level authorization through Laravel policies.
- Social login integration through Laravel Socialite.

### Article lifecycle

- Article creation, editing, and management with role-aware access.
- Submission for review, administrator approval, return for correction, and withdrawal.
- Soft deletion, trash management, restoration, and administrator-only permanent deletion.
- Optimistic locking to help detect conflicting article modifications.
- Queued notification and thumbnail-cleanup jobs.

### Category management

- Administrator-only category CRUD operations.
- Form Request validation and slug uniqueness safeguards.
- Category slug history and database-integrity checks.

### Code organization & reliability

- Eloquent relationships and database constraints.
- Form Requests, policies, and dedicated services for distinct responsibilities.
- Database transactions for operations requiring consistent state changes.
- Unit and Feature tests for application behavior and regression prevention.

> **Scope note:** Some original educational features and legacy views remain in the repository. In particular, the existing File Manager code is not the completed Phase 07 implementation.

## Tech stack

| Area | Technologies |
| --- | --- |
| Backend | PHP 8.2+, Laravel 12 (12.69.1 in the Phase 06 lockfile) |
| Database | SQLite (default local configuration) |
| Views & frontend tooling | Blade, Vite 5, Tailwind CSS 3 (declared dependency) |
| Authentication integration | Laravel Socialite |
| Image handling | Intervention Image |
| Automated testing | PHPUnit 11 |
| Code formatting | Laravel Pint |

## Installation

**Prerequisites:** PHP 8.2+, Composer, Node.js, npm, and the required PHP extensions (including SQLite support for the default database configuration).

1. Clone the repository and enter the project directory:

   ```bash
   git clone <repository-url>
   cd laravel-blog-refactoring
   ```

2. Install PHP and frontend dependencies:

   ```bash
   composer install
   npm install
   ```

3. Create a local environment file and application key:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   On Windows PowerShell, `Copy-Item .env.example .env` is an alternative to `cp`.

4. Create the SQLite database file if it does not already exist, then run migrations:

   ```bash
   php artisan migrate
   ```

   The expected SQLite file is `database/database.sqlite`. On PowerShell, create it if needed with `New-Item database/database.sqlite -ItemType File`.

5. Build frontend assets and start the application:

   ```bash
   npm run build
   php artisan serve
   ```

For local frontend development, use `npm run dev` in a separate terminal instead of building assets for each change.

**Configuration notes:** The provided `.env.example` uses SQLite, database-backed queues/sessions, and the `log` mail driver. To process queued jobs, run `php artisan queue:work` separately. Social login requires valid OAuth credentials for the chosen provider.

## Testing

The project includes automated **Unit** and **Feature** tests covering:

- Authentication, role middleware, and article authorization.
- Article lifecycle actions, validation, notifications, and background jobs.
- Category operations, slug handling, and access control.
- Eloquent relationships, transaction safeguards, and selected UI behavior.

**The full test suite passed before Phase 06 was finalized and committed.**

Run the tests locally with:

```bash
php artisan test
```

## Development progress

| Phase | Focus | Status |
| --- | --- | --- |
| 01 | Project baseline | Completed |
| 02 | Codebase cleanup | Completed |
| 03 | Database & Eloquent relationships | Completed |
| 04 | Authentication & authorization | Completed |
| 05 | Article management & review workflow | Completed |
| 06 | Category management & safeguards | Completed |
| 07 | File Manager refactoring | In progress — not in this release |

This README is updated as new phases are completed and merged into the stable codebase.

## Project scope

This is an ongoing refactoring project built from an educational application. It demonstrates incremental improvements to existing software; it is **not presented as a production-ready application**.
