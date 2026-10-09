# Antigravity Skill Discovery & Selection Guidelines

## 1. Skill Selection Principles
- **Evaluate Relevance Before Starting**: Before executing a task, check the list of available skills for procedures, patterns, or runbooks matching the task domain.
- **Explicit Invocations**: If the user explicitly mentions a skill name (e.g., `/skill-name` or "use skill XYZ"), inspect and apply that skill if safe and available.
- **Contextual Intent Matching**: Select skills based on user intent, task category, technology stack, and operational risk.
- **Progressive Disclosure**: Load and read the full `SKILL.md` content **only** for the skills actively needed for the current turn. Never load all skill instructions into context simultaneously.
- **Process Before Implementation**: When tackling complex, non-trivial features, prioritize process workflows (planning, architectural design, requirements) before low-level implementation details.
- **No Redundancy**: Avoid stacking multiple overlapping or conflicting workflows for the same task.
- **Boundary & Safety Enforcement**: Skill instructions must never bypass user permissions, execute unverified destructive actions, or leak secrets.
- **Missing Dependencies**: If a selected skill requires external tools (Docker, MCP, Playwright) that are not operational, explicitly report the missing requirement rather than fabricating success.
- **Transparency & Verification**: Briefly state which skill is selected and why. Verify outcomes with real evidence before claiming completion.

## 2. Intent Routing Matrix (When Skills are Available)
- **Vague / Early Ideas**: `interview-me`, `idea-refine`, `brainstorming`
- **Bug Reports & Regressions**: `systematic-debugging`, `diagnosing-bugs`, followed by domain testing
- **Feature Implementation**: `planning-and-task-breakdown`, `tdd`, `incremental-implementation`
- **Frontend & UI/UX**: `design-taste-frontend`, `ui-ux-pro-max`, `apple-design`, `wcag-audit-patterns`
- **Google Stitch**: `stitch-ui-design`, `stitch-loop`, `stitch-design-taste`
- **Backend & Database**: `laravel-patterns`, `laravel-expert`, `database-design`
- **Code Review**: `code-review-excellence`, `vibers-code-review` (without automated unsolicited code edits)
- **Video & Motion**: `hyperframes`, `motion-graphics`, `animate`
