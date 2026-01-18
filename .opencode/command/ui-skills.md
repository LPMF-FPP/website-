---
description: "Enforce UI-Skills constraints for UI implementation"
---

When invoked, apply UI-Skills constraints:

- Use rules from `RAMS_UI_GUIDELINES.md` and `.opencode/skills/ui-skills/SKILL.md`.
- If a file path is provided, review it and output:
    - violations (quote exact line/snippet)
    - why it matters (1 short sentence)
    - a concrete fix (code-level suggestion)
- If no file is provided, enforce the rules on any UI changes in the conversation.
