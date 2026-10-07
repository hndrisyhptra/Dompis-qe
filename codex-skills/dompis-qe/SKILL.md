---
name: dompis-qe
description: Audit, diagnose, extend, and verify the Dompis QE Laravel application while preserving LOP lifecycle, location-scoped admin access, BOQ snapshots, technician evidence workflow, import queues, and production migration safety. Use for Dompis QE bug fixes, features, UI, database audits, LOP/BOQ import, evidence approval, technician mobile flow, dashboard, revenue, reporting, or deployment preparation.
---

# Dompis QE

Use this skill only inside the Dompis QE repository. It is a project continuity guide, not a generic Laravel skill.

## Start here

Before taking task actions:

1. Read repository-root `AGENTS.md` completely.
2. Read `HANDOVER_DOMPIS_QE.md`, prioritizing repository state, invariants, latest changes, database status, and open issues.
3. Treat `CLAUDE.md` and `.claude/skills/dompis-qe-development/SKILL.md` as historical requirements. Validate them against current code.
4. Snapshot branch, `git status --short`, recent commits, and relevant diff. Preserve existing user changes.

## Choose the work mode

### Audit or diagnosis

- Trace route -> middleware -> request/policy -> controller -> service -> model/query -> Blade/JavaScript -> side effects.
- Identify actor role, admin scope, LOP status, program, project status, assignment, and data source.
- Verify assumptions through code, tests, and read-only schema/data checks.
- Report cause, impact, and related files. Do not implement unless the user asks for a change.

### Bug fix

- Reproduce or encode the failure in the narrowest relevant regression test.
- Fix the canonical service/policy/helper rather than masking the error in one view.
- Check scoped versus SUPER_ADMIN behavior and legacy location fallback.
- For mobile evidence, check original file, thumbnail, authenticated streaming, preview, retry, replace, and server limits.
- For import, check parser, normalization, transaction, queue status, result rows, and worker behavior.

### New feature

- Map role/permission, scope, source of truth, state transitions, audit history, queue/storage effects, UI entry points, and responsive behavior.
- Reuse existing services and components before adding another abstraction.
- Add authorization and workflow tests with the implementation.
- Update the handover if architecture, schema, lifecycle, or operational behavior changes.

### Database or migration work

- Start with read-only `migrate:status` and `db:table` inspection.
- Compare migration ledger, physical schema, model casts/fillable, and production error evidence.
- Create additive migrations; do not rewrite applied migration history as the only fix.
- Never execute migration, seeder, rollback, fresh, wipe, or write query without explicit user authorization.
- For narrowing or destructive rollback, guard against existing data that cannot fit.
- Distinguish verified local state from unknown server state.

### Deployment preparation

- Confirm target branch and commit before writing commands.
- Detect tracked/untracked server changes before pull.
- Recommend backup/tag, `--ff-only`, dependency install, build, migration, cache rebuild, queue restart, permissions, and maintenance-mode recovery.
- Do not claim deployment success unless logs/status from that server confirm it.

## Non-negotiable project rules

- Assignment source: `qe_lop_assignments`.
- Status source: `qe_lops.status_lop`; transition through `LopService`.
- Admin scope source: `LopVisibilityService`.
- Evidence source: `qe_evidences`; approval completion requires every reviewable workflow evidence approved. Supporting `request_letter` documents are explicitly excluded from approval/progress aggregates.
- Workflow completeness source: `TechnicianWorkflowService::state()` and `ProjectProgressService`.
- BOQ/value source: `BoqService` and `LopBoqValueService`; preserve snapshots.
- Regional package source: `RegionalPackageResolver`.
- Revenue actual includes only completed LOPs; Recovery has no plan.
- Preserve legacy `branch`/`sto` fallback until an explicit audited backfill removes the need.

## Verification routing

- Import/BOQ: `BulkImportAndBoqTest`, `LopExcelImportTest`, `DesignatorCsvImportTest`.
- Evidence/approval: `EvidenceWorkflowTest`, `EvidenceAsyncUploadTest`, `EvidencePermissionTest`.
- Technician flow: `TechnicianMobileWorkflowTest`.
- Scope/status: `AdminScopeAndProjectStatusTest`, `LopWorkflowTest`, `LopPermissionTest`.
- Dashboard/revenue: `AdminDashboardTest`, `RevenueDashboardTest`.
- Reporting: `MaterialReportTest`.
- User/RBAC: `UserManagementWorkflowTest`, `UserPermissionTest`, `UserHasPermissionTest`.

For Blade/UI changes, compile views and run the Vite production build. Always run `git diff --check`. Broaden to the full suite when shared services, migrations, status, scope, or aggregate values change.

## Finish

Summarize outcome, changed files, tests, database/deployment impact, and unresolved risk. Update `HANDOVER_DOMPIS_QE.md` when current truth changes. Preserve a clear distinction between committed history, uncommitted work, verified local behavior, and unverified production state.
