# SPEC / Brownfield / UX / Architecture Reconciliation — Inventory Desktop

**Review date:** 2026-09-25

**Verdict: PARTIAL — approved choices are represented consistently in the current artifacts. Several exact acceptance clauses from the architecture are not stated with equal precision in the SPEC or operator-facing UX, and several operational decisions remain open.**

This is a planning-artifact reconciliation. It does not certify implementation, deployed runtime/database controls, rendered accessibility, or pilot readiness.

## Review basis

Read directly from the current checkout:

- `_bmad-output/specs/spec-inventori-desktop/SPEC.md` and `brownfield.md`
- `_bmad-output/specs/spec-inventori-desktop/.memlog.md` for decision provenance
- `_bmad-output/planning-artifacts/architecture/architecture-inventory-desktop-2026-09-24/ARCHITECTURE-SPINE.md`, `architecture-diagrams.md`, and `.memlog.md`
- `_bmad-output/planning-artifacts/ux-designs/ux-website--2026-09-24/DESIGN.md`, `EXPERIENCE.md`, and `.memlog.md`

The specified source files were available and read by their exact absolute paths. Decision logs were checked to distinguish approved user choices and overrides from earlier superseded assumptions. This review does not use derivative reviews or the project context as authority for inventory decisions.

Authority: SPEC is the capability and acceptance baseline; architecture spine governs detailed mutation, authorization, gate, and database invariants; diagrams support but do not override the spine. UX Experience/Design express operator-facing behavior and visual acceptance. Brownfield records observed current behavior and risks, not target behavior.

## Approved choices and acceptance baseline

The user decisions and overrides in the three decision logs match the current normative artifacts on the following points:

| Approved choice | Exact current acceptance / contract | Reconciliation |
|---|---|---|
| Complete opening baseline before routine live mutation; later routine counts may be selected-tuple cycles. | SPEC CAP-5: “Before live mutation, one approved baseline manifest is the deduplicated union of all existing balance and movement-endpoint tuples (including inactive/disposed masters) plus every active item × every configured location × each applicable non-disposed lot; an untracked item contributes one null-lot tuple per location.” It further requires one unique row per tuple, explicit zero, captured quantity, and balance-row existence; stale catalog changes require recount. AD-6 §§74–83 and EXPERIENCE §§32, 66, 81–84 specify the tuple scope, gate and separate cycle behavior. | **Aligned decision.** The baseline is not optional and must be approved even at zero variance; routine zero-variance cycles do not need approval or movement. A repeat full-location baseline trigger remains open. |
| Receipt uses a preconfigured authorized location; setup is through Master Lokasi. | SPEC CAP-3: receipt selects “one preconfigured authorized location”; constraints state new locations/types/grants are “set up only through Master Lokasi.” AD-2 requires one existing authorized `location_id`; the diagram submits a configured location. EXPERIENCE §§28, 60 and Flow 1 route setup to Master Lokasi. | **Aligned decision.** Earlier receipt-time location creation is superseded. |
| Same-item repeated supplier lot identity reuses one lot; cross-item same number remains distinct. | SPEC CAP-2 says “one canonical lot number identifies one lot per item, repeat receipts reuse that lot, the same number on another item remains distinct.” CAP-3 rejects duplicate new-lot creation. AD-15 defines `(item_id, canonical lot_no)` uniqueness and routes collisions to existing-lot mode. EXPERIENCE §59 and Flow 1 instruct the operator to select existing lot. | **Aligned decision.** New-lot mode cannot silently duplicate or reactivate a disposed lot; normalization/backfill details remain provisional. |
| Lot-tracked issue, transfer, and disposal use exactly one explicit lot; FEFO is advisory only. | SPEC CAP-4 and constraint §49 say one explicit lot and no cross-lot allocation. AD-5 and EXPERIENCE §§59, 128–133 repeat the same boundary. | **Aligned decision.** |
| Restricted-location access is a per-user/per-location grant with independent view/transact rights; reads/history and both movement endpoints are scoped. | SPEC CAP-1 and constraints §§54–57 specify global permission plus scoped grant, independent source/destination checks, historical read access, separate view/transact rights and fail-closed uncached authorization. AD-7/14 and EXPERIENCE §§37, 60–61, 80, 173–181 detail the same behavior. | **Aligned behavior.** Named assignment of the grant-management permission remains open; transact does not confer report/history access. |
| Stocktake variance requires an independent approver; zero-variance handling differs for opening baseline and later cycles. | SPEC CAP-5 requires complete opening baseline acceptance “even with zero variance,” atomic variance adjustments, and stale cycle revisions returning for recount without movement. AD-6 separates opening baseline approval from no-approval zero-variance cycle completion. EXPERIENCE §§65–67, 82–83 makes both visible. | **Aligned decision.** Exact stale-snapshot/existence rules need more explicit acceptance traceability (below). |
| Ledger is append-only and balances are atomic projections; transfers net to zero globally. | SPEC constraints §§50–69 and CAP-6 require ledger, atomic mutation, database guards and neutral all-location transfer reconciliation. AD-3/9 and diagrams define DB enforcement and equal/opposite projection effects. | **Aligned target contract.** This is not evidence that current code or deployment already enforces it. |
| Inventory sample integration is read-only; sample destruction remains a separate workflow. | SPEC CAP-8 and constraints §§62, 68; AD-10; EXPERIENCE §§35, 41, 69, 165–171. | **Aligned decision.** |

