# One-shot FTP deploy for NCST site -> httpdocs/
# Reads FTP_* from .env; never prints secrets.
# Usage: powershell -File scripts/deploy-ftp.ps1

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

function Get-DotEnv([string]$path) {
  $map = @{}
  if (-not (Test-Path $path)) { throw ".env not found at $path" }
  foreach ($line in Get-Content $path) {
    $t = $line.Trim()
    if ($t -eq '' -or $t.StartsWith('#')) { continue }
    $i = $t.IndexOf('=')
    if ($i -lt 1) { continue }
    $k = $t.Substring(0, $i).Trim()
    $v = $t.Substring($i + 1).Trim()
    if (($v.StartsWith('"') -and $v.EndsWith('"')) -or ($v.StartsWith("'") -and $v.EndsWith("'"))) {
      $v = $v.Substring(1, $v.Length - 2)
    }
    $map[$k] = $v
  }
  return $map
}

$envMap = Get-DotEnv (Join-Path $root '.env')
$hostName = [string]$envMap['FTP_ADDRESS']
$port = if ($envMap['FTP_PORT']) { [int]$envMap['FTP_PORT'] } else { 21 }
$user = [string]$envMap['FTP_USER']
$pass = [string]$envMap['FTP_PASSWORD']

if ([string]::IsNullOrWhiteSpace($hostName) -or [string]::IsNullOrWhiteSpace($user) -or [string]::IsNullOrWhiteSpace($pass)) {
  throw 'FTP_ADDRESS / FTP_USER / FTP_PASSWORD must be set in .env'
}

# App lives under httpdocs/ on this host. Never upload into sibling folders (2/, advertising/, bid/, dev/).
$remoteRoot = 'httpdocs'

Write-Host "FTP host: $hostName"
Write-Host "FTP port: $port"
Write-Host "FTP user: $user"
Write-Host "Remote root: $remoteRoot"
Write-Host "Password: (set, not shown)"

$files = @(
  'admin/settings/facebook/auto-post.php',
  'admin/settings/facebook/cron.php',
  'cron/facebook_sync.php',
  'includes/facebook.php',
  'includes/partials/admin_shell_start.php',
  'sql/apply_facebook_auto_post.php',
  'sql/apply_facebook_comments.php',
  'sql/migrate_facebook_auto_post.sql',
  'sql/migrate_facebook_comments.sql',
  'sql/schema.sql',
  '.env.example',
  'README.md'
)

$dirs = @(
  'admin',
  'admin/settings',
  'admin/settings/facebook',
  'cron',
  'includes',
  'includes/partials',
  'sql'
)

function Invoke-FtpCommand([string]$method, [string]$remotePath, [byte[]]$bytes = $null) {
  $uri = "ftp://${hostName}:${port}/${remotePath}".Replace('\', '/')
  $req = [System.Net.FtpWebRequest]::Create($uri)
  $req.Credentials = New-Object System.Net.NetworkCredential($user, $pass)
  $req.Method = $method
  $req.UseBinary = $true
  $req.UsePassive = $true
  $req.KeepAlive = $false
  if ($null -ne $bytes) {
    $req.ContentLength = $bytes.Length
    $stream = $req.GetRequestStream()
    $stream.Write($bytes, 0, $bytes.Length)
    $stream.Close()
  }
  try {
    $resp = $req.GetResponse()
    $status = $resp.StatusDescription
    $resp.Close()
    return @{ ok = $true; status = $status }
  } catch {
    return @{ ok = $false; status = $_.Exception.Message }
  }
}

foreach ($d in $dirs) {
  $remote = "$remoteRoot/$d"
  $r = Invoke-FtpCommand ([System.Net.WebRequestMethods+Ftp]::MakeDirectory) $remote
  Write-Host ("mkdir {0}: {1}" -f $remote, $(if ($r.ok) { 'ok' } else { 'exists/skip' }))
}

foreach ($rel in $files) {
  $local = Join-Path $root $rel
  if (-not (Test-Path $local)) { throw "Missing local file: $rel" }
  $remote = "$remoteRoot/$($rel.Replace('\', '/'))"
  $bytes = [System.IO.File]::ReadAllBytes($local)
  $r = Invoke-FtpCommand ([System.Net.WebRequestMethods+Ftp]::UploadFile) $remote $bytes
  if (-not $r.ok) { throw "Upload failed for ${remote}: $($r.status)" }
  Write-Host ("uploaded {0} ({1} bytes)" -f $remote, $bytes.Length)
}

Write-Host 'Deploy complete.'
Write-Host 'If needed, run on production: php sql/apply_facebook_auto_post.php'
Write-Host '(Schema is also auto-ensured on cron/auto-post admin load.)'
