# ABAC Implementation — Form-Level Permissions

## Overview

This document describes the Attribute-Based Access Control (ABAC) system for form templates and submissions. It covers the schema, how permission decisions are made, how to provision access, and migration notes for existing deployments.

---

## Phase Implemented

**Phase 1: Form-level ABAC** — controls who can `view`, `create`, and `edit` at the form-template level.

**Phase 2: Field-level ABAC** — not yet implemented. See [abac-plan.md](abac-plan.md) for the design.

---

## How Authorization Decisions Work

### The three actions

| Action | Covers |
|--------|--------|
| `view` | `GET /api/v1/form-templates/{id}` — reading a specific template |
| `create` | `POST /api/v1/form-submissions` — submitting against a template |
| `edit` | `PUT /api/v1/form-submissions/{id}` — editing a submission tied to a template |

### Decision logic

1. **Admin bypass** — a user whose role is named `admin` always passes every check.
2. **Open by default** — if no permission rows exist for a given `(template, action)` pair, *all authenticated users are allowed*. This is the default state for new and existing templates.
3. **Restricted mode** — the moment at least one permission row is added for `(template, action)`, only users matching a row can perform that action. Users who don't match any row are denied (403).

A user "matches" a row when:
- their `role_id` equals the row's `permissible_id` and `permissible_type = 'role'`, **OR**
- one of their department IDs equals the row's `permissible_id` and `permissible_type = 'department'`, **OR**
- one of their team IDs equals the row's `permissible_id` and `permissible_type = 'team'`

Multiple grants are OR-ed — a user only needs to satisfy one.

Each action is evaluated independently. A user can be granted `view` but denied `create` on the same template.

---

## Database Schema

### `form_template_permissions`

```
id                 bigint PK
form_template_id   bigint FK → form_templates.id (cascade delete)
action             string   — 'view' | 'create' | 'edit'
permissible_type   string   — 'role' | 'department' | 'team'
permissible_id     bigint   — ID in the corresponding table
created_at         timestamp
updated_at         timestamp

UNIQUE (form_template_id, action, permissible_type, permissible_id)
INDEX  (permissible_type, permissible_id)
```

`permissible_type` stores the **morph alias** (`role`, `department`, or `team`), not the full class name. This is controlled by the morph map registered in `AppServiceProvider::boot()`.

---

## API Reference

All endpoints require `Authorization: Bearer <jwt>`.

### Admin CRUD

#### List permission grants for a template
```
GET /api/v1/form-templates/{id}/permissions
```
Admin only. Returns all permission rows for the template.

Response:
```json
{
  "data": [
    {
      "id": 1,
      "form_template_id": 5,
      "action": "view",
      "permissible_type": "role",
      "permissible_id": 2,
      "created_at": "2026-06-18T..."
    }
  ]
}
```

#### Grant a permission
```
POST /api/v1/form-templates/{id}/permissions
```
Admin only. Idempotent — duplicate grants are silently ignored.

Request body:
```json
{
  "action": "view",              // required: view | create | edit
  "permissible_type": "role",    // required: role | department | team
  "permissible_id": 2            // required: ID of the role/dept/team
}
```

#### Revoke a permission
```
DELETE /api/v1/form-templates/{id}/permissions/{permission_id}
```
Admin only. Returns 204 on success, 404 if the permission doesn't belong to the given template.

---

### User endpoint

#### Resolve my permissions on a template
```
GET /api/v1/form-templates/{id}/my-permissions
```
Available to all authenticated users. Returns the resolved boolean for each action for the calling user.

Response:
```json
{
  "data": {
    "form_template_id": 5,
    "permissions": {
      "view": true,
      "create": false,
      "edit": true
    }
  }
}
```

The frontend should call this before rendering a form to determine which actions are available.

---

## Provisioning Permissions (Admin Guide)

### Scenario 1 — Restrict who can view a template

By default everyone can see it. To restrict it to a specific role:

```bash
# First, find the role ID
GET /api/v1/roles

# Grant view to that role
POST /api/v1/form-templates/5/permissions
{
  "action": "view",
  "permissible_type": "role",
  "permissible_id": 3
}
```

After this, only users with role ID 3 (or admins) can view the template.

