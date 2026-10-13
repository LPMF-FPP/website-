# Adversarial Review — Inventory Architecture Seams

**Reviewed:** Current `ARCHITECTURE-SPINE.md` (224 lines), `architecture-diagrams.md` (123 lines), and linked stocktake rules in `SPEC.md` and the spine clarification.

**Date:** 2026-09-25
**Verdict: PASS — no material current gaps found in the requested seams.**

## Findings

No material findings.

## Requested seams rechecked

- **Lock order vs AD-13:** The stock-mutation sequence acquires the shared mutation gate first, then actor/permission rows, locations/grants, item/lot masters, and ordered balance tuples. Uncached permission resolution occurs after permission locks. Grant mutation follows AD-13's authorization-row order while intentionally omitting the stock gate and balances; gate disable takes the exclusive gate lock and no later row-class locks.
- **Opening-baseline tuple universe:** AD-6 and its clarification define the deduplicated union of existing balance tuples, both movement endpoints, and active-item/catalog expansion at every configured location; they specify applicable lots, untracked null-lot tuples, explicit zero lines, and `balance_existed` snapshots. Existing historical tuples survive inactive/disposed master status. The diagram delegates manifest generation to AD-6.
- **Manifest invalidation on master-data changes:** AD-6 invalidates the manifest after item, lot, or location changes during opening-baseline mode and requires regeneration/recount; the diagram repeats this dependency. Unknown physical stock must be onboarded before finalizing the refreshed manifest.
- **Mutation-gate serialization:** AD-12 requires a shared gate-row lock held through mutation commit, and exclusive emergency disable waits for in-flight mutations before disabling. AD-13 makes the gate the first mutation lock. The mutation diagram keeps the transaction open through movement/projection commit.
- **Receipt and lot identity:** AD-2 permits only an existing authorized location, requires explicit exclusive `existing`/`new`/eligible `untracked` mode, validates item ownership/status, and places new-lot creation plus receipt effects in one service-owned transaction. AD-15 defines per-item canonical lot uniqueness and rejects collisions or disposed-lot reactivation; the diagram specifies insert-only-if-absent.
- **Item-code database normalization:** AD-8 defines the canonical ASCII trim/uppercase domain, a database check equivalent, unique storage, and additive backfill/write-freeze rollout. Its format and collision/correction policy are explicitly marked assumptions/deferred owner decisions, not unstated guarantees.
- **Approval manifest and idempotency:** AD-6 requires immutable revisions, exact tuple-set and existence/quantity revalidation, independent approval (including zero-variance opening baseline), and atomic application. The diagram binds retries to batch/revision/manifest and requires matching approver decision and payload to return the stored outcome.
- **Grant event semantics:** AD-14 requires one atomic projection-plus-event write for a real grant change and a no-op response without an extra event. The grant diagram matches both paths and serializes absent grant creation using locked parent rows.
- **PostgreSQL guards:** AD-3 requires database-level movement update/delete denial for normal application roles, including bulk/raw paths, and database quantity/endpoint constraints. AD-12 requires PostgreSQL/`pdo_pgsql` and PostgreSQL integration verification before claiming those guarantees; SQLite is explicitly insufficient for that claim.

This is a document-contract review only. It does not assert that runtime implementation or deployed PostgreSQL controls satisfy these target rules.
