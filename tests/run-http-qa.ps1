param([switch]$RealSession)
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$php = 'C:\xampp\php\php.exe'
$port = if($RealSession){8769}else{8765}
$listener = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue
if ($listener) { throw "El puerto QA $port ya está ocupado; no se detendrá un proceso ajeno." }
$previousDb = $env:APPU_DB_NAME
$previousQa = $env:APPU_DOCUMENT_QA
$previousUrl = $env:APPU_QA_URL
$previousAuth = $env:APPU_QA_SESSION_AUTH
$server = $null
try {
    $env:APPU_DB_NAME = 'db_appu_qa'
    $env:APPU_DOCUMENT_QA = '1'
    $env:APPU_QA_SESSION_AUTH = if($RealSession){'1'}else{''}
    $env:APPU_QA_URL = "http://127.0.0.1:$port"
    $server = Start-Process -FilePath $php -ArgumentList @('-S', "127.0.0.1:$port", 'tests/document-workbench.php') -WorkingDirectory $root -WindowStyle Hidden -PassThru
    $ready = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        Start-Sleep -Milliseconds 200
        if (Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue) { $ready = $true; break }
    }
    if (-not $ready) { throw 'El servidor HTTP QA no inició.' }
    $tests = if($RealSession){@('session-auth-http.php')}else{@('navigation-http.php','masters-navigation-http.php','product-master-http.php','sales-order-http.php','sales-fulfillment-http.php','purchase-goods-receipt-http.php','purchase-process-http.php','inventory-document-http.php','inventory-transfer-http.php','finance-payment-http.php','management-http.php')}
    foreach ($test in $tests) {
        & $php (Join-Path $PSScriptRoot $test)
        if ($LASTEXITCODE -ne 0) { throw "Falló $test" }
    }
    Write-Host 'PASS HTTP QA suite'
}
finally {
    if ($server -and -not $server.HasExited) { Stop-Process -Id $server.Id -Force }
    $env:APPU_DB_NAME = $previousDb
    $env:APPU_DOCUMENT_QA = $previousQa
    $env:APPU_QA_URL = $previousUrl
    $env:APPU_QA_SESSION_AUTH = $previousAuth
}