## Acceptance-language reconciliation and handoffs

| Area | Exact source comparison | Disposition |
|---|---|---|
| Opening-baseline manifest and stale detection | SPEC CAP-5 already requires the deduplicated union, explicit zero, captured quantity and balance-row existence, includes inactive/disposed history, requires onboarding unknown stock, and invalidates/recounts after item/lot/location changes. Architecture AD-6 clarification §§76–83 is more exact: every configured location; for tracked items every non-disposed lot, including quarantined/expired lots; null-lot expansion only for classified untracked items; versioned classification/catalog snapshot; dedupe key `(item_id, lot_id, location_id)`; unclassified active items block approval; and a changed row-existence bit (including absent-to-present at zero) invalidates approval. EXPERIENCE §§66–67 gives the broad operator contract but omits those exact status/snapshot checks. | **Real acceptance/UX precision gap.** Add the architecture’s exact lot inclusion, classification/catalog snapshot, tuple key and absent-to-present-at-zero stale condition to the SPEC acceptance language and operator-visible recount behavior. Baseline decision itself is settled. |
| Later cycle-count revision | SPEC CAP-5 states exact selected tuple manifest and stale quantity/row-existence changes return for recount without movement. AD-6 defines immutable revisions, exact tuple set, quantity and existence snapshot, and pending-to-applied atomic transition. EXPERIENCE §65 and state/flow sections describe approval but do not plainly tell the operator that stale/missing/changed tuple snapshots—including absent-to-present-at-zero—return for recount without movement. | **Precision handoff.** Preserve selected-tuple scope; explicitly add stale tuple, missing/duplicate row, row-existence and quantity revalidation to UX acceptance and recount messaging. Do not reopen baseline-vs-cycle scope. |
| Global permission key traceability | SPEC CAP-1 says each action checks its “corresponding global permission” and constraints §§55–57 require separate least-privilege permissions, but the SPEC does not enumerate the action/key matrix or state the exact new-lot dual authority. AD-7 §§90–105 enumerates the matrix and AD-2 says new-lot receipt requires `inventori.receipt.create` plus `inventori.create`; existing-lot receipt requires receipt authority. EXPERIENCE §59 states the dual permission for new-lot mode but not the full matrix. | **SPEC traceability gap.** Reference the AD-7 key/action matrix in SPEC acceptance and expressly state new-lot versus existing-lot authority. Do not infer named role assignments. |
| PostgreSQL enforcement and activation evidence | SPEC CAP-5/constraints §§63, 69 require PostgreSQL-boundary invariants, database tests and readiness controls, but do not enumerate each test result. AD-3/4/12 §§49–63, 131–139 specifies DB append-only denial for normal application roles including bulk/raw paths, quantity/endpoint checks, separate null/non-null tuple uniqueness, PostgreSQL/`pdo_pgsql`, application-role integration tests, and fail-closed activation evidence. EXPERIENCE §84 has a readiness checklist but not individually inspectable control/evidence rows or named Inventory Administrator/system-owner acceptance. | **Acceptance/readiness checklist granularity gap.** Track each guard, PostgreSQL runtime/extension, role-based ledger denial test, backup/restore, rehearsal, monitoring/escalation and disable/rollback as a blocking item with owner, evidence/result, status and next action. No source establishes deployed runtime or passing evidence. |
| Item identity and lot normalization | SPEC constraints §§58–60, 94, 99 make item-code ASCII trim/uppercase provisional and require legacy review; lot normalization/backfill and receivable-status matrix remain open. AD-8/15 state assumed canonical forms and migration safeguards while explicitly deferring confirmation/collision handling. EXPERIENCE §59 uses canonical lot behavior and §186 calls out unresolved decisions. | **Decision partly adopted; policy details remain open.** Preserve no-auto-merge and per-item lot identity. Confirm formats, collisions/backfill, correction policy and status/expiry matrix before constraints are enforced. |
| Visual primary action | DESIGN §§23–32, 82–85 and §105 specify light `primary-700`/white and dark semantic `primary-500`/dark-text pairs and require both button variants to use them. The design source does not provide rendered-browser verification. | **UX sign-off evidence pending.** Confirm both variants in light/dark rendered states and run contrast verification; this does not alter inventory behavior acceptance. |

