# ABAC Implementation Plan

## Current State

### Authorization model (binary, admin-or-not)

Every policy in the codebase resolves to one of two answers:

| Check | Used in |
|---|---|
| `$user->isAdmin()` → role.name === 'admin' | FormTemplatePolicy, UserPolicy, RolePolicy, DepartmentPolicy, TeamPolicy |
| `return true` | All FormSubmission policy methods except delete |

`FormSubmissionPolicy` exists but is **not registered** in `AppServiceProvider::boot()` — so its rules are currently silent. This is documented in `CLAUDE.md` as a known gap.

### Existing identity attributes (already in DB)

```
User
  └─ role_id            → roles.id        (BelongsTo)
  └─ department_user    pivot             (BelongsToMany → departments)
  └─ team_user          pivot             (BelongsToMany → teams)
  └─ is_active

Department
  └─ teams              (HasMany → teams)

Team
  └─ department_id      (BelongsTo → departments)
```

JWT custom claims already embed `role`, `departments`, and `teams` for every token — the identity surface is there, it's just not used for authorization decisions yet.

### What's missing

- No concept of *what a role/department/team is allowed to do on a specific resource*.
- No per-form permission records anywhere in the schema.
- No field-level visibility or editability rules.
- No admin API for managing permissions at runtime.

---

## Proposed Design

### Core idea

Add a `form_template_permissions` table that records: **subject** (role / department / team) + **action** (form-level verb) + **form template**. Policies query this table instead of delegating everything to `isAdmin()`.

A parallel `form_template_field_permissions` table adds field-scoped rules on top, keyed by a string field path matching keys in `json_schema`.

Admins always bypass both layers.

---

## Schema Changes

### Table: `form_template_permissions`

```
id
form_template_id   FK → form_templates.id  CASCADE DELETE
action             ENUM('view','create','edit')
permissible_type   STRING  ('App\Models\Role' | 'App\Models\Department' | 'App\Models\Team')
permissible_id     UNSIGNED BIGINT
created_at / updated_at

UNIQUE (form_template_id, action, permissible_type, permissible_id)
INDEX  (permissible_type, permissible_id)   -- for reverse lookup
```

Using Laravel's polymorphic convention keeps subject extension open (e.g. per-user overrides later) without schema changes.

### Table: `form_template_field_permissions`

```
id
form_template_id   FK → form_templates.id  CASCADE DELETE
field_key          STRING  -- dot-notation path matching a key in json_schema
action             ENUM('fill','edit')
permissible_type   STRING
permissible_id     UNSIGNED BIGINT
created_at / updated_at

UNIQUE (form_template_id, field_key, action, permissible_type, permissible_id)
```

`field_key` is a dot-path (`"name"`, `"address.street"`) that mirrors the structure of `json_schema.properties`. No FK — the coupling is intentionally soft so renaming a field doesn't cascade-delete permissions; instead an admin migration tool handles it.

---

## Code Changes

### 1. Models

**`FormTemplatePermission`**
- `$fillable`: `form_template_id`, `action`, `permissible_type`, `permissible_id`
- `casts`: `action` as a PHP `Enum` (`FormPermissionAction`)
- Polymorphic `permissible()` morph relation
- `template()` BelongsTo

**`FormTemplateFieldPermission`**
- Same structure + `field_key`

**`FormTemplate`** — add helper:
```php
public function permissions(): HasMany { … }
public function fieldPermissions(): HasMany { … }
```

**Enum `FormPermissionAction`**
```php
enum FormPermissionAction: string {
    case View   = 'view';
    case Create = 'create';
    case Edit   = 'edit';
}
```

**Enum `FormFieldPermissionAction`**
```php
enum FormFieldPermissionAction: string {
    case Fill = 'fill';
    case Edit = 'edit';
}
```

### 2. Authorization service

Extract the resolution logic into a dedicated service rather than putting it directly in policies (policies stay thin):

**`App\Services\Form\FormPermissionService`**

```php
class FormPermissionService
{
    public function userCanOnTemplate(User $user, FormPermissionAction $action, FormTemplate $template): bool
    {
        if ($user->isAdmin()) return true;

        return FormTemplatePermission::where('form_template_id', $template->id)
            ->where('action', $action)
            ->where(function ($q) use ($user) {
                $q->where(fn ($q) =>
                        $q->where('permissible_type', Role::class)
                          ->where('permissible_id', $user->role_id))
                  ->orWhere(fn ($q) =>
                        $q->where('permissible_type', Department::class)
                          ->whereIn('permissible_id', $user->departments->pluck('id')))
                  ->orWhere(fn ($q) =>
                        $q->where('permissible_type', Team::class)
                          ->whereIn('permissible_id', $user->teams->pluck('id')));
            })
            ->exists();
    }

    /**
     * Returns the set of field_keys the user may perform $action on.
     * Returns null when no field-level rules exist (= all fields allowed).
     */
    public function allowedFields(User $user, FormFieldPermissionAction $action, FormTemplate $template): ?array
    {
        if ($user->isAdmin()) return null;

        $rows = FormTemplateFieldPermission::where('form_template_id', $template->id)
            ->where('action', $action)
            ->where(function ($q) use ($user) { /* same subject OR clause */ })
            ->pluck('field_key');

        // No field rules defined → treat as unrestricted.
        if ($rows->isEmpty()) return null;

        return $rows->unique()->values()->all();
    }
}
```