### Scenario 2 — Allow only one department to create submissions

```bash
POST /api/v1/form-templates/5/permissions
{
  "action": "create",
  "permissible_type": "department",
  "permissible_id": 7
}
```

### Scenario 3 — Grant multiple subjects

Make multiple `POST` calls. Each row is independent. A user matching any one of them is granted access.

```bash
# Department can view
POST /api/v1/form-templates/5/permissions
{ "action": "view", "permissible_type": "department", "permissible_id": 7 }

# A specific team can also view
POST /api/v1/form-templates/5/permissions
{ "action": "view", "permissible_type": "team", "permissible_id": 12 }
```

### Scenario 4 — Fully open (default)

Don't add any permission rows. Everyone can perform all actions.

### Scenario 5 — Revoke access

```bash
# Find the grant ID
GET /api/v1/form-templates/5/permissions

# Delete it
DELETE /api/v1/form-templates/5/permissions/1
```

---

## Files Changed

| File | Change |
|------|--------|
| `database/migrations/2026_06_18_*_create_form_template_permissions_table.php` | New table |
| `app/Enums/FormPermissionAction.php` | New enum |
| `app/Models/FormTemplatePermission.php` | New model |
| `app/Models/FormTemplate.php` | Added `permissions()` HasMany |
| `app/Services/Form/FormPermissionService.php` | New service (core decision logic) |
| `app/Policies/FormTemplatePolicy.php` | `view()` now delegates to service |
| `app/Policies/FormSubmissionPolicy.php` | `create()` and `update()` now delegate to service |
| `app/Providers/AppServiceProvider.php` | Registered `FormSubmissionPolicy`; added morph map |
| `app/Http/Controllers/Api/V1/Form/FormSubmissionController.php` | Added `authorize()` calls for store/update |
| `app/Http/Controllers/Api/V1/Form/FormTemplatePermissionController.php` | New CRUD + my-permissions controller |
| `app/Http/Requests/Form/StoreFormTemplatePermissionRequest.php` | New form request |
| `app/Http/Resources/Form/FormTemplatePermissionResource.php` | New resource |
| `routes/api/v1/form-permissions.php` | New route file |
| `routes/api.php` | Includes form-permissions routes |
| `tests/Feature/Api/V1/Form/FormTemplatePermissionTest.php` | 33 new ABAC tests |
| `tests/Feature/Api/V1/Form/FormSchemaValidationTest.php` | Updated 6 submission tests to include `form_template_version_id` |

---

## Migration Notes (Existing Deployments)

### Running the migration

```bash
php artisan migrate
```

This creates the `form_template_permissions` table. No existing data is affected.

### Behaviour after migration (no action required for open access)

Existing templates have zero rows in `form_template_permissions`. The system treats this as **open by default** — existing users can still view templates and create/edit submissions exactly as before. No permissions need to be provisioned to maintain current behaviour.

### Registering FormSubmissionPolicy (previously unregistered)

`FormSubmissionPolicy` was previously unregistered (a known gap documented in `CLAUDE.md`). It is now registered in `AppServiceProvider::boot()`. Because `create()` and `update()` both follow open-by-default when no grants exist, this change is backwards-compatible.

### Morph map

A morph map is registered in `AppServiceProvider::boot()`:

```php
Relation::morphMap([
    'role'       => Role::class,
    'department' => Department::class,
    'team'       => Team::class,
]);
```

This only affects the `FormTemplatePermission` model (the only morph relation in the project). It stores `permissible_type` as `role`/`department`/`team` instead of full class names. There are no existing rows, so no data migration is needed.

---

## Known Limitations (Phase 1)

- **`index` listing is not ABAC-filtered.** `GET /api/v1/form-templates` filters by `is_active` only. A user who is denied `view` on a specific template can still see it in the list; the 403 only fires on `show`. Filtering the listing requires a subquery against the permissions table — deferred to a future phase.
- **Field-level permissions are not implemented.** The design is in [abac-plan.md](abac-plan.md).
- **No hierarchical inheritance.** A department grant does not automatically propagate to its teams.
- **Token must be refreshed after membership changes.** JWT claims embed role/dept/team IDs at issue time; the user must refresh their token if their memberships change (existing limitation).
