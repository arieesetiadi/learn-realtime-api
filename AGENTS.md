
# Project Guidelines

## Context
- Laravel 13, PHP 8.4, Pest, and Laravel Boost.
- Follow existing project conventions and architecture.
- Prefer simple, idiomatic Laravel solutions. Avoid unnecessary abstractions and dependencies.

## Token Efficiency
- Keep responses, plans, and summaries concise. Avoid repetition and unnecessary explanations.
- Search before reading. Inspect only relevant files and use targeted searches with limited output.
- Reuse context, documentation, and tool results. Avoid redundant reads, searches, and commands.
- Use Laravel Boost tools and relevant skills when applicable. Search version-specific documentation only when needed.
- For simple tasks, implement directly. Plan only for complex, ambiguous, or high-risk changes.
- Make minimal, focused changes. Do not modify unrelated code.
- Run only relevant tests and checks. Expand verification when failures or risks justify it.
- Summarize only what changed, verification results, and unresolved issues.

## Development Rules
- Check existing implementations before creating new ones.
- Use Artisan generators for Laravel classes when appropriate.
- Follow existing Pest test conventions.
- Run `vendor/bin/pint --dirty` after modifying PHP files.
- Ask before destructive operations or adding dependencies.
- Never sacrifice correctness or security for token savings.
