# UX / SPEC / Architecture Reconciliation — Inventory Desktop

**Review date:** 2026-09-25
**Verdict: MATERIAL HANDOFF GAPS REMAIN.** Receipt location, item-scoped lot identity/status handling, post-baseline cycle counts, the grant-management journey and named-role deferral, and the sample-destruction boundary are aligned. The opening-baseline UI still omits material tuple/snapshot details and several atomic readiness blockers. DESIGN's semantic-button contrast statement is not reflected by the current semantic CSS.

This is a planning-artifact and declared-CSS review. It is not evidence of rendered contrast, implemented behavior, deployment readiness, or pilot approval.

## Reviewed sources

- `_bmad-output/planning-artifacts/ux-designs/ux-website--2026-09-24/DESIGN.md`
- `_bmad-output/planning-artifacts/ux-designs/ux-website--2026-09-24/EXPERIENCE.md`
- `_bmad-output/planning-artifacts/ux-designs/ux-website--2026-09-24/.memlog.md`
- `_bmad-output/specs/spec-inventori-desktop/SPEC.md` and `.memlog.md`
- `_bmad-output/planning-artifacts/architecture/architecture-inventory-desktop-2026-09-24/ARCHITECTURE-SPINE.md`
- `_bmad-output/planning-artifacts/architecture/architecture-inventory-desktop-2026-09-24/architecture-diagrams.md`
- `resources/css/theme-semantic.css`

The architecture spine governs architectural contracts; the SPEC and UX decision log confirm selected user decisions and open owner assignments. CSS inspection is declaration-level only.

## Material findings

| Area | Reconciliation | Finding |
|---|---|---|
| Receipt location | SPEC constraint §52 and AD-2 require one existing authorized location; new locations/configuration/grants go through Master Lokasi. EXPERIENCE §§26, 58, 116–121 matches this and returns the operator to the draft. | **Aligned.** Receipt must not create a location. |
| Duplicate lot and status | SPEC CAP-2/3 and AD-15 require one canonical lot per item, reject a same-item duplicate in new-lot mode and route to existing-lot mode; the same number on another item is distinct. Existing-lot receipt is ACTIVE-only until an approved status matrix exists; disposed lots are not reactivated. EXPERIENCE §§57, 117 captures duplicate handling and status restrictions. | **Aligned.** Keep the existing-lot route actionable at collision and preserve the distinction between same-item collision and cross-item identity. The receivable-status matrix remains an owner decision. |
| Opening baseline manifest | SPEC CAP-5/constraint §53 and AD-6 §§74–82 require the union of existing balance tuples, all ledger endpoint tuples (including inactive/disposed historical masters), and catalog expansion for active items across every configured location. Include every non-disposed lot for tracked items (including quarantined/expired), null-lot tuples only for classified untracked items, exact-key deduplication, explicit zero rows, and `balance_existed`; unknown physical stock must be onboarded first and changes invalidate/rebuild the manifest. EXPERIENCE §64 and Flow 3 §§135–141 show the union, historical tuples, zero rows, onboarding, invalidation, and recount, but omit exact lot-status inclusion, deduplication key, and absent-versus-existing-zero snapshot. | **Material operator-manifest gap.** State the exact tuple eligibility and snapshot contract in the manifest and counting journey. Without `balance_existed`, an absent row later appearing at zero is not distinguishable as a stale snapshot requiring recount. |
| Baseline checklist / gate | AD-12 §135 and §139 require fail-closed mode, approved baseline, named Inventory Administrator and system-owner acceptance of lot-tracking/item-code policy and permission/grant assignments, rehearsal, backup/restore, monitoring/escalation, disable/rollback, deployed PostgreSQL + `pdo_pgsql`, and passing PostgreSQL tests for null-lot uniqueness, quantity/endpoints, and application-role ledger update/delete denial. | EXPERIENCE §82 and Flow 3 §§135–139 show useful owner/evidence/status/next-step language and broad checklist categories, but do not enumerate runtime `pdo_pgsql`, individual guard-test results, the exact policy/assignment acceptance, or transition evidence. **Material gate-evidence gap:** make each a blocking, independently inspectable checklist item; do not present the gate as active until all evidence and sign-offs are accepted. |
| Grant action and permission roles | AD-7 §§87–105 and AD-14 §151 define `inventori.location-access.manage`, per-user/per-location independent view/transact rights, atomic grant state/audit, and separate least-privilege permissions for each global action. EXPERIENCE IA §25, §35, grant editor §59, state §83 and Flow 7 §§171–179 provide the grant-management route, actor, scope, rights, confirmation and audited result. EXPERIENCE §183 leaves assignment of grant-management and approval permissions to named roles/users open, as do SPEC §88/§93 and architecture Deferred §§213–214. | **Aligned and appropriately open.** Preserve Inventory Administrator as the grant manager; keep assignment of `inventori.location-access.manage` and `inventori.stocktake.approve` to named roles/users for the operational owner. Do not infer either from `inventori.edit`. |
| Counts after opening baseline | AD-6 and AD-12 specify one complete, independently approved opening baseline before active mutations; later routine cycle counts use exactly selected tuples and are partial. EXPERIENCE §§30, 64, 80–82, Flow 3 and Open Question §185 express that same sequence. | **Aligned.** Future trigger/frequency for a repeat full re-baseline remains open; it does not change the selected post-opening cycle-count behavior. |
| Semantic button contrast | DESIGN frontmatter §§27–30 and §§82, 103 specify light `#047857`/white and dark `#10B981`/`#0F172A` pairs for both primary button variants and claim 4.5:1 contrast. `resources/css/theme-semantic.css` `.btn-sem-primary` §§113–120 instead hardcodes white and semantic primary/hover tokens in both themes; it does not resolve the declared dark foreground or light primary-action background. | **Material declaration mismatch.** Align semantic button default/hover foreground and background with the theme-aware pairs and verify rendered default, hover, and focus states in both themes before UX sign-off. This review did not measure browser contrast. |
| Sample destruction boundary | AD-10 §§119–123 and SPEC CAP-8/constraints §§62, 68 reserve eligibility, execution, lifecycle mutation, witnesses, approvals and audit for the independently authorized sample-disposal workflow; inventory integration is read-only and cannot mutate balances. EXPERIENCE §§31, 33, 67 and Flow 6 §§163–169 maintain a separate surface and preview/confirmation/recheck. | **Aligned.** Keep sample destruction separate from inventory-material disposal; retention/date-anchor confirmation remains governed by the sample owner. |

## Required handoff changes

1. Specify the opening manifest's exact union, lot-status inclusion, active-item classification/null-lot rule, tuple deduplication key, explicit zero and `balance_existed` snapshot, plus stale-manifest invalidation/recount behavior.
2. Expand the opening gate into separate blocking evidence rows for runtime prerequisites, each PostgreSQL guard test/result, policy and grant assignment acceptance, operational rehearsal/recovery/monitoring/rollback, approved baseline, and named Inventory Administrator/system-owner sign-off.
3. Make `.btn-sem-primary` follow DESIGN's light/dark action pairs in default, hover, and focus states; verify rendered contrast before sign-off.

## Conclusion

The material reconciliation gaps are confined to operator-visible baseline manifest/snapshot precision, atomic readiness evidence, and semantic-button implementation alignment. Requested receipt-location policy, duplicate-lot/status contract, grant action with role assignments explicitly left open, selected-tuple cycles after the full opening baseline, and sample destruction ownership are aligned. No application tests, live database checks, browser rendering, or contrast measurements were performed.
