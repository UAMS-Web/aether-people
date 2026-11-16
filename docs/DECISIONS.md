# Decision log

The canonical record of **why** this repository is the way it is: the reasoning and the **rejected alternatives** behind decisions that have closed. It is the guard against silently relitigating a settled decision.

**Consult it before acting.** Before proposing a change, reopening a question, or picking an approach, check here first: the path under consideration may already be a decided-and-recorded one, or an explicitly rejected one. If it is, follow the record (or make the case to revisit it); do not quietly re-decide.

**When a seam closes, record the verdict in both places.** Capture the *what*, *why* and rejected alternatives **here** (the cross-ticket index) **and** in the GitHub issue's `## Decision` section (the per-ticket record), keeping the two in sync. This file does not replace the issue tracker, which stays the system of record for a single ticket's status.

See the [`design-decision-forks`](../.claude/rules/design-decision-forks.md) rule for how a fork is surfaced and driven to resolution before its verdict lands here.

## How to add an entry

Oldest first, one entry per closed decision, so **a new entry is appended to the end of the file**. Keep it terse; the issue carries the depth. Each entry is preceded by exactly one blank line, which `scripts/check-decision-log-order.mjs` enforces along with the date order. The file is marked `merge=union` in `.gitattributes`, so two branches that each append an entry merge without a conflict.

```markdown
## YYYY-MM-DD: <short decision title> (<#issue or area>)

**Decision.** <what was decided, in a sentence or two>

**Why.** <the reasoning that settled it>

**Rejected.** <the alternatives considered, and why each lost>

**Companion.** <link to the GitHub issue holding the full reasoning>
```

---

## 2026-10-31: Started from the add-on template (repository setup)

**Decision.** This repository started from `UAMS-Web/statamic-addon-template@197c764`, the template's `main` tip when it was created. It carries no synced files (`.agents/`, `.claude/`, `.cursor/`) and no `LICENSE`, and `composer.json` has no `license` field.

**Why.** The shared rules and skills reach a repository by onboarding it to the sync in `UAMS-Web/uams-claude-skills`, not by copying a snapshot that goes stale. The license waits on the general decision from UAMS legal in `UAMS-Web/uams-statamic#261`.

**Rejected.** Snapshotting the synced files into the template: every new add-on would begin with a stale copy that conflicts with its first sync pull request. Copying a license from an existing add-on: legal has not decided which license applies.

**Companion.** `UAMS-Web/uams-statamic#261`.

## 2026-11-12: Publish to Packagist on the MIT license (#39)

**Decision.** The add-on is listed on Packagist under the MIT license, with a `LICENSE` file reading `Copyright (c) 2026 University of Arkansas for Medical Sciences` and `"license": "MIT"` in `composer.json`, ahead of the general decision from UAMS legal.

**Why.** The package is already on Packagist and tagged, and a public listing needs a declared license. The choice follows `UAMS-Web/aether-redirects`. `UAMS-Web/uams-statamic#261` is open and no longer gates it.

**Rejected.** Waiting for the legal decision: it left the listing without a license, which the checklist treats as a prerequisite. A different license: MIT is the recorded choice for the sibling add-on, and diverging would need its own decision.

**Companion.** `UAMS-Web/aether-people#39`.

## 2026-11-16: Write merge redirects through a host-bound contract that the preview and the commit share (#98)

**Decision.** The people merge writes redirects through a contract the host binds, in the way the reference repointer is bound. The dry-run preview reads the same binding the commit uses, so it reports the count that would be written. A host with no binding is refused with a message that names the missing binding, and a host that wants no redirects binds an explicit no-op.

**Why.** The ported behavior lets the preview report the planned redirects without looking at the `redirects` global, while a commit with no global logs a warning, deletes the losing entries and reports no redirects. The operator sees a count that the commit does not honor, and the retired URLs return 404. A binding makes the dependency explicit, the preview and the commit then agree, and an unbound host fails before anything is deleted.

**Rejected.** Refusing a merge when the global is missing, with no contract: it fails closed but leaves a host that wants no redirects with no supported way to say so. Keeping the log-and-continue path and making only the preview report zero: the smallest change, but the operator can still commit and lose the URLs.

**Companion.** `UAMS-Web/aether-people#98`. The pull request that moved the merge into the add-on (`UAMS-Web/aether-people#99`) ports the site's behavior unchanged and is not affected.