Bind it in `AppServiceProvider` if you want an interface, or just inject the concrete class directly.

### 3. Updated policies

**`FormTemplatePolicy`** — replace `is_active` shortcut with ABAC call:
```php
public function view(User $user, FormTemplate $template): bool
{
    return app(FormPermissionService::class)
        ->userCanOnTemplate($user, FormPermissionAction::View, $template);
}

public function create(User $user): bool
{
    // No specific template yet — defer to form-submission policy at store time.
    // OR: check if user has 'create' on *any* active template (product decision).
    return true; // minimal: allow all authenticated users to attempt; enforced per-template at store
}
```

**`FormSubmissionPolicy`** — register it (fix the known gap), then:
```php
public function create(User $user, FormTemplate $template): bool
{
    return app(FormPermissionService::class)
        ->userCanOnTemplate($user, FormPermissionAction::Create, $template);
}

public function update(User $user, FormSubmission $submission): bool
{
    return app(FormPermissionService::class)
        ->userCanOnTemplate($user, FormPermissionAction::Edit, $submission->template);
}
```

Register in `AppServiceProvider::boot()`:
```php
Gate::policy(FormSubmission::class, FormSubmissionPolicy::class);
```

### 4. Controller changes

**`FormSubmissionController::store()`** — pass the template to the policy:
```php
$this->authorize('create', [FormSubmission::class, $template]);
```
(Laravel supports passing extra arguments to policies.)

**`FormSubmissionController::update()`** — already has the submission, policy uses `$submission->template`.

**Field stripping on save (minimal enforcement)**

In `StoreFormSubmissionRequest` / `UpdateFormSubmissionRequest`, after content validation, strip disallowed fields:
```php
$allowed = $this->permissionService->allowedFields($user, FormFieldPermissionAction::Fill, $template);
if ($allowed !== null) {
    $content = Arr::only($request->content, $allowed);
}
```

### 5. Admin API endpoints

New route file `routes/api/v1/form-permissions.php`:

```
GET    /form-templates/{template}/permissions              list
POST   /form-templates/{template}/permissions              grant
DELETE /form-templates/{template}/permissions/{permission} revoke

GET    /form-templates/{template}/field-permissions              list
POST   /form-templates/{template}/field-permissions              grant
DELETE /form-templates/{template}/field-permissions/{permission} revoke
```

All six routes gated with an admin check in their policy.

**`GET /form-templates/{template}/my-permissions`** (non-admin)
Returns the current user's resolved permissions for a template — both form-level actions and allowed field keys — so the frontend can render the form correctly before submitting.

### 6. `FormTemplateResource` update

Include resolved permissions for the authenticated user in the template response (or via the dedicated `my-permissions` endpoint — the latter is cleaner so clients can cache the template separately):

```json
{
  "id": 1,
  "name": "...",
  "template_version": { … },
  "my_permissions": {
    "view": true,
    "create": true,
    "edit": false,
    "fields": {
      "fill": ["name", "dob"],
      "edit": []
    }
  }
}
```

---

## Sequenced Implementation Steps

### Phase 1 — Form-level ABAC (minimal viable)

1. `php artisan make:migration create_form_template_permissions_table`
2. `php artisan make:model FormTemplatePermission`
3. Create `FormPermissionAction` enum
4. Create `FormPermissionService` with `userCanOnTemplate()`
5. Update `FormTemplatePolicy` (view, viewInactive)
6. Register & update `FormSubmissionPolicy` (create, update)
7. Update `FormSubmissionController` to pass template to `authorize('create', …)`
8. Create `FormTemplatePermissionController` (admin CRUD) + route file
9. `GET /form-templates/{template}/my-permissions` endpoint
10. Tests for all of the above

### Phase 2 — Field-level ABAC

1. `php artisan make:migration create_form_template_field_permissions_table`
2. `php artisan make:model FormTemplateFieldPermission`
3. Create `FormFieldPermissionAction` enum
4. Add `allowedFields()` to `FormPermissionService`
5. Strip disallowed fields in store/update requests
6. Include `fields` in `/my-permissions` response
7. Create `FormTemplateFieldPermissionController` (admin CRUD)
8. Tests

---

## Decisions to make before coding

| Question | Options |
|---|---|
| Default when no rules exist? | **Open** (allow all) vs **Closed** (deny all). Open is friendlier for migration; Closed is safer. Recommend Open by default, Closed opt-in per template. |
| `create` scope | Does "create" mean "submit *any* template" or is it per-template? Recommend per-template. |
| Field rules: allowlist or denylist? | Allowlist (specify who CAN fill a field) is simpler to reason about. |
| Field key schema coupling | Soft (key string, no FK) — recommended. Tight (FK to a field registry) — over-engineered for now. |
| `my-permissions` caching | JWT already embeds identity; token refresh required when membership changes (existing gap). No extra caching needed. |

---

## What This Does Not Cover

- Hierarchical inheritance (dept permission → team member automatically inherits) — defer.
- Time-bound permissions (e.g. access until a date) — defer.
- Field visibility (hidden vs read-only vs editable) beyond fill/edit — out of scope.
- Per-user overrides (override above role/dept/team) — out of scope.
- Audit log of permission grants/revocations — out of scope but easy to add with model events later.
