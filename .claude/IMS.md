IMS – Development Roadmap
=========================

# 1. Introduction

### 1.1 Purpose

This document defines the requirements for an Information Management System designed to handle customized form inputs, multi-level permissions, and automated notifications. The system aims to provide an application interface that allows users to complete customized forms generated via a dynamic builder, supported by hierarchical user management and access control.

### 1.2 Scope

The project scope focuses on Attribute-Based Access Control, along with a dynamic form builder for creating customized templates. It includes a notification system that sends notifications to specific people when certain form fields are filled out, while ensuring operational continuity through an offline-capable architecture that allows users to work without internet and automatically syncs data when connectivity is restored.

# 2. System Overview

The IMS is composed of a set of modules that together deliver dynamic form management with hierarchical, attribute-based access control and automated notifications:

* **Authentication** – verifies user identity.
* **User Management** – manages users, roles, teams, and departments.
* **Authorization** – evaluates whether a user may perform an action on a resource (Attribute-Based Access Control).
* **Form Template** – defines reusable form structures via the dynamic builder.
* **Form** – handles the lifecycle of form data based on templates.
* **Version Control & Conflict Detection** – tracks form versions and detects concurrent edits.
* **Notification** – sends alerts triggered by system events.
* **Workflow** – manages form state transitions.
* **Form History** – records past changes to forms.
* **Assignment** – assigns forms or tasks to users, teams, or departments.
* **Priority** – categorizes forms or tasks by urgency.
* **Offline** – enables work without connectivity and syncs when restored.

# 3. Roadmap at a Glance

| Phase | Modules | Goal | Estimate |
| :---- | :---- | :---- | :---- |
| **Phase 1 — MVP** | Authentication, User Management, Authorization, Form Template, Form | Users can authenticate, be managed in a hierarchy, and create/submit forms from dynamically built templates under attribute-based access control. | ~5 Weeks |
| **Phase 2 — Collaboration & Tracking** | Notification, Version Control & Conflict Detection, Form History | The right people are notified, concurrent edits are detected, and a form's change history can be reviewed. | ~2 Weeks |
| **Phase 3 — Workflow & Distribution** | Workflow, Assignment, Priority | Forms move through an approval lifecycle and can be assigned and prioritized. | ~1 Week |
| **Phase 4 — Offline** | Offline | Users can work without connectivity and sync when restored. | ~2-3 Weeks |
| **Total** | | | **~11 Weeks** |

# 4. Phase 1 — MVP (~5 Weeks)

**Goal:** Deliver the core path — a user logs in, is placed in the role/team/department hierarchy, and can build templates and create/submit forms governed by attribute-based access control.

**Milestone:** An authorized user can build a template, create a form from it, and submit it; access is enforced by role, team, and department.

**Build order & dependencies:** Authentication → User Management → Form Template → Form → Authorization (each step depends on the previous).

### 4.1 Authentication — 2 Days

Verifies user identity by handling login, credential validation, and token/session generation.

Deliverables:

1. Authenticate users via login.
2. Validate user credentials.
3. Generate and manage authentication tokens/sessions.

### 4.2 User Management — 3 Days

Manages users, roles, teams, and departments, providing the hierarchical user structure that supports access control.

Deliverables:

1. Create, update, and disable users.
2. Create, update, and disable roles.
3. Create, update, and disable teams.
4. Create, update, and disable departments.

*Depends on: Authentication.*

### 4.3 Form Template — 1.5 Weeks

Defines and manages reusable form structures through a dynamic form builder, including fields, validation rules, and who is authorized to interact with each template.

Deliverables:

1. Create and manage reusable form templates via the dynamic builder.
2. Define template fields.
3. Define validation rules.
4. Define who is authorized to view, create, and edit forms based on the template.

*Depends on: User Management.*

### 4.4 Form — 1.5 Weeks

Handles the creation, editing, validation, storage, and submission of form data based on predefined templates.

Deliverables:

1. Create form data based on a template.
2. Edit form data.
3. Validate form data against the template.
4. Store form data.
5. Submit form data.

*Depends on: Form Template.*

### 4.5 Authorization — 1.5 Weeks

Determines whether a user is allowed to perform a specific action on a resource. Authorization is attribute-based, evaluating the user's role, team, and department.

Deliverables:

1. Determine whether a user may perform a specific action on a resource (create, view, edit a form, etc.).
2. Evaluate permissions based on the user's role, team, and department (Attribute-Based Access Control).

*Depends on: User Management (role/team/department attributes), Form Template, Form.*

# 5. Phase 2 — Collaboration & Tracking (~2 Weeks)

**Goal:** Stakeholders are notified of relevant events, simultaneous edits are caught, and a form's change history can be reviewed.

**Milestone:** A submitted form notifies the right people, surfaces a conflict when two users edit at once, and exposes its full change history.

### 5.1 Notification — 1 Week

Sends system alerts triggered by events, including notifying specific people when certain form fields are filled out.

Deliverables:

1. Send alerts via email and in-app notifications.
2. Trigger notifications on events such as form submission, assignment, and workflow changes.
3. Notify specific people when certain form fields are filled out.

*Depends on: Form. (Assignment- and workflow-triggered notifications are wired up when those modules land in Phase 3.)*

### 5.2 Version Control & Conflict Detection — 2-3 Days

Tracks and stores form versions and notifies users if there's a version conflict.

Deliverables:

1. Track form versions.
2. Store all versions in the database.
3. Notify users when a conflict is detected.

*Depends on: Form, Notification.*

### 5.3 Form History — 1-2 Days

Records and allows viewing of past changes to forms.

Deliverables:

1. Record past changes to forms, including who modified them and when.
2. Allow viewing of a form's change history.

*Depends on: Form.*

# 6. Phase 3 — Workflow & Distribution (~1 Week)

**Goal:** Forms progress through an approval lifecycle and can be assigned to the right owners and prioritized.

**Milestone:** A form can move draft → pending → approved/rejected, be assigned by scope (person/team/department/global), and flagged by priority.

### 6.1 Workflow — 2-3 Days

Manages the lifecycle of forms by controlling state transitions.

Deliverables:

1. Manage form lifecycle through state transitions such as draft, pending approval, approved, and rejected.
2. Work with the Authorization module to block certain actions in certain lifecycle states (e.g., no edits after a form is approved).

*Depends on: Form, Authorization.*

### 6.2 Assignment — 2-3 Days

Manages assigning forms or tasks to the users responsible for processing them.

Deliverables:

1. Assign forms or tasks to specific users, teams, or departments responsible for processing them.
2. When an admin creates the form template, the admin can set who can be assigned (specific people / team-wide / department-wide / global).

*Depends on: Form, Authorization.*

### 6.3 Priority — 1 Day

Allows forms or tasks to be categorized by urgency or importance, helping users process critical items first.

Deliverables:

1. Categorize forms or tasks by urgency or importance.

*Depends on: Form.*

# 7. Phase 4 — Offline (~2-3 Weeks)

**Goal:** Users remain productive without connectivity and reconcile their work on reconnect.

**Milestone:** A user creates/edits forms offline and the data syncs to the server when connectivity is restored.

### 7.1 Offline — 2-3 Weeks

Enables users to work without internet access and synchronizes data when connectivity is restored.

Deliverables:

1. Allow users to create or edit forms without internet access.
2. Synchronize data with the server once connectivity is restored.
3. Allow users to resolve conflict.

*Depends on: Form, Version Control & Conflict Detection.*
