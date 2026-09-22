param(
    [string]$ConfigPath = (Join-Path $PSScriptRoot "config.local.json"),
    [switch]$Once
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$TaskMarker = "AGENT_BRIDGE_TASK_V1"
$ReportMarker = "AGENT_BRIDGE_REPORT_V1"
$StatePath = Join-Path $PSScriptRoot "state.local.json"

function Write-BridgeLog {
    param([string]$Message)
    $stamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Write-Host "[$stamp] $Message"
}

function Load-JsonFile {
    param([string]$Path)
    if (-not (Test-Path $Path)) {
        throw "Required file not found: $Path"
    }
    return Get-Content -Raw -Path $Path | ConvertFrom-Json
}

function Save-State {
    param($State)
    $State | ConvertTo-Json -Depth 10 | Set-Content -Encoding UTF8 -Path $StatePath
}

function Load-State {
    if (Test-Path $StatePath) {
        return Load-JsonFile $StatePath
    }
    return [pscustomobject]@{ processedTaskIds = @() }
}

function Assert-Preflight {
    param($Config)

    if (-not (Test-Path $Config.openCodeCli)) {
        throw "OpenCode CLI not found: $($Config.openCodeCli)"
    }

    $null = Get-Command gh -ErrorAction Stop
    & gh auth status *> $null
    if ($LASTEXITCODE -ne 0) {
        throw "GitHub CLI is not authenticated. Run: gh auth login"
    }

    if (-not (Test-Path $Config.allowedWorktreeRoot)) {
        throw "Allowed worktree root not found: $($Config.allowedWorktreeRoot)"
    }
}

function Get-IssueComments {
    param($Config)

    $endpoint = "repos/$($Config.repo)/issues/$($Config.issue)/comments?per_page=100"
    $raw = & gh api $endpoint
    if ($LASTEXITCODE -ne 0) {
        throw "Unable to read GitHub issue comments."
    }

    if ([string]::IsNullOrWhiteSpace(($raw -join ""))) {
        return @()
    }

    return ($raw -join [Environment]::NewLine) | ConvertFrom-Json
}

function Parse-BridgeTask {
    param([string]$Body)

    $pattern = '(?s)<!--\s*AGENT_BRIDGE_TASK_V1\s*(\{.*?\})\s*-->'
    $match = [regex]::Match($Body, $pattern)

    if (-not $match.Success) {
        return $null
    }

    try {
        return $match.Groups[1].Value | ConvertFrom-Json
    }
    catch {
        return $null
    }
}

function Get-NextTask {
    param($Config, $State)

    $processed = @($State.processedTaskIds)
    $comments = @(Get-IssueComments $Config)

    foreach ($comment in ($comments | Sort-Object id)) {
        $task = Parse-BridgeTask ([string]$comment.body)
        if ($null -eq $task) { continue }
        if ($task.status -ne "READY") { continue }
        if ($processed -contains [string]$task.task_id) { continue }
        return $task
    }

    return $null
}

function Assert-TaskSafe {
    param($Task, $Config)

    $required = @("task_id", "status", "risk", "worktree", "branch", "prompt")
    foreach ($field in $required) {
        if (-not $Task.PSObject.Properties.Name.Contains($field) -or
            [string]::IsNullOrWhiteSpace([string]$Task.$field)) {
            throw "Task is missing required field: $field"
        }
    }

    if ($Task.risk -eq "RED") {
        throw "RED tasks are never executed by the bridge. Human approval is required."
    }

    if ($Task.risk -notin @("GREEN", "YELLOW")) {
        throw "Unsupported risk level: $($Task.risk)"
    }

    $root = [IO.Path]::GetFullPath([string]$Config.allowedWorktreeRoot).TrimEnd('\', '/')
    $worktree = [IO.Path]::GetFullPath([string]$Task.worktree).TrimEnd('\', '/')

    if (-not $worktree.StartsWith($root + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
        throw "Task worktree is outside allowed root: $worktree"
    }

    if (-not (Test-Path $worktree)) {
        throw "Task worktree does not exist: $worktree"
    }

    $currentBranch = (& git -C $worktree branch --show-current).Trim()
    if ($LASTEXITCODE -ne 0) {
        throw "Unable to determine branch for worktree: $worktree"
    }

    if ($currentBranch -ne [string]$Task.branch) {
        throw "Branch mismatch. Expected '$($Task.branch)', actual '$currentBranch'."
    }

    if ($currentBranch -in @("main", "master")) {
        throw "Bridge refuses to work directly on main/master."
    }

    $allowDirty = $false
    if ($Task.PSObject.Properties.Name.Contains("allow_dirty")) {
        $allowDirty = [bool]$Task.allow_dirty
    }

    $status = @(& git -C $worktree status --short)
    if (($status.Count -gt 0) -and (-not $allowDirty)) {
        throw "Worktree is dirty but task does not explicitly allow dirty state."
    }
}

function Post-IssueComment {
    param($Config, [string]$Body)

    $temp = [IO.Path]::GetTempFileName()
    try {
        @{ body = $Body } | ConvertTo-Json -Depth 5 | Set-Content -Encoding UTF8 -Path $temp
        & gh api --method POST "repos/$($Config.repo)/issues/$($Config.issue)/comments" --input $temp *> $null
        if ($LASTEXITCODE -ne 0) {
            throw "Failed to post bridge report to GitHub."
        }
    }
    finally {
        Remove-Item -Force -ErrorAction SilentlyContinue $temp
    }
}

function New-ReportBody {
    param(
        $Task,
        [string]$Status,
        [string]$OpenCodeOutput,
        [string]$GitStatus,
        [string]$DiffStat,
        [string]$DiffCheck,
        [bool]$TimedOut
    )

    $safeOutput = $OpenCodeOutput
    if ($safeOutput.Length -gt 30000) {
        $safeOutput = "... output truncated by Agent Bridge ..." + [Environment]::NewLine + $safeOutput.Substring($safeOutput.Length - 30000)
    }

    $metadata = [ordered]@{
        task_id = [string]$Task.task_id
        status = $Status
        risk = [string]$Task.risk
        branch = [string]$Task.branch
        timed_out = $TimedOut
        completed_at = (Get-Date).ToUniversalTime().ToString("o")
    } | ConvertTo-Json -Compress

    return @"
<!-- $ReportMarker
$metadata
-->
## OpenCode report
~~~text
$safeOutput
~~~

## Evidence
### git status --short
~~~text
$GitStatus
~~~

### git diff --stat
~~~text
$DiffStat
~~~

### git diff --check
~~~text
$DiffCheck
~~~
"@
}

function Invoke-OpenCodeTask {
    param($Task, $Config)

    $worktree = [string]$Task.worktree

    $wrappedPrompt = @"
AGENT BRIDGE V1 APPROVED TASK

Task ID: $($Task.task_id)
Declared risk: $($Task.risk)
Branch: $($Task.branch)

Follow the bridge-worker rules exactly.
Do not commit, stage, push, switch branches, install dependencies, or make architecture decisions.

APPROVED TASK:
$($Task.prompt)

At the end return the required structured report.
"@

    $agent = [string]$Config.agent
    $model = [string]$Config.model
    $cli = [string]$Config.openCodeCli

    Write-BridgeLog "Running task '$($Task.task_id)' in $worktree"

    $job = Start-Job -ScriptBlock {
        param($WorkingDirectory, $Cli, $Agent, $Model, $Prompt)

        Set-Location $WorkingDirectory

        if ([string]::IsNullOrWhiteSpace($Model)) {
            & $Cli run --agent $Agent $Prompt 2>&1
        }
        else {
            & $Cli run --agent $Agent --model $Model $Prompt 2>&1
        }

        exit $LASTEXITCODE
    } -ArgumentList $worktree, $cli, $agent, $model, $wrappedPrompt

    $timeoutSeconds = [int]$Config.maxTaskMinutes * 60
    $completed = Wait-Job -Job $job -Timeout $timeoutSeconds
    $timedOut = $null -eq $completed

    if ($timedOut) {
        Stop-Job -Job $job -ErrorAction SilentlyContinue
    }

    $output = @(Receive-Job -Job $job -ErrorAction SilentlyContinue) | Out-String
    Remove-Job -Job $job -Force -ErrorAction SilentlyContinue

    $gitStatus = @(& git -C $worktree status --short 2>&1) | Out-String
    $diffStat = @(& git -C $worktree diff --stat 2>&1) | Out-String
    $diffCheck = @(& git -C $worktree diff --check 2>&1) | Out-String

    $status = "REPORT"

    if ($timedOut) {
        $status = "BLOCKED"
        $output += [Environment]::NewLine + "Agent Bridge timeout after $($Config.maxTaskMinutes) minutes."
    }
    elseif ($output -match '(?im)^STATUS:\s*NEEDS_APPROVAL\b' -or
            $output -match '(?im)^RISK:\s*RED\b') {
        $status = "NEEDS_APPROVAL"
    }
    elseif ($Task.risk -eq "YELLOW" -or
            $output -match '(?im)^STATUS:\s*NEEDS_REVIEW\b') {
        $status = "NEEDS_REVIEW"
    }

    return [pscustomobject]@{
        Status = $status
        Output = $output.Trim()
        GitStatus = $gitStatus.Trim()
        DiffStat = $diffStat.Trim()
        DiffCheck = $diffCheck.Trim()
        TimedOut = $timedOut
    }
}

function Mark-Processed {
    param($State, [string]$TaskId)

    $items = @($State.processedTaskIds)
    if ($items -notcontains $TaskId) {
        $items += $TaskId
    }

    $State.processedTaskIds = $items
    Save-State $State
}

$config = Load-JsonFile $ConfigPath
Assert-Preflight $config
$state = Load-State

Write-BridgeLog "Agent Bridge v1 started for $($config.repo) issue #$($config.issue)."

do {
    try {
        $task = Get-NextTask $config $state

        if ($null -eq $task) {
            if ($Once) {
                Write-BridgeLog "No READY task found."
                break
            }

            Start-Sleep -Seconds ([int]$config.pollSeconds)
            continue
        }

        Write-BridgeLog "Found task '$($task.task_id)' risk=$($task.risk)."

        try {
            Assert-TaskSafe $task $config
        }
        catch {
            $message = $_.Exception.Message
            $blockedMetadata = [ordered]@{
                task_id = [string]$task.task_id
                status = "BLOCKED"
                risk = [string]$task.risk
            } | ConvertTo-Json -Compress

            $blockedBody = @"
<!-- $ReportMarker
$blockedMetadata
-->
Bridge refused to execute this task:

~~~text
$message
~~~
"@
            Post-IssueComment $config $blockedBody
            Mark-Processed $state ([string]$task.task_id)
            continue
        }

        $result = Invoke-OpenCodeTask $task $config
        $reportBody = New-ReportBody $task $result.Status $result.Output $result.GitStatus $result.DiffStat $result.DiffCheck $result.TimedOut

        Post-IssueComment $config $reportBody
        Mark-Processed $state ([string]$task.task_id)

        Write-BridgeLog "Task '$($task.task_id)' finished with status $($result.Status)."

        if ($Once) {
            break
        }
    }
    catch {
        Write-BridgeLog "Bridge error: $($_.Exception.Message)"

        if ($Once) {
            throw
        }

        Start-Sleep -Seconds ([int]$config.pollSeconds)
    }
}
while ($true)
