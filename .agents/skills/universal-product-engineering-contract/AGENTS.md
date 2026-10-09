# universal-product-engineering-contract

Cross-agent entry point for the Universal Product & Engineering Contract (UPEC) skill.
The full procedure is in [SKILL.md](./SKILL.md).

Use this skill when the user asks for a new feature, behavior change, architecture
decision, domain design, external integration, or asks for DoD / recap / ATM analysis —
or says "pakai UPEC" / "ikuti kontrak produk" / `/upec`.

Do not use it for one-sentence factual questions, typo fixes, pure shell commands,
or pure visual exploration (those belong to `prototype`, `impeccable`, `vercel-optimize`,
etc. — UPEC orchestrates them as sub-phases, see SKILL §2.4).

## Procedure

1. Read [SKILL.md](./SKILL.md).
2. Identify the current workflow phase (§3); start at Phase 0 Intake if ambiguous.
3. Run the mandatory protocols (§2): at minimum Research + Cross-check + Decision Log.
4. Fill the phase's minimum artifacts (templates in `references/`).
5. Close with DoD (§7.1) and Recap (§7.2) for work spanning more than one edit.

The hard rules are: evidence before synthesis, measurable by default,
human-in-control, no slop, and narrow scope with explicit edge states.
When another skill conflicts with UPEC, UPEC wins on standards of evidence and DoD;
the specialist skill wins on craft detail in its own domain.

## Install

Manual project install:

```bash
mkdir -p .agents/skills .claude/skills
cp -R <source>/universal-product-engineering-contract .agents/skills/
cp -R <source>/universal-product-engineering-contract .claude/skills/
```

Then add this to the project `AGENTS.md`:

```md
When building features or making product/engineering decisions, follow
`.agents/skills/universal-product-engineering-contract/SKILL.md` before proposing changes.
Evidence before synthesis; no phase advance without its exit gate.
```