## Brownfield evidence (current behavior, not target decisions)

The directly read `brownfield.md` §§14–29 records missing equivalent permission enforcement on inventory routes, destructive item bulk-delete behavior, pre-transaction receipt master creation, missing item-lot/location validation, direct stocktake adjustment without approval, stock-card opening/transfer calculation defects, preview-limited dashboard totals, null-lot PostgreSQL uniqueness gap, and reserved-vs-on-hand mismatch. It also records existing sample-disposal safeguards that must be retained. These observations support the acceptance requirements above; they do not show those target controls are implemented.

## Unresolved owner decisions

The current SPEC Open Questions §§93–102, architecture Deferred §§213–224 and UX handoff §§185–192 still leave these decisions/evidence pending:

1. Assign named roles/users for `inventori.stocktake.approve` and `inventori.location-access.manage`; confirm approver delegation/thresholds and pilot sign-off owners.
2. Confirm item-code allowed format, legacy collision/backfill owner and audited post-history correction policy.
3. Approve the item lot-tracking matrix, including categories allowed without lots and treatment of legacy null-lot balances.
4. Confirm canonical lot-number normalization/backfill collision handling and receivable status/expiry matrix; `ACTIVE`-only is interim fail-closed behavior.
5. Define when a later repeat full-location baseline is required; initial baseline before activation and selected-tuple cycles afterward are settled.
6. Confirm whether `reserved_qty` is operational and identify its reserve/release owner; no reservation writer is authorized meanwhile.
7. Decide whether a separate stock-return workflow is needed before `RETURN` can be written.
8. Confirm sample-destruction retention period and authoritative completion-date anchor if the configured rule changes.
9. Validate transaction journeys with inventory personnel and verify rendered light/dark primary-action contrast.
10. Complete pilot readiness evidence and named Inventory Administrator/system-owner acceptance before routine mutation is enabled.

## Disposition

The requested source files are present and were read directly; no source absence is inferred. The approved choices—including full opening baseline before live mutation, selected-tuple later cycle counts, preconfigured receipt location, item-scoped lot reuse, one explicit lot for tracked operations, distinct global action permissions, scoped location grants, and separate sample destruction—are represented in the current artifacts. The remaining reconciliation work is exact acceptance wording for manifest/snapshot and PostgreSQL evidence, plus operational policy/evidence still identified as open in the sources. Brownfield/code and deployment findings remain implementation or verification work, not unmade product decisions.

## Verification limits

Compared SPEC, brownfield, architecture spine/diagrams, UX DESIGN/EXPERIENCE and the corresponding decision logs. Did not run application tests, browser checks, database queries, deployment attestation or pilot verification. No normative source artifact was changed; only this review was updated.
