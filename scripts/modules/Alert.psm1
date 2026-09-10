<#
.SYNOPSIS
    Alerting backup dengan fallback chain: file -> event log -> toast
.DESCRIPTION
    Channel 1: ALERTS.log (selalu, tanpa privilege)
    Channel 2: Windows Event Log (best-effort, butuh source terdaftar)
    Channel 3: Toast notification (hanya failure, tanpa privilege)
#>

function Write-BackupEvent {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory)] [ValidateSet("Success", "Failure")] [string]$Status,
        [Parameter(Mandatory)] [string]$Message,
        [int]$ExitCode = 0,
        [string]$AlertFile = "C:\backups\awareness\ALERTS.log"
    )

    $ts = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $line = "[$ts] $Status : $Message"

    # --- Channel 1: alert file (SELALU) ---
    try {
        Add-Content -Path $AlertFile -Value $line
    } catch {
        Write-Host "ALERT FILE GAGAL: $($_.Exception.Message)"
    }

    # --- Channel 2: event log (best-effort, silent) ---
    try {
        if ([System.Diagnostics.EventLog]::SourceExists("AwarenessPlatformBackup")) {
            $type = if ($Status -eq "Success") { [System.Diagnostics.EventLogEntryType]::Information } else { [System.Diagnostics.EventLogEntryType]::Error }
            $id = if ($Status -eq "Success") { 1000 } else { 1001 }
            [System.Diagnostics.EventLog]::WriteEntry("AwarenessPlatformBackup", $Message, $type, $id)
        }
    } catch { }

    # --- Channel 3: toast (failure saja) ---
    if ($Status -eq "Failure") {
        try {
            [Windows.UI.Notifications.ToastNotificationManager, Windows.UI.Notifications, ContentType = WindowsRuntime] | Out-Null
            $tpl = [Windows.UI.Notifications.ToastNotificationManager]::GetTemplateContent([Windows.UI.Notifications.ToastTemplateType]::ToastText02)
            $nodes = $tpl.GetElementsByTagName("text")
            $nodes.Item(0).AppendChild($tpl.CreateTextNode("AWARENESS BACKUP GAGAL")) | Out-Null
            $msg = if ($Message.Length -gt 200) { $Message.Substring(0, 200) + "..." } else { $Message }
            $nodes.Item(1).AppendChild($tpl.CreateTextNode($msg)) | Out-Null
            $toast = [Windows.UI.Notifications.ToastNotification]::new($tpl)
            [Windows.UI.Notifications.ToastNotificationManager]::CreateToastNotifier("powershell.exe").Show($toast)
        } catch { }
    }

    $icon = if ($Status -eq "Success") { "OK   " } else { "ALERT" }
    Write-Host "[$icon] $line"
}

Export-ModuleMember -Function Write-BackupEvent