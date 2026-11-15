# AGENTS.md

# Project Development Guidelines

This document defines the mandatory development standards, architecture conventions, environment configuration, and execution rules for AI agents working on this Laravel application.

All agents MUST follow these guidelines before inspecting, modifying, testing, or executing commands within the project.

---

# 1. Project Environment

## 1.1 Application Stack

This project uses the following technology stack:

| Component | Technology | Configuration |
|---|---|---|
| Backend | Laravel | PHP 8.3 |
| Admin Panel | Filament | Version 5.x |
| Database | MySQL | 8.0 |
| Web Server | Nginx | Alpine |
| PHP Runtime | PHP-FPM | Docker container |
| Frontend Runtime | Node.js | 22 Alpine |
| Frontend Bundler | Vite | Project-defined version |
| Package Manager | NPM | Project-defined version |
| Container Runtime | Docker | Docker Compose |
| Development Environment | Docker-based | No direct host execution |

## 1.2 Mandatory Environment Rules

This application runs entirely inside Docker containers.

AI agents MUST execute commands through the appropriate Docker service.

**NEVER execute the following commands directly on the host machine:**

- `php`
- `composer`
- `artisan`
- `npm`
- `npx`
- `node`
- `pest`
- `pint`

Instead, execute them through their designated containers.

The host machine is only used for Docker orchestration and project file management.

## 1.3 Docker Services

The project uses the following Docker Compose services:

| Service | Container Name | Purpose |
|---|---|---|
| app | erka_app | Laravel, PHP 8.3, Composer, Artisan |
| webserver | erka_webserver | Nginx web server |
| db | erka_db | MySQL 8.0 database |
| node | erka_node | Node.js, NPM, Vite |

### Container Configuration

**Application container**

- Service: `app`
- Container: `erka_app`
- Working directory: `/var/www/html`
- PHP runtime: PHP 8.3
- Source directory: `./:/var/www/html`

**Frontend container**

- Service: `node`
- Container: `erka_node`
- Working directory: `/var/www/html`
- Node.js runtime: Node.js 22 Alpine
- Package manager: NPM

**Database container**

- Service: `db`
- Container: `erka_db`
- Database engine: MySQL 8.0
- Database name: `erka`
- Database user: `erka`

**Web server container**

- Service: `webserver`
- Container: `erka_webserver`
- Image: `nginx:alpine`
- Application root: `/var/www/html`

---

# 2. Docker Command Execution Rules

This section is mandatory for all AI agents.

Before executing any terminal command, determine which Docker service is responsible for that operation.

## 2.1 Docker Orchestration

Docker Compose commands MUST be executed from the project root on the host.

Preferred commands:

```bash
make up
make stop
make restart
```

Equivalent commands:

```bash
docker compose up -d
docker compose stop
docker compose restart
docker compose ps
```

Do not use alternative container configurations or create additional services without explicit approval.

## 2.2 PHP and Laravel Commands

All PHP-related commands MUST run inside the `app` container.

Use:

```bash
docker compose exec app php artisan [command]
```

Examples:

```bash
docker compose exec app php artisan about

docker compose exec app php artisan route:list

docker compose exec app php artisan migrate:status

docker compose exec app php artisan config:clear

docker compose exec app php artisan cache:clear

docker compose exec app php artisan optimize:clear
```

For Artisan commands that support non-interactive execution:

```bash
docker compose exec app php artisan make:model Example --no-interaction
```

### Composer Commands

Always execute Composer through the application container.

```bash
docker compose exec app composer install

docker compose exec app composer update

docker compose exec app composer show --direct

docker compose exec app composer show filament/filament
```

Do not execute Composer directly on the host.

### PHP Tinker

Execute Tinker through the application container:

```bash
docker compose exec app php artisan tinker --execute 'Your::code();'
```

Always use single quotes around the Tinker command to prevent shell expansion.

### Laravel Pint

Run Pint through the application container:

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
```

If PHP files have been modified, Pint MUST be executed before finalizing the changes.

## 2.3 Frontend and Node.js Commands

All frontend-related commands MUST run inside the `node` container.

Examples:

```bash
docker compose exec node node --version

