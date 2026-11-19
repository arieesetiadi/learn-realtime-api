# Project Guidelines

## Context
- Laravel 13, PHP 8.4, Pest 5, Larastan, and Laravel Boost.
- Follow existing conventions and architecture.
- Prefer simple, idiomatic Laravel solutions. Avoid unnecessary abstractions and dependencies.

## Architecture
- Follow `Controller → Service → Model`.
  - Controllers handle HTTP only: validation, authorization, resources, status codes.
  - Services own use cases, database writes, transactions, and domain logging.
  - Models own relationships, scopes, casts, and small domain helpers.
- Services live in `app/Services`, are `final`, constructor-injected, and use `declare(strict_types=1)`.
- Services must not use `Request`, `Auth`, `Gate`, or HTTP response classes.
- Authorization stays in controllers/policies. Services receive explicit IDs or models.
- No repository pattern. No interfaces for internal services. No DTO layer for internal calls.
- Keep one service layer; split a service only when it has clearly separate responsibilities.
- Use events/jobs for side effects that should not fail the request.

## Exceptions
- Do not add blanket `try/catch` in controllers or services.
- Catch only when you can recover, translate, or add meaningful context.
- Catch specific exceptions first; use `Throwable` only at a true boundary.
- Never swallow exceptions silently or log-and-rethrow the same exception.
- Use `DB::transaction()` for multi-write operations; do not catch database errors just to wrap them.
- Expected domain failures use `ValidationException` or a purpose-built exception rendered by the global handler.
- Unexpected exceptions bubble to `bootstrap/app.php`, where `ApiErrorResponse` maps them once.
- API errors include `message`, `code`, `request_id`, and `errors` for validation failures.
- Never expose exception messages or traces for `500` responses.
- Broadcasts are best-effort: a failed broadcast is logged as a warning and does not fail a saved message.
- Recover expected race conditions (for example unique-constraint violations) by re-reading the winner.

## Logging
- Default log channel is `app`: daily, JSON-formatted, structured context.
- Every request receives an `X-Request-Id`; logs include it through `RequestContextProcessor`.
- Log events, not prose: `Log::info('message.sent', [...])`.
- Log mutations at `info`, reads at `debug`, recoverable failures at `warning`.
- Business logs belong in services, not controllers.
- Never log passwords, tokens, raw message bodies, or full email addresses.
- Do not manually log `error` for exceptions handled by the global handler.
- Slow queries are logged at `debug` in local only.

## API
- Version routes under `/api/v1`.
- Reuse `FormRequest` validation, API Resources, and policies.
- Keep the existing error contract; add new codes deliberately.
- Rate limits are named and defined in `AppServiceProvider`: `api`, `login`, `register`, `conversations`, `messages`.
- Add `X-Request-Id` to every response, including errors.
- Keep token expiry (`SANCTUM_EXPIRATION`), CORS, and security headers configured.

## Security
- Never trust client input; validate through Form Requests.
- Authorization must be tested for every endpoint, including non-participants.
- Keep `APP_DEBUG=false` in production; never leak stack traces.
- Use the configured CORS allowlist and trusted proxies; do not use `*` blindly.
- Use strong password rules and Sanctum token expiration.
- Do not add secrets to the repository. Use environment variables.

## Testing
- Pest is the test framework. Feature tests are the default; unit tests for pure logic.
- Use `RefreshDatabase` for database-backed feature tests.
- `tests/bootstrap.php` forces test-safe env (SQLite in-memory, array cache, sync queue).
  - CI opts into Postgres with `TEST_DB_CONNECTION=pgsql`.
- Follow existing Pest naming and assertion style.
- Add feature tests for new behavior and regression tests for fixed bugs.
- Architecture tests live in `tests/Unit/ArchTest.php`; keep the service and controller boundaries enforced.
- Run the narrowest relevant test first, then the full suite when risk justifies it.

## Quality
- PHPStan via Larastan at level 6: `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`.
- Pint uses the default `laravel` preset; no custom rules unless explicitly agreed.
- Run `vendor/bin/pint --dirty` after modifying PHP files.
- CI runs Pint (`--test`), PHPStan, the test suite, and `composer audit --locked`.
- Keep new files under strict types and use explicit return types.
- Prefer framework features over custom helpers.

## Development Rules
- Check existing implementations before creating new ones.
- Use Artisan generators for Laravel classes when appropriate.
- Make minimal, focused changes. Do not modify unrelated code.
- Search before reading; inspect only relevant files.
- Use Laravel Boost tools and relevant skills when applicable.
- Ask before destructive operations or adding dependencies.
- Never sacrifice correctness or security for token savings.
- Summarize only what changed, verification results, and unresolved issues.

## Commands
```bash
docker compose exec app php artisan test
docker compose exec app vendor/bin/phpstan analyse --memory-limit=1G --no-progress
docker compose exec app vendor/bin/pint --dirty
docker compose exec app composer audit --locked
```
