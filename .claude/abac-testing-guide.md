# Testing the ABAC Permission System

## Prerequisites

The app must be running (`composer run dev`) and migrated (`php artisan migrate --seed`).

The seeded admin account is `admin@example.com` / `password`.

All examples use `curl`. Swap the base URL if yours differs.

---

## Step 1 — Get a JWT token

**Log in as admin:**
```bash
curl -s -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}' \
  | jq '.access_token'
```

Save the token:
```bash
ADMIN_TOKEN="<paste token here>"
```

**Log in as a regular user** (create one first if needed):
```bash
curl -s -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"user@example.com","password":"password","password_confirmation":"password"}'

curl -s -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}' \
  | jq '.access_token'

USER_TOKEN="<paste token here>"
```

---

## Step 2 — Create a template and note its ID

```bash
curl -s -X POST http://localhost:8000/api/v1/form-templates \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Test Form","json_schema":{"type":"object","properties":{"answer":{"type":"string"}}},"ui_schema":{}}' \
  | jq '.data.id'

TEMPLATE_ID=<paste id here>
```

---

## Step 3 — Check current permissions (open by default)

Right now, no grants exist, so **everyone can do everything**:

```bash
curl -s http://localhost:8000/api/v1/form-templates/$TEMPLATE_ID/my-permissions \
  -H "Authorization: Bearer $USER_TOKEN" | jq
```

Expected response — all `true`:
```json
{
  "data": {
    "form_template_id": 1,
    "permissions": {
      "view": true,
      "create": true,
      "edit": true
    }
  }
}
```

---

## Step 4 — Restrict the `create` action to a specific role

First, find the role ID you want to grant to (e.g. an existing role):
```bash
curl -s http://localhost:8000/api/v1/roles \
  -H "Authorization: Bearer $ADMIN_TOKEN" | jq '.data[] | {id, name}'

ROLE_ID=<paste role id here>
```

Grant `create` to that role:
```bash
curl -s -X POST http://localhost:8000/api/v1/form-templates/$TEMPLATE_ID/permissions \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"action\":\"create\",\"permissible_type\":\"role\",\"permissible_id\":$ROLE_ID}"
```

Now the regular user (who is **not** in that role) is locked out of `create`:
```bash
curl -s http://localhost:8000/api/v1/form-templates/$TEMPLATE_ID/my-permissions \
  -H "Authorization: Bearer $USER_TOKEN" | jq '.data.permissions'
# → { "view": true, "create": false, "edit": true }
```

Trying to submit a form as that user returns 403:
```bash
curl -s -X POST http://localhost:8000/api/v1/form-submissions \
  -H "Authorization: Bearer $USER_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"form_template_id\":$TEMPLATE_ID,\"form_template_version_id\":1,\"form_name\":\"Test\",\"content\":{\"answer\":\"hello\"}}"
# → 403 Forbidden
```

Admin is never blocked regardless of grants:
```bash
curl -s -X POST http://localhost:8000/api/v1/form-submissions \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"form_template_id\":$TEMPLATE_ID,\"form_template_version_id\":1,\"form_name\":\"Test\",\"content\":{\"answer\":\"hello\"}}"
# → 201 Created
```

---

## Step 5 — Grant via department or team

Works the same way — just change `permissible_type`:

```bash
# Grant 'view' to a department
curl -s -X POST http://localhost:8000/api/v1/form-templates/$TEMPLATE_ID/permissions \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"action":"view","permissible_type":"department","permissible_id":2}'

# Grant 'edit' to a team
curl -s -X POST http://localhost:8000/api/v1/form-templates/$TEMPLATE_ID/permissions \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"action":"edit","permissible_type":"team","permissible_id":3}'
```

A user satisfies the check if **any one** of their role/departments/teams matches a grant for that action. Multiple grants are OR-ed together.

---

## Step 6 — List and revoke grants

List all grants on the template:
```bash
curl -s http://localhost:8000/api/v1/form-templates/$TEMPLATE_ID/permissions \
  -H "Authorization: Bearer $ADMIN_TOKEN" | jq '.data[] | {id, action, permissible_type, permissible_id}'

PERM_ID=<paste permission id here>
```

Revoke one:
```bash
curl -s -X DELETE http://localhost:8000/api/v1/form-templates/$TEMPLATE_ID/permissions/$PERM_ID \
  -H "Authorization: Bearer $ADMIN_TOKEN"
# → 204 No Content
```

Once all grants for an action are revoked, it goes back to open-default (everyone allowed).

---

## Key rules to remember

| Rule | Behaviour |
|------|-----------|
| No grants for an action | Everyone is allowed (open default) |
| At least one grant exists | Only matching subjects are allowed |
| Subject match | User's role OR any of their departments OR any of their teams |
| Admin | Always allowed regardless of grants |
| Duplicate grant | Silently ignored (idempotent POST) |
| Actions are independent | A user can have `view` but not `create` on the same template |
