# Final Architecture / SPEC / UX Rubric — Inventory Desktop

**Review date:** 2026-09-25

**Scope:** Current architecture spine and diagrams, bound SPEC/brownfield sources, UX DESIGN/EXPERIENCE, and their decision logs.
**Verdict: PARTIAL — architecture contract is coherent, but material baseline acceptance details are not carried into SPEC/UX, and generated-source metadata is incomplete.** This is a planning-artifact review; it does not certify implementation, deployed database/runtime controls, accessibility rendering, or pilot readiness.

## Exact current sources checked

- `_bmad-output/planning-artifacts/architecture/architecture-inventory-desktop-2026-09-24/ARCHITECTURE-SPINE.md`, `architecture-diagrams.md`, `.memlog.md`
- `_bmad-output/specs/spec-inventori-desktop/SPEC.md`, `brownfield.md`, `.memlog.md`
- `_bmad-output/planning-artifacts/ux-designs/ux-website--2026-09-24/DESIGN.md`, `EXPERIENCE.md`, `.memlog.md`
- Existing specialist reports under `reviews/` and the architecture folder’s `reviews/` were treated as history only; current normative files above control.

## Rubric results

| Criterion | Result | Evidence-based assessment |
|---|---|---|
| AD structure, authority, and bindings | **Pass** | Spine is feature-altitude Laravel layered monolith, status `draft`; AD-1–AD-15 are numbered and each has `Binds`, `Prevents`, and `Rule`. Spine states it governs diagrams. CAP bindings are explicit per AD; frontmatter binds CAP-1–CAP-8. |
| All CAPs and ownership | **Pass** | Capability map has CAP-1 through CAP-8 exactly once, with governing ADs and layer owners. CAP-8 is separately owned by the existing sample-disposal route/domain; AD-10 limits inventory to read-only sample queries. CAP-1–CAP-8 intent and observable success are in SPEC. |
| Permission matrix and location/access owner | **Pass as target; named assignment deferred** | AD-7 lists view, master-data, receipt, issue, transfer, stocktake submit/approve, disposal, and grant-management permissions. Global permission and per-user/location `view`/`transact` grant are both required; source/destination are independently checked; missing grants deny. Inventory Administrator owns grant administration. Named recipients of `inventori.location-access.manage` and `inventori.stocktake.approve` remain open and are not inferred from edit rights. |
| Complete baseline followed by cycle counts | **Architecture pass; material SPEC/UX handoff gap** | AD-6 and the user override in the decision log establish one full approved physical opening baseline before routine writes, then selected-tuple partial cycle counts. Opening approval remains required at zero variance; cycle-count zero variance completes without movement/approval. However, SPEC §35/§53 and EXPERIENCE §§30, 64, 135–139 omit exact universe/snapshot requirements present in AD-6/clarification. See material finding 1. |
| Item/lot/tuple identity | **Architecture pass; downstream precision incomplete** | AD-4 defines exact tuple `(item_id, lot_id, location_id)`, null/non-null uniqueness, quantity/reservation constraints, and ordered locking. AD-6 clarifies dedupe on that key, historical tuple inclusion, tracked-item null-lot prohibition, and balance-existence snapshot. AD-8 defines provisional canonical item-code policy; AD-15 defines one lot per item by PostgreSQL `UPPER(BTRIM(lot_no))`, same-item collision rejection, cross-item separation, no disposed-lot reactivation, and interim ACTIVE-only receipt. SPEC captures core identity and explicit lot modes, but does not repeat all baseline tuple-eligibility/snapshot details. |
| Migration and runtime envelope | **Target contract pass; runtime evidence not checked** | AD-3/4/8/12/15 and migration convention require additive migrations/backfills, collision audits before uniqueness, PostgreSQL constraints/partial indexes and append-only ledger protection, deployed PostgreSQL plus `pdo_pgsql`, and PostgreSQL integration tests including application-role update/delete denial. SQLite is explicitly insufficient evidence for these guarantees. Stack marks deploy-managed framework/runtime and PostgreSQL server details as unverified. Nothing here proves migrations or deployed controls have run. |
| Location setup and authorization ownership | **Pass as target** | AD-2 requires an existing authorized location at receipt; creation/configuration/grants belong in Master Lokasi. AD-7 assigns grants to Inventory Administrator with independent view/transact rights. EXPERIENCE §§25, 35, 58–59 and Flow 7 preserve these boundaries. Assignment of the managing permission to named roles/users remains deferred. |
| Deferred questions and fail-closed boundaries | **Mostly aligned; stale question remains** | Spine/SPEC/UX defer item-code format and legacy collision/backfill, lot classification/status matrix, reservation ownership, future repeat full-baseline trigger, RETURN workflow, sample retention/date anchor, and named pilot/permission owners. Until resolved, current interim rules remain restrictive. SPEC §94 and EXPERIENCE §184 still ask whether post-history item-code correction needs a workflow even though AD-8 already selects stable codes and an audited identity-change path; clarify this as an assumption requiring owner confirmation, not an unresolved choice of whether audit is required. |
| Diagrams and generated-document source links | **Diagram pass; source metadata incomplete** | Dependency, mutation, baseline/cycle-count, grant, and balance diagrams generally preserve spine precedence and boundaries; they simplify lock detail without contradicting the AD rules. Declared relative companion paths resolve. However, SPEC frontmatter has `sources: []`; DESIGN/EXPERIENCE source lists omit the adopted architecture spine/diagrams despite architecture being a required UX/spec companion. Provenance links should identify the actual normative inputs used to generate/revise those docs. |

## Material findings

1. **Opening-baseline acceptance contract is not fully handed into SPEC and operator UX.** AD-6 §§74, 76–83 requires the union of existing balance tuples, both ledger endpoints (even when masters are inactive/disposed), and active item × every configured location × every applicable lot; all non-disposed lots of tracked items count, including quarantined/expired; active untracked items contribute null-lot only. Deduplicate exact item/lot/location tuples, persist explicit zeros and `balance_existed`, and invalidate/rebuild and recount affected coverage after master changes. SPEC §35/§53 and EXPERIENCE §64/Flow 3 expose a high-level “applicable non-disposed lot” formulation but omit some status inclusions, exact key/deduplication, and existence-bit semantics. Specify these in the generated operator manifest/checklist and acceptance text so two different manifests cannot both appear complete.
2. **Generated-source provenance is incomplete.** `SPEC.md` declares `sources: []`; UX DESIGN/EXPERIENCE `sources` omit the adopted architecture spine/diagram links. Although companion links and architecture-source references resolve, generated/revised artifacts do not identify all governing source documents in their own source metadata. Populate/verify those source links before relying on generated-doc traceability.

## Material issues not found

- No missing CAP binding or CAP-8 ownership gap found.
- No contradiction found in receipt’s existing-authorized-location rule, same-item/cross-item lot identity, permission/grant model, or sample-domain boundary.
- The opening-baseline versus later cycle-count sequence is decided; do not reopen it. Only its exact tuple and readiness-evidence handoff is incomplete.
- The diagrams do not replace the spine’s full lock order, but their simplification does not weaken or contradict the normative sequence.

## Verification and limits

- Read all exact current source files listed above, including the SPEC brownfield audit and three decision logs; cross-checked declared source/companion paths and current CAP/AD maps.
- No application tests, database migrations/queries, browser checks, deployment attestation, or pilot verification were performed.
- Only this rubric was updated. The broader worktree contains pre-existing unrelated changes; they were not included in this edit.