docker compose exec node npm --version

docker compose exec node npm install

docker compose exec node npm run dev

docker compose exec node npm run build

docker compose exec node npm run lint
```

For package inspection:

```bash
docker compose exec node cat package.json
```

For running NPM scripts, always inspect `package.json` first to confirm the available scripts.

Never install Node.js or NPM dependencies directly on the host.

Never create another Node.js runtime or Docker service without approval.

## 2.4 Database Commands

MySQL is available through the `db` service.

To inspect the database container:

```bash
docker compose exec db mysql -u erka -p erka
```

To execute a database command:

```bash
docker compose exec db mysql -u erka -p -e "SHOW DATABASES;"
```

For database inspection and read-only application queries, prefer Laravel Boost database tools when available.

Do not use raw SQL through Tinker when the Laravel Boost `database-query` tool is available.

Before creating migrations or changing models, inspect the existing database structure using Laravel Boost `database-schema`.

Never delete, reset, or overwrite production data without explicit approval.

## 2.5 Container Shell Access

Use the existing Makefile targets:

```bash
make bash-app
make bash-node
```

Equivalent commands:

```bash
docker compose exec app bash

docker compose exec node sh
```

Note: The Node.js Alpine container uses `sh`, not necessarily Bash.

## 2.6 Command Routing Reference

| Operation | Required Execution |
|---|---|
| Laravel Artisan | `docker compose exec app php artisan ...` |
| Composer | `docker compose exec app composer ...` |
| PHP | `docker compose exec app php ...` |
| Laravel Pint | `docker compose exec app vendor/bin/pint ...` |
| Pest | `docker compose exec app vendor/bin/pest ...` |
| NPM | `docker compose exec node npm ...` |
| Node.js | `docker compose exec node node ...` |
| Vite | `docker compose exec node npm run ...` |
| MySQL CLI | `docker compose exec db mysql ...` |
| Docker lifecycle | `docker compose ...` or `make ...` |

**Critical rule:** If an agent cannot access the required container, it MUST report the problem instead of silently switching to host execution.

Do not use `docker compose run` to create temporary replacement containers when the existing service can be used through `docker compose exec`.

---

# 3. Laravel Boost Guidelines

## 3.1 Foundational Context

This is a Laravel application running on PHP 8.3.

Always use APIs that match the installed major version of each package.

Before relying on a package API, confirm its installed version.

PHP packages:

```bash
docker compose exec app composer show --direct
```

Specific PHP package:

```bash
docker compose exec app composer show vendor/package
```

JavaScript packages:

```bash
docker compose exec node cat package.json
```

Never assume a package version based on online examples.

## 3.2 Skills Activation

This project may contain domain-specific skills in `**/skills/**`.

Agents MUST activate the relevant skill whenever working in that domain.

Do not wait until implementation problems occur before activating the relevant skill.

When working with Laravel, Filament, Pest, deployment, database architecture, or frontend development, inspect available relevant skills first.

## 3.3 Existing Conventions

- Follow existing code conventions.
- Inspect sibling files before creating or editing files.
- Reuse existing components whenever possible.
- Use descriptive variable and method names.
- Follow existing naming conventions.
- Avoid unnecessary abstractions.
- Avoid duplicate implementations.
- Do not introduce dependencies without approval.

## 3.4 Verification

Do not create temporary verification scripts or use Tinker when existing tests already cover the functionality.

Prioritize unit and feature tests.

---

# 4. Filament 5.x Development Guidelines

## 4.1 Official Documentation

This project uses Filament as its low-code admin panel framework.

The official documentation is:

https://filamentphp.com/docs/5.x/getting-started

Agents MUST use Filament 5.x documentation when implementing or modifying Filament features.

Do not use Filament 3.x or 4.x examples unless explicitly verified to be compatible with the installed Filament 5.x version.

Before implementing Filament functionality:

1. Inspect the installed Filament version.
2. Read the official documentation relevant to the requested feature.
3. Inspect existing Filament resources, pages, widgets, forms, and tables.
4. Follow existing project conventions.
5. Reuse existing Filament components and patterns.
6. Implement the feature using APIs supported by the installed version.

Verify the installed version:

```bash
docker compose exec app composer show filament/filament
```

## 4.2 Filament Low-Code Architecture

Filament is the primary framework for building administrative interfaces.

Prefer native Filament functionality instead of creating custom implementations unnecessarily.

Use the appropriate Filament components:

- Resources
- Pages
- Widgets
- Forms
- Tables
- Infolists
- Actions
- Notifications
- Navigation
- Relation Managers
- Clusters
- Custom Fields
- Custom Widgets

Before building a custom UI, check whether Filament already provides the required functionality.

## 4.3 Filament Resources

When creating or modifying resources:

- Follow the existing Resource directory structure.
- Follow existing naming conventions.
- Keep forms modular and maintainable.
- Use native Filament form components.
- Use native table columns and filters.
- Implement appropriate validation.
- Apply authorization policies.
- Avoid duplicating business logic in Resource classes.
- Keep business logic in the appropriate application layer.

Do not place excessive business logic inside form definitions, table definitions, or page classes.

## 4.4 Filament Forms

Use native Filament form components whenever possible.

Examples include:

- TextInput
- Textarea
- Select
- Checkbox
- Toggle
- DatePicker
- DateTimePicker
- FileUpload
- Repeater
- RichEditor
- Section
- Grid
- Tabs
- Wizard

Use the installed Filament 5.x API.

Forms MUST:

- Have appropriate validation.
- Follow database constraints.
- Handle nullable fields correctly.
- Use appropriate relationship handling.
- Prevent unauthorized data modification.
- Provide meaningful feedback.

## 4.5 Filament Tables

Prefer native Filament table functionality.

Implement:

- Searchable columns.
- Sortable columns.
- Appropriate filters.
- Bulk actions where appropriate.
- Record actions.
- Pagination.
- Appropriate relationship loading.
- Authorization for individual actions.

Avoid unnecessary database queries and N+1 problems.

## 4.6 Filament Authorization and RBAC

Authorization is mandatory.

Never assume that hiding a navigation item is sufficient authorization.

Apply authorization at the appropriate levels:

- Navigation visibility.
- Resource access.
- Page access.
- Record access.
- Form actions.
- Table actions.
- Bulk actions.
- Backend policies and permissions.

Use existing authentication and authorization architecture.

Do not introduce another RBAC implementation without approval.
## 4.9 Filament Shield — Role-Based Access Control (RBAC)

### Official Plugin

This project uses **Filament Shield** by BezhanSalleh as the primary Role-Based Access Control (RBAC) management plugin.

Official documentation:

https://filamentphp.com/plugins/bezhansalleh-shield

Filament Shield is responsible for managing:

- Roles.
- Permissions.
- Role-permission assignments.
- Resource permissions.
- Page permissions.
- Widget permissions.
- Access control for Filament administrative features.

All RBAC implementations MUST follow the installed Filament Shield version and its compatibility with Filament 5.x.

### 4.9.1 Mandatory Version Verification

Before implementing or modifying RBAC functionality, inspect the installed package:

```bash
docker compose exec app composer show bezhansalleh/filament-shield
```

Inspect Filament version:

```bash
docker compose exec app composer show filament/filament
```

Inspect available Shield Artisan commands:

```bash
docker compose exec app php artisan list | grep shield
```

Always consult the official documentation before using Shield-specific APIs, configuration options, commands, or permission-generation features.

Never assume that examples from older Filament Shield versions are compatible with the installed version.

### 4.9.2 RBAC Architecture

Filament Shield MUST be the primary permission-management mechanism for Filament administrative resources.

Use the following authorization hierarchy:

1. User authentication.
2. Role assignment.
3. Permission assignment.
4. Resource authorization.
5. Page authorization.
6. Widget authorization.
7. Record-level authorization.
8. Action-level authorization.

Authorization MUST be enforced on the backend.

Hiding navigation items or buttons is not sufficient security.

### 4.9.3 Role Management

Use Filament Shield to manage roles.

Agents MUST:

- Reuse existing roles.
- Avoid creating duplicate roles.
- Follow existing role naming conventions.
- Assign permissions according to responsibilities.
- Avoid assigning excessive permissions.
- Apply least-privilege principles.
- Protect sensitive role-management functionality.

Do not create a separate custom role-management implementation when Filament Shield already provides the required functionality.

### 4.9.4 Permission Management

Use Filament Shield's permission-generation and management mechanisms according to its installed version.

Permissions MUST be assigned explicitly.

Follow these principles:

- Grant only necessary permissions.
- Avoid granting all permissions to ordinary users.
- Avoid unrestricted wildcard permissions.
- Separate view, create, update, delete, and other supported operations where appropriate.
- Respect custom business authorization rules.
- Ensure permissions are consistent across Resources, Pages, Widgets, and Actions.

When creating new Filament Resources, Pages, or Widgets, verify whether corresponding permissions need to be generated or synchronized.

Never assume a newly created resource is automatically protected without verification.

### 4.9.5 Resource Authorization

Every Filament Resource MUST have appropriate authorization.

Agents MUST:

- Verify view permissions.
- Verify create permissions.
- Verify edit permissions.
- Verify delete permissions.
- Verify bulk-delete permissions.
- Verify custom action permissions.
- Verify record-level access restrictions.

Use Shield's generated policies and permission mechanisms where applicable.

For business-specific authorization, implement the appropriate Laravel Policies, Gates, or additional authorization logic.

Do not bypass existing policies.

### 4.9.6 Super Administrator Protection

Super Administrator access is highly sensitive.

Agents MUST:

- Preserve the existing Super Administrator role and configuration.
- Avoid accidentally removing Super Administrator privileges.
- Restrict role and permission management to authorized administrators.
- Protect sensitive system configuration.
- Prevent unauthorized privilege escalation.
- Verify authorization before modifying Super Administrator behavior.

If the project uses Shield's Super Admin functionality, follow its documented configuration and authorization behavior.

Do not assume that a role named `super_admin` automatically has unrestricted access.

Do not change Super Admin configuration without understanding the existing implementation.

### 4.9.7 Default Deny Authorization

This project MUST follow a Default Deny authorization principle.

Rules:

- Unauthenticated users cannot access protected administrative functionality.
- Users without the required permissions cannot access protected features.
- Missing permission definitions must not result in unrestricted access.
- Direct URL access must be authorized.
- Livewire actions must respect authorization.
- Backend operations must enforce authorization independently of UI visibility.

Never implement permission checks exclusively in frontend components.

### 4.9.8 Navigation and UI Permissions

Filament navigation MUST reflect the user's permissions.

Requirements:

- Unauthorized Resources must not appear in navigation.
- Unauthorized Pages must not be accessible.
- Unauthorized Widgets must not expose protected information.
- Unauthorized Actions must not be executable.
- Navigation visibility must remain consistent with backend authorization.

Do not confuse navigation visibility with actual access control.

### 4.9.9 Permission Synchronization

When adding or modifying Resources, Pages, Widgets, or custom actions:

1. Inspect the existing Shield configuration.
2. Determine the required permissions.
3. Use the supported permission-generation mechanism.
4. Verify generated permission names.
5. Verify role assignments.
6. Verify authorization behavior.
7. Test access using users with different roles.

Do not blindly regenerate or synchronize permissions in production.

Never remove existing role assignments or permissions without understanding their impact and obtaining approval when necessary.

### 4.9.10 Database and Migration Safety

Filament Shield uses database structures for role and permission management.

Agents MUST:

- Inspect the existing Shield migrations.
- Inspect existing role and permission tables.
- Avoid duplicate migrations.
- Avoid manually modifying Shield-managed tables without justification.
- Preserve existing user-role relationships.
- Use the installed package's supported APIs.
- Avoid destructive permission synchronization.

Do not reset the RBAC database tables to solve permission issues.

### 4.9.11 RBAC Testing

Every RBAC-related implementation MUST include authorization tests where applicable.

Test scenarios should include:

- Super Administrator access.
- Authorized administrator access.
- User with limited permissions.
- User without permissions.
- Unauthenticated user.
- Direct URL access.
- Unauthorized create operations.
- Unauthorized update operations.
- Unauthorized delete operations.
- Unauthorized bulk actions.
- Custom Page and Widget access.
- Privilege escalation prevention.

Use Pest and existing project factories.

Execute tests through the Docker application container.

Example:

```bash
docker compose exec app php artisan test --compact
```

### 4.9.12 RBAC Development Restrictions

AI agents MUST NOT:

- Create a second RBAC system alongside Filament Shield.
- Hardcode role checks throughout application components.
- Use navigation hiding as the only authorization mechanism.
- Assign all permissions to every role.
- Bypass Laravel Policies.
- Modify Shield internal package files.
- Modify role or permission records destructively.
- Remove Super Administrator privileges without approval.
- Assume permission generation automatically grants authorization.
- Introduce another authorization package without approval.

### 4.9.13 RBAC Implementation Workflow

Whenever implementing an RBAC-related feature, follow this workflow:

1. Inspect the installed Filament Shield version.
2. Read the official Filament Shield documentation.
3. Inspect the existing Shield configuration.
4. Inspect existing roles and permissions.
5. Inspect relevant Resources, Pages, Widgets, and Policies.
6. Determine the minimum required permissions.
7. Implement authorization using existing Shield mechanisms.
8. Implement additional Laravel Policies when business rules require them.
9. Verify navigation visibility and backend enforcement.
10. Write and execute Pest authorization tests.
11. Verify that existing administrator access remains intact.

**Final RBAC Rule:**

Filament Shield is the primary RBAC management solution for this project. All new administrative functionality MUST integrate with the existing Shield authorization architecture rather than introducing an independent role and permission management system.

## 4.7 Filament UI Consistency

All admin interfaces MUST follow a consistent design system.

Requirements:

- Consistent spacing.
- Consistent component hierarchy.
- Consistent form layouts.
- Consistent table layouts.
- Consistent page headers.
- Responsive layouts.
- Clear empty states.
- Appropriate loading states.
- Consistent notification behavior.

Avoid unnecessary custom CSS when native Filament configuration can achieve the desired result.

## 4.8 Filament Performance

Performance is a primary consideration.

Agents MUST:

- Avoid N+1 queries.
- Use eager loading where appropriate.
- Avoid unnecessary relationship queries.
- Optimize table queries.
- Use pagination for large datasets.
- Avoid loading unnecessary records.
- Avoid expensive calculations during rendering.
- Use caching where appropriate.
- Avoid unnecessary Livewire re-renders.

Do not sacrifice application performance for visual customization.

---

# 5. PHP Guidelines

- Always use curly braces for control structures, including single-line bodies.
- Use PHP 8 constructor property promotion.
- Declare explicit return types.
- Use type hints for method parameters.
- Use TitleCase for Enum keys.
- Prefer PHPDoc blocks over inline comments.
- Only add inline comments for exceptionally complex logic.
- Use array shape definitions in PHPDoc where appropriate.

Example:

```php
public function __construct(
    public GitHub $github,
) {
}
```

Avoid empty zero-parameter constructors unless the constructor is private.

---

# 6. Laravel Core Guidelines

## 6.1 Laravel Conventions

Follow Laravel's recommended architecture and conventions.

Use:

- Eloquent Models.
- Form Requests where appropriate.
- Policies and Gates.
- Service classes when justified.
- Events and Listeners where appropriate.
- Jobs for background processing.
- Notifications for user communication.
- API Resources for API responses.

Avoid unnecessary abstractions.

## 6.2 Artisan

Create Laravel files using Artisan commands.

Examples:

```bash
docker compose exec app php artisan make:model Example --no-interaction

docker compose exec app php artisan make:controller ExampleController --no-interaction

docker compose exec app php artisan make:migration create_examples_table --no-interaction

docker compose exec app php artisan make:test ExampleTest --pest --no-interaction
```

Check available commands and parameters before execution:

```bash
docker compose exec app php artisan list

docker compose exec app php artisan make:model --help
```

## 6.3 Models

When creating new models:

- Create useful factories.
- Create seeders where appropriate.
- Follow existing relationship conventions.
- Define casts appropriately.
- Define fillable or guarded properties consistently.
- Avoid unnecessary model responsibilities.

Ask the user if additional model-related components are required.

## 6.4 APIs and Eloquent Resources

For APIs:

- Prefer Eloquent API Resources.
- Use API versioning when appropriate.
- Follow existing API conventions.
- Validate incoming requests.
- Apply authorization.
- Use consistent response structures.

If existing API routes do not use versioning, follow the existing application convention.

## 6.5 URL Generation

When generating internal URLs, prefer named routes and Laravel's `route()` function.

Use Laravel Boost `get-absolute-url` before sharing project URLs with the user.

---

# 7. Database Guidelines

- Inspect the existing database schema before modifying database structures.
- Use Laravel migrations for schema changes.
- Follow existing naming conventions.
- Define appropriate indexes.
- Use foreign keys where appropriate.
- Avoid unnecessary database columns.
- Avoid inefficient relationship queries.
- Consider transaction handling for multi-step operations.
- Preserve existing data.
- Never reset or truncate databases without approval.

Use Laravel Boost `database-schema` before writing migrations or models.

Use Laravel Boost `database-query` for read-only database inspection.

Do not assume database structures from model names alone.

---

# 8. Deployment Guidelines

Laravel can be deployed using Laravel Cloud:

https://cloud.laravel.com/

Activate the `deploying-to-cloud` skill whenever:

- Deploying to Laravel Cloud.
- Configuring Cloud environments.
- Managing Cloud resources.
- Using Cloud CLI.
- Troubleshooting Cloud deployments.

For other deployment environments, follow the existing infrastructure and deployment architecture.

Never modify production infrastructure without approval.

---

# 9. Frontend Bundling Guidelines

The project uses Node.js 22 and Vite inside the `node` container.

Before modifying frontend dependencies:

1. Inspect `package.json`.
2. Confirm the installed package versions.
3. Check existing components.
4. Follow the existing frontend conventions.

If frontend changes do not appear in the UI or Vite reports:

`Unable to locate file in Vite manifest`

Run the frontend build through Docker:

```bash
docker compose exec node npm run build
```

For development:

```bash
docker compose exec node npm run dev -- --host 0.0.0.0
```

If necessary, ask the user to run the existing Vite development service.

Never install additional frontend dependencies without approval.

---

# 10. Pest Testing Guidelines

This project uses Pest.

Before writing tests, read the `testing-best-practices` skill.

Create tests using:

```bash
docker compose exec app php artisan make:test ExampleTest --pest --no-interaction
```

Do not include the test suite directory in the test name.

Use:

`SomeFeatureTest`

Not:

`Feature/SomeFeatureTest`

## 10.1 Testing Conventions

- Prefer feature tests.
- Use unit tests when appropriate.
- Use existing factories.
- Check factory states before manually creating test data.
- Follow existing test structure.
- Isolate external dependencies.
- Do not delete existing tests without approval.

## 10.2 Running Tests

Run the narrowest test suite that covers the changes.

```bash
docker compose exec app php artisan test --compact
```

Run a specific test:

```bash
docker compose exec app php artisan test --compact tests/Feature/ExampleTest.php
```

Run a specific test method:

```bash
docker compose exec app php artisan test --compact --filter=test_example
```

Or execute Pest directly:

```bash
docker compose exec app vendor/bin/pest
```

After feature tests pass, ask the user to run the complete suite when broader verification is necessary.

---

# 11. Laravel Pint Code Formatter

If PHP files have been modified, Pint MUST be executed before finalizing changes.

Run:

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
```

Do not run:

```bash
vendor/bin/pint --test --format agent
```

Use Pint to automatically fix formatting issues.

---

# 12. Project Rules and AI Instructions

This project may contain committed area-grouped rules in:

`.ai/rules/`

Before entering plan mode or creating/editing files:

1. Check whether `.ai/rules/` exists.
2. Read `.ai/rules/index.md`.
3. Identify all rule files whose path patterns cover the files in scope.
4. Read every matching rule file.
5. Run keyword searches to identify additional relevant rules.

Example:

```bash
grep -rin 'keyword' .ai/rules
```

These instructions must be followed in addition to this document.

If `.ai/rules/` does not exist, continue without it.

---

# 13. Documentation Rules

Only create documentation files when explicitly requested by the user.

When documentation is requested:

- Follow the existing documentation structure.
- Avoid unnecessary duplicate documentation.
- Keep technical instructions consistent with the actual project configuration.
- Do not document commands that cannot run in the configured environment.

---

# 14. Security and Operational Safety

Agents MUST:

- Never expose credentials, passwords, API keys, or secrets.
- Never commit `.env` files.
- Never modify production credentials without approval.
- Never execute destructive database operations without approval.
- Never delete user data without approval.
- Never change Docker service definitions without approval.
- Never change package dependencies without approval.
- Never bypass authorization or validation.
- Never expose sensitive application data in logs or responses.

Do not assume development credentials are safe to reuse in production.

---

# 15. Mandatory AI Agent Workflow

Before implementing any feature or fixing a bug, follow this workflow.

## Step 1: Understand the Environment

Inspect:

- `docker-compose.yml` or `compose.yaml`.
- `Makefile`.
- `.env.example`.
- `composer.json`.
- `package.json`.

Determine the correct service for each command.

Do not assume that the host has PHP, Composer, Node.js, or NPM installed.

## Step 2: Inspect Project Rules

Read:

- `AGENTS.md`.
- `.ai/rules/index.md`, if present.
- All matching area-specific rules.
- Relevant domain-specific skills.

## Step 3: Inspect Existing Implementation

Before writing code:

- Inspect related files.
- Inspect sibling components.
- Inspect existing resources and pages.
- Inspect database schemas.
- Inspect existing routes.
- Inspect existing tests.

Reuse existing implementations whenever possible.

## Step 4: Check Package Versions and Documentation

Verify installed package versions through their designated containers.

For Laravel ecosystem changes, use Laravel Boost `search-docs` before implementation.

For Filament changes, use official Filament 5.x documentation:

https://filamentphp.com/docs/5.x/getting-started

Never rely on outdated examples without verification.

## Step 5: Plan the Implementation

Understand:

- Root cause.
- Affected components.
- Database impact.
- Authorization impact.
- Performance impact.
- Backward compatibility.

Avoid unrelated modifications.

## Step 6: Implement

Follow:

- Laravel conventions.
- Filament 5.x conventions.
- Existing project architecture.
- Docker execution rules.
- Existing UI patterns.
- Security requirements.

## Step 7: Verify

Execute relevant checks through Docker:

```bash
docker compose exec app php artisan test --compact
```

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
```

For frontend changes:

```bash
docker compose exec node npm run build
```

Use Laravel Boost `browser-logs` to inspect recent browser errors.

## Step 8: Report Results

At the end of each task, summarize:

1. What was changed.
2. Which files were modified.
3. What implementation decisions were made.
4. Which tests or verification commands were executed.
5. Whether verification passed or failed.
6. Any remaining issues or actions requiring user approval.

Never claim a test, build, migration, or command succeeded if it was not actually<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.3. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

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

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>
 executed successfully.

---

# 16. Final Mandatory Rules

The following rules override convenience or alternative execution preferences.

1. Docker is the only supported runtime environment for this project.
2. PHP and Laravel commands MUST execute through the `app` service.
3. Node.js and frontend commands MUST execute through the `node` service.
4. Database CLI operations MUST execute through the `db` service.
5. Docker lifecycle commands MUST follow the existing Compose configuration and Makefile.
6. Filament development MUST follow the installed Filament 5.x version.
7. Official Laravel and Filament documentation MUST be consulted when relevant.
8. Existing project architecture and coding conventions MUST be preserved.
9. Tests MUST be executed through the appropriate Docker container.
10. Destructive operations and dependency changes require explicit approval.

**If there is a conflict between generic AI coding practices and the actual project configuration, the actual project configuration and these project-specific instructions take precedence.**