# Recapture map-related mobile app screenshots (requires emulator + production login).
param(
    [string]$Adb = "$env:LOCALAPPDATA\Android\Sdk\platform-tools\adb.exe",
    [string]$Apk = "d:\laragon\www\gps_tracker_pro_mobile\build\app\outputs\flutter-apk\app-debug.apk",
    [string]$OutDir = "d:\laragon\www\gps-traccar\public\images\mobile-app",
    [string]$Package = "com.gpstrackerpro.gps_tracker_pro_mobile",
    [string]$MainActivity = "com.gpstrackerpro.gps_tracker_pro_mobile.MainActivity"
)

$ErrorActionPreference = "Stop"
if (-not (Test-Path $Adb)) { throw "adb not found at $Adb" }
New-Item -ItemType Directory -Force -Path $OutDir | Out-Null

function Ensure-Device { & $Adb wait-for-device | Out-Null }

function App-Focused {
    $line = (& $Adb shell dumpsys window 2>$null | Select-String "mCurrentFocus").Line
    return $line -match [regex]::Escape($Package)
}

function Start-App {
    Ensure-Device
    & $Adb shell am force-stop $Package | Out-Null
    Start-Sleep -Seconds 1
    & $Adb shell am start -n "$Package/$MainActivity" | Out-Null
    Start-Sleep -Seconds 12
    if (-not (App-Focused)) { throw "FalconEyeGPS is not in the foreground after launch." }
}

function Tap([int]$x, [int]$y) {
    Ensure-Device
    if (-not (App-Focused)) { Start-App }
    & $Adb shell input tap $x $y | Out-Null
    Start-Sleep -Milliseconds 1000
}

function Key([string]$code) {
    & $Adb shell input keyevent $code | Out-Null
    Start-Sleep -Milliseconds 350
}

function Type-Ascii([string]$text) {
    $escaped = $text -replace ' ', '%s' -replace '@', '%40'
    & $Adb shell input text $escaped | Out-Null
    Start-Sleep -Milliseconds 400
}

function Capture([string]$name) {
    if (-not (App-Focused)) { throw "Lost app focus before capturing $name" }
    Ensure-Device
    Key "KEYCODE_BACK"
    Start-Sleep -Milliseconds 400
    if (-not (App-Focused)) { Start-App }
    $path = Join-Path $OutDir "$name.png"
    $proc = Start-Process -FilePath $Adb -ArgumentList @("exec-out", "screencap", "-p") `
        -RedirectStandardOutput $path -NoNewWindow -Wait -PassThru
    if ($proc.ExitCode -ne 0 -or -not (Test-Path $path) -or ((Get-Item $path).Length -lt 50000)) {
        throw "Screenshot failed or too small for $name"
    }
    Write-Host "Captured $name ($((Get-Item $path).Length) bytes)"
    Start-Sleep -Seconds 2
}

Start-App
if (Test-Path $Apk) { & $Adb install -r $Apk | Out-Null; Start-App }

# Login
Tap 540 900
Key "KEYCODE_CTRL_A"
Key "KEYCODE_DEL"
Type-Ascii "ali@user.com"
Tap 540 1060
Key "KEYCODE_CTRL_A"
Key "KEYCODE_DEL"
Type-Ascii "12345678"
Tap 540 1240
Start-Sleep -Seconds 20
if (-not (App-Focused)) { throw "Login did not reach FalconEyeGPS home screen." }

$navY = 2280
$navHome = 108
$navMap = 324
$navDevices = 540
$navProfile = 972

# Dark theme
Tap $navProfile $navY
Start-Sleep -Seconds 5
Tap 920 520
Start-Sleep -Seconds 2
Key "KEYCODE_BACK"
Start-Sleep -Seconds 2

Tap $navMap $navY
Start-Sleep -Seconds 16
Capture "04-live-tracking-map"

Tap $navDevices $navY
Start-Sleep -Seconds 8
Tap 540 480
Start-Sleep -Seconds 10
Capture "06-vehicle-details"

Tap 280 1180
Start-Sleep -Seconds 16
& $Adb shell input swipe 540 1200 540 800 400 | Out-Null
Start-Sleep -Seconds 2
Capture "07-live-map-detail"

Key "KEYCODE_BACK"
Start-Sleep -Seconds 4
Tap 540 1280
Start-Sleep -Seconds 16
Capture "08-history-playback"

Write-Host "Done. Map screenshots in $OutDir"
