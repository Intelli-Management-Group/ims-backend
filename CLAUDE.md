# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Development
composer run dev       # Start all services (web, queue, logs, Vite) concurrently
composer run setup     # Full setup: install deps, generate keys, migrate, seed

# Testing
composer run test      # Run full Pest test suite
php artisan test --filter=TestClassName  # Run a single test class

# Code quality
./vendor/bin/pint      # Laravel Pint linter/formatter

# Database
php artisan migrate --seed   # Run migrations and seed
php artisan jwt:secret       # Regenerate JWT secret

# API docs
php artisan scribe:generate  # Generate API documentation
```

Default admin credentials (seeded): `admin@example.com` / `password`

## Architecture

**Laravel 12 REST API** versioned at `/api/v1/`. The app is API-first with JWT authentication; Blade/Vite exist but the frontend is minimal.

### Request lifecycle

```
Route (routes/api/v1/*.php)
  → FormRequest (validation)
  → Controller (app/Http/Controllers/Api/V1/)
  → Policy (authorization, registered in AppServiceProvider)
  → Model / Service
  → Resource (response transformation)
```

### Key layers

- **Controllers** (`app/Http/Controllers/Api/V1/`) — thin; delegate to models or IdentityService
- **Form Requests** (`app/Http/Requests/`) — all validation lives here, not in controllers
- **Resources** (`app/Http/Resources/`) — API response shaping; always use these, never return raw models
- **Policies** (`app/Policies/`) — all authorization; registered in `AppServiceProvider`
- **IdentityService** (`app/Services/Identity/`) — interface-backed service for user/role/department/team reads; bound in `AppServiceProvider`

### Data model relationships

```
User ─── belongsTo ──→ Role
User ─── belongsToMany ──→ Department (pivot)
User ─── belongsToMany ──→ Team (pivot)

FormTemplate ─── hasMany ──→ FormSubmission
FormSubmission ─── hasMany ──→ FormSubmissionVersion  (audit trail)
```

`FormTemplate` stores `json_schema` and `ui_schema` as JSON columns. `FormSubmissionVersion` records every change to a submission, including `form_name` at the time of submission.

### Authentication

JWT via `php-open-source-saver/jwt-auth`. The `auth:api` middleware guard is defined in `config/auth.php`. `User` implements `JWTSubject`. Token refresh and invalidation are handled in `AuthController`.

### Testing

Pest v3 with an in-memory SQLite database (configured in `phpunit.xml`) — no real DB required for tests. Factories exist for all models. Feature tests hit real routes through the full middleware stack.

## Tech stack

| Layer | Package |
|---|---|
| Framework | Laravel 12, PHP 8.2+ |
| Auth | jwt-auth v2.8, Laravel Fortify |
| ORM | Eloquent (MySQL in production, SQLite in tests) |
| Queue/Cache | Database-backed |
| Frontend build | Vite 7, Tailwind CSS v4 |
| Testing | Pest v3 + pest-plugin-laravel |
| Linting | Laravel Pint |
| API docs | Scribe v5 |
| Local dev | Laravel Sail |

---

## Authorization model

All authorization is binary: **admin vs. everyone else**. Every policy resolves to `$user->isAdmin()` or a simple active-check. `isAdmin()` checks `$user->role->name === 'admin'`.

Consequences for development:
- Adding new roles (e.g. `manager`) requires updating each relevant policy — there is no permission table or gate abstraction. Touch the policies explicitly.
- All routes require `auth:api` (JWT). There are no public read endpoints.
- Policies are registered in `AppServiceProvider::boot()` via `Gate::policy()`. **`FormSubmissionPolicy` is the only policy that exists but is NOT registered there** — authorization currently passes silently for submissions. Register it there if you add any meaningful authorization to it.

## Form submission invariants

These must not be broken:

1. **Never update a `FormSubmissionVersion` in place.** Every change creates a new version row. The audit trail is the point.
2. **`current_version_id` on `FormSubmission` always points to the latest version.** Update it atomically in the same DB transaction when creating a new version.
3. **Optimistic locking via `version_number`.** The update endpoint returns `409 Conflict` if the submitted `version_number` doesn't match the current one. Frontend must handle this. The controller also uses pessimistic `lockForUpdate()` during the transaction — both locks work together.

## Adding a new resource (checklist)

Follow this order to match existing conventions:

```
1. php artisan make:model ModelName -mfs        # model + migration + factory + seeder
2. php artisan make:policy ModelNamePolicy      # add to AppServiceProvider::boot() Gate::policy()
3. php artisan make:request Form/StoreModelNameRequest
4. php artisan make:request Form/UpdateModelNameRequest
5. php artisan make:controller Api/V1/Domain/ModelNameController --api
6. php artisan make:resource ModelNameResource  # place in Resources/Domain/
7. Add route file routes/api/v1/domain.php      # include it from routes/api.php
8. php artisan make:test Feature/Api/V1/Domain/ModelNameTest --pest
```

Controllers should call `$this->authorizeResource(ModelName::class, 'model_name')` in the constructor for standard CRUD, matching the pattern in `UserController`.

## Testing conventions

- Tests use an in-memory **SQLite** database — no seeder runs, factories create all state.
- Authenticate in tests with `actingAs($user, 'api')` — never manipulate JWT tokens directly.
- Use `UserFactory::new()->create()` for a plain user. Check factories for existing states before defining inline attributes.
- Feature tests hit real routes through full middleware. Assert on JSON structure using `assertJsonStructure` or `assertJsonPath`.
- Run only the file you're changing: `php artisan test --compact --filter=ModelNameTest`.

## Known gaps (be aware, don't paper over)

- **`FormSubmissionPolicy` is unregistered** (see Authorization section). Registering it is the right fix.
- **Roles are names, not types.** `isAdmin()` does a string comparison on `role->name`. If a role is renamed in the DB, auth breaks. Consider this fragile during any role management work.
- **No soft deletes anywhere.** All models are hard-deleted if deletion were ever added. No `deleted_at` column exists. Factor this in before adding delete routes.
- **JWT claims embed department/team IDs.** Tokens are not automatically invalidated when a user's departments/teams change. Users must refresh their token (or log out/in) to reflect membership changes in their claims. The blacklist is enabled, so logout properly invalidates.
- **No enforcement that a user's teams belong to their departments.** The DB allows any team–user assignment. If that constraint matters, add it at the service or request validation layer.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5.3
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v3
- phpunit/phpunit (PHPUNIT) - v11
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `pest-testing` — Tests applications using the Pest 3 PHP framework. Activates when writing tests, creating unit or feature tests, adding assertions, testing Livewire components, architecture testing, debugging test failures, working with datasets or mocking; or when the user mentions test, spec, TDD, expects, assertion, coverage, or needs to verify functionality works.
- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.
- `fortify-development` — Laravel Fortify headless authentication backend development. Activate when implementing authentication features including login, registration, password reset, email verification, two-factor authentication (2FA/TOTP), profile updates, headless auth, authentication scaffolding, or auth guards in Laravel applications.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan Commands

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`, `php artisan tinker --execute "..."`).
- Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Debugging

- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.
- To execute PHP code for debugging, run `php artisan tinker --execute "your code here"` directly.
- To read configuration values, read the config files directly or run `php artisan config:show [key]`.
- To inspect routes, run `php artisan route:list` directly.
- To check environment variables, read the `.env` file directly.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.
- CRITICAL: ALWAYS use `search-docs` tool for version-specific Pest documentation and updated code examples.
- IMPORTANT: Activate `pest-testing` every time you're working with a Pest or testing-related task.

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.

=== laravel/fortify rules ===

# Laravel Fortify

- Fortify is a headless authentication backend that provides authentication routes and controllers for Laravel applications.
- IMPORTANT: Always use the `search-docs` tool for detailed Laravel Fortify patterns and documentation.
- IMPORTANT: Activate `developing-with-fortify` skill when working with Fortify authentication features.

</laravel-boost-guidelines>
