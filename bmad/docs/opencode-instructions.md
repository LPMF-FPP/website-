# BMAD Method - OpenCode Instructions

## Activating Agents

In this repository, BMAD is exposed through three OpenCode surfaces:

1. Installed agent files in `.opencode/agent/` such as `bmad-agent-bmm-dev.md`
2. Installed workflow/task command files in `.opencode/command/` such as `bmad-workflow-bmm-dev-story.md`
3. Skill shortcut commands in `.opencode/commands/` such as `bmad-help.md` or `bmad-agent-dev.md`

For this repo, treat `bmad/` as the active BMAD install. `_bmad/` still exists for legacy or compatibility-oriented skills, but it is not the primary OpenCode command surface.

### How to Use

1. **Switch Agents**: Press **Tab** to cycle through primary agents or select using the `/agents`
2. **Activate Agent**: Once the Agent is selected say `hello` or any prompt to activate that agent persona
3. **Execute Commands**: Type `/` and use fuzzy matching for commands such as `/bmad-help`, `/bmad-agent-dev`, or `/bmad-workflow-bmm-dev-story`

### Examples

```
/agents - to see a list of agents and switch between them
/bmad-help - ask BMAD what to run next
/bmad-agent-dev - activate the developer-facing BMAD skill shortcut
/bmad-workflow-bmm-workflow-init - run the workflow-init command directly
```

### Notes

- Press **Tab** to switch between primary agents (Analyst, Architect, Dev, etc.)
- Commands are autocompleted when you type `/` and allow for fuzzy matching
- If you are unsure which BMAD route to use, start with `/bmad-help`
- Workflow commands execute in current agent context, so confirm whether you want a direct workflow command, an installed agent file, or a skill shortcut before running it
