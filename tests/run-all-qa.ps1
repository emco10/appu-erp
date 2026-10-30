$ErrorActionPreference='Stop'
$root=Split-Path -Parent $PSScriptRoot
$previousDb=$env:APPU_DB_NAME
Push-Location $root
try{
    $env:APPU_DB_NAME='db_appu_qa'
    & 'C:\xampp\php\php.exe' (Join-Path $PSScriptRoot 'schema-readiness.php')
    if($LASTEXITCODE -ne 0){throw 'El esquema QA no está actualizado.'}
    & cmd.exe /d /c 'tests\run-smoke-qa.cmd'
    if($LASTEXITCODE -ne 0){throw 'Falló la suite de dominio y renderizado.'}
    & (Join-Path $PSScriptRoot 'run-ui-qa.ps1')
    & (Join-Path $PSScriptRoot 'run-http-qa.ps1')
    & (Join-Path $PSScriptRoot 'run-http-qa.ps1') -RealSession
    Write-Host 'PASS complete ERP QA regression'
}
finally{$env:APPU_DB_NAME=$previousDb;Pop-Location}
