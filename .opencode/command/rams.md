---
description: "Review UI files for accessibility + visual polish (Rams)"
---

When invoked, perform a Rams review:

- Use rules from `RAMS_UI_GUIDELINES.md` and `.opencode/skills/rams/SKILL.md`.
- If a file path is provided, read it and output:
    - violations (quote exact line/snippet)
    - why it matters (1 short sentence)
    - a concrete fix (code-level suggestion)
- Group findings by severity: CRITICAL, SERIOUS, MODERATE.
- If no file is provided, apply the same checks to any UI changes in the conversation.
