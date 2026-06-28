$ErrorActionPreference = "Stop"
$base = "http://127.0.0.1:8000/api"
$headers = @{ "Accept" = "application/json"; "Content-Type" = "application/json" }

function Show($label, $resp) {
  $body = $resp.Content
  if ($body.Length -gt 700) { $body = $body.Substring(0,700) }
  Write-Host "=== $label -> $($resp.StatusCode) ==="
  Write-Host $body
  Write-Host ""
}

$loginBody = @{ email = "admin@admin.com"; password = "12345678"; device_name = "local-test" } | ConvertTo-Json
try {
  $login = Invoke-WebRequest -Uri "$base/login" -Method Post -Headers $headers -Body $loginBody -UseBasicParsing -TimeoutSec 15
  $json = $login.Content | ConvertFrom-Json
  $token = $json.data.token
  Write-Host "LOGIN OK - role=$($json.data.user.role) token=$($token.Substring(0,12))..."
  Write-Host ""
} catch {
  Write-Host "LOGIN FAILED: HTTP $($_.Exception.Response.StatusCode.value__)"
  $sr = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
  Write-Host $sr.ReadToEnd()
  exit
}

$auth = @{ "Accept" = "application/json"; "Authorization" = "Bearer $token" }

foreach ($path in @("/dashboard", "/dashboard/recent-vehicles", "/devices", "/fleet/live", "/alerts/unread", "/geofences")) {
  try {
    $r = Invoke-WebRequest -Uri "$base$path" -Headers $auth -UseBasicParsing -TimeoutSec 20
    Show $path $r
  } catch {
    $code = $_.Exception.Response.StatusCode.value__
    Write-Host "=== $path -> HTTP $code ==="
    try { $sr = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream()); $t = $sr.ReadToEnd(); Write-Host $t.Substring(0,[Math]::Min(700,$t.Length)) } catch {}
    Write-Host ""
  }
}
