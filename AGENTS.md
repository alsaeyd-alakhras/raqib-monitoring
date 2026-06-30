# Aid Registry – Agent Guidance

## Subagent Usage

When the user asks for **broad exploration**, **multi-step tasks**, or **shell/git operations**, prefer launching a subagent instead of executing directly.

### Quick Decision

| User Request | Action |
|--------------|--------|
| "استكشف المشروع" / "explore project structure" | `mcp_task` with `explore` |
| "ابحث عن..." / "find all usages of X" | `mcp_task` with `explore` |
| "نفّذ..." / "run php artisan..." / "git status" | `mcp_task` with `shell` |
| "مراجعة شاملة" / "multi-step analysis" | `mcp_task` with `generalPurpose` |
| Edit one file, fix one bug, answer one question | Direct execution (no subagent) |

### Subagent Types

- **explore**: Project structure, routes, models, search across files. Use `thoroughness: medium` for balanced results.
- **generalPurpose**: Complex tasks spanning many files, research + execution.
- **shell**: Git, artisan, composer, npm, tests.

### Reference

- Skill: `.cursor/skills/aid-registry-subagents/`
- Prompts: `.cursor/skills/aid-registry-subagents/prompts.md`
