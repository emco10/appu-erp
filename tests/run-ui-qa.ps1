$ErrorActionPreference='Stop'
$nodeCommand=Get-Command node -ErrorAction SilentlyContinue
$nodeBinary=if($nodeCommand){$nodeCommand.Source}else{Join-Path $env:USERPROFILE '.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe'}
if(-not(Test-Path -LiteralPath $nodeBinary)){throw 'Instala Node.js o agrega node al PATH para ejecutar las pruebas de interfaz.'}
$previousNodePath=$env:NODE_PATH
try{
    if(-not(Test-Path -LiteralPath (Join-Path $PSScriptRoot 'node_modules\jsdom\package.json'))){
        $temporaryModules=Join-Path $env:TEMP 'appu-erp-qa-node\node_modules'
        if(Test-Path -LiteralPath (Join-Path $temporaryModules 'jsdom\package.json')){$env:NODE_PATH=$temporaryModules}
    }
    $testFiles=Get-ChildItem -LiteralPath $PSScriptRoot -Filter '*.test.cjs' | ForEach-Object FullName
    & $nodeBinary --test --test-concurrency=1 $testFiles
    if($LASTEXITCODE -ne 0){throw 'Fallaron las pruebas de interfaz.'}
}
finally{$env:NODE_PATH=$previousNodePath}
