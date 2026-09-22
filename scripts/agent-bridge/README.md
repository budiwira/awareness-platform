# Agent Bridge v1

Agent Bridge v1 connects GitHub issue tasks to a local OpenCode worker without exposing the local machine to the internet.

## Architecture

GitHub Issue #67
→ local Windows bridge
→ OpenCode CLI
→ MiMo-V2.5 Free
→ local feature worktree
→ structured report back to GitHub

ChatGPT reviews the GitHub report/diff and posts the next approved task.

## Safety Model

The bridge refuses to:

- run on main/master
- run outside the configured worktree root
- execute RED tasks
- use a dirty worktree unless the task explicitly sets allow_dirty=true

The OpenCode bridge-worker agent additionally denies:

- git add/commit/push/switch/reset/restore/clean/merge/rebase
- dependency installation
- migrations/destructive DB commands
- external-directory access
- web access
- subagents

Only a small validation command allowlist is available.

## Decision Gates

GREEN:
approved implementation inside an existing contract.

YELLOW:
implementation may touch service/policy/lifecycle details but remains inside approved architecture. The report returns NEEDS_REVIEW.

RED:
schema/migration decisions, authorization architecture, tenancy/RLS, roles/business rules, dependencies/providers, destructive DB actions, security trade-offs, or scope changes. The bridge does not execute RED tasks.

For RED, ChatGPT provides a recommendation and the user replies from mobile with APPROVE A, APPROVE B, or STOP. A new approved GREEN/YELLOW task is then posted.

## One-Time Local Setup

Use a dedicated bridge worktree so bridge infrastructure never interferes with feature worktrees.

From the main repository:

    cd C:\Users\budii\awareness-lab
    git fetch origin
    git worktree add "C:\Users\budii\awareness-lab.worktrees\agent-bridge-v1" chore/agent-bridge-v1

Then:

    cd "C:\Users\budii\awareness-lab.worktrees\agent-bridge-v1"

Verify GitHub CLI:

    gh --version
    gh auth status

If gh is installed but not authenticated:

    gh auth login

The bridge never installs GitHub CLI automatically.

Run the installer:

    powershell -ExecutionPolicy Bypass -File .\scripts\agent-bridge\install.ps1

Review:

    scripts\agent-bridge\config.local.json

The local config and state are gitignored.

## Dry Run

With no READY task present:

    powershell -ExecutionPolicy Bypass -File .\scripts\agent-bridge\bridge.ps1 -Once

Expected result:

    Agent Bridge v1 started...
    No READY task found.

## Continuous Run

    powershell -ExecutionPolicy Bypass -File .\scripts\agent-bridge\bridge.ps1

Stop safely with Ctrl+C.

Later this can be wrapped in Windows Task Scheduler after the manual flow is proven.

## Task Comment Format

ChatGPT posts a GitHub issue comment containing:

    <!-- AGENT_BRIDGE_TASK_V1
    {
      "task_id": "TTX-D3-B2-001",
      "status": "READY",
      "risk": "GREEN",
      "worktree": "C:\\Users\\budii\\awareness-lab.worktrees\\ttx2-d-facilitator-console",
      "branch": "agents/ttx-facilitator-console",
      "allow_dirty": false,
      "prompt": "Run the approved task..."
    }
    -->

The bridge executes each task_id once. It accepts task comments only from GitHub logins listed in `trustedTaskAuthors` in the local config, and it also skips task IDs that already have a bridge report on GitHub.

## Report Format

The bridge posts:

    <!-- AGENT_BRIDGE_REPORT_V1
    {"task_id":"...","status":"REPORT","risk":"GREEN",...}
    -->

and includes:

- OpenCode final output
- git status --short
- git diff --stat
- git diff --check
- actual unstaged `git diff`
- staged diff evidence
- bounded contents for untracked text files
- HEAD before/after execution

The bridge itself never commits or merges feature work.


## Watchdog v1.1

The bridge protects against stuck or looping workers:

- default hard timeout: 30 minutes
- trusted task may request `timeout_minutes`, capped by `maxTaskMinutesCap` (default 45)
- no worker output for 10 minutes: BLOCKED
- identical diagnostic failure output repeated 5 times: BLOCKED; source/diff/command-echo lines are ignored to avoid false positives from identifiers such as `accessDeniedError`
- BLOCKED reports include the worker output tail plus git evidence
- the continuous runner stops after BLOCKED / NEEDS_REVIEW / NEEDS_APPROVAL
- watchdog never auto-switches to Codex

Escalation remains review-gated: focused MiMo correction, then free fallback if needed, then Codex only for justified difficult/security-critical work.

## Worker Routing

Default:
MiMo-V2.5 Free.

Fallback:
Nemotron free model only after an explicit new task changes the selected worker/model.

Codex:
escalation outside this local bridge when repeated focused attempts fail, difficult concurrency/race conditions remain, a high-risk multi-file refactor is required, or a security-critical second review is justified.

## Security Notes

- No local OpenCode port is exposed publicly.
- GitHub is the only shared control plane.
- No secrets belong in issue comments or reports.
- Only trusted GitHub task authors may enqueue executable tasks.
- The bridge uses the user's existing GitHub CLI authentication.
- The bridge does not install packages or providers.
- Keep laptop disk encryption, Windows login, and GitHub account MFA enabled.
