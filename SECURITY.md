# Security Policy

## Supported versions

Security fixes are applied to the latest minor release line. Older minor versions may receive fixes for critical issues at the maintainers' discretion — when in doubt, please upgrade.

| Version       | Supported              |
| ------------- | ---------------------- |
| `3.6.x`       | ✅                     |
| `3.x` (older) | ⚠️ critical fixes only |
| `< 3.0`       | ❌ (please upgrade)    |

## Reporting a vulnerability

**Please do not open a public GitHub issue for security reports.**

Use [GitHub Security Advisories](https://github.com/offload-project/laravel-mandate/security/advisories/new) to report privately. This lets us discuss, fix, and coordinate disclosure before details become public.

When reporting, please include:

- A description of the issue and its potential impact.
- Steps to reproduce, or a minimal proof-of-concept.
- Affected package version(s), Laravel version, and PHP version.
- Any suggested fix or mitigation (optional).

## Response expectations

- **Acknowledgement:** within 5 business days.
- **Initial assessment:** within 10 business days.
- **Fix timeline:** depends on severity. Critical issues get prioritized; lower-severity issues may be batched into the next regular release.

We'll keep you updated on progress and credit you in the advisory unless you'd prefer to stay anonymous.

## Scope

Things in scope for this project:

- Vulnerabilities in any code published under `OffloadProject\Mandate\` (models, traits, services, middleware, Blade directives, facade, console commands).
- Authorization bypass — any scenario where a subject can satisfy a permission, role, or capability check they should not have (including wildcard, context, and feature-integration paths).
- Privilege escalation through code-first sync, the `mandate:sync` / `mandate:assign-*` commands, or the seeding/assignment config.
- Mass-assignment issues in the package-provided `Permission`, `Role`, or `Capability` models.
- Cache poisoning of the permission registrar — anything that lets a subject get cached as having permissions they were not granted.
- SQL injection, query-scope bypass, or guard-mismatch bypass in package-provided Eloquent code.
- Information disclosure via exception messages, events, or middleware redirects (e.g., leaking subject identifiers or context model data).
- Insecure defaults in the published config or migrations.

Things **not** in scope (please report upstream or with the relevant project):

- Vulnerabilities in Laravel itself or other Composer dependencies — please file with the respective project.
- Application-level misconfiguration in a consuming app (e.g., assigning over-broad wildcards in config, exposing the `mandate:*` commands publicly, custom Gate registrations that bypass Mandate).
- Issues caused by user-supplied implementations of the package's extension points (custom `FeatureAccessHandler`, custom Permission/Role/Capability models, overridden trait methods).
- Vulnerabilities in the host application's authentication, session, cache, or database layer.

## Disclosure

Once a fix is published, we will:

1. Publish a GitHub Security Advisory with details and credit.
2. Tag a patch release.
3. Update the changelog with a brief mention (without exploit details prior to the disclosure window).

Thanks for helping keep the project and its users safe.
