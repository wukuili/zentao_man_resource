# 打包 crmsync / taizhang / zoucha 插件为可分发 zip（版本号取自各自 plugin.json）
# 用法: pwsh ./pack_plugins.ps1   产物输出到 dist/
$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$dist = Join-Path $root 'dist'
New-Item -ItemType Directory -Force $dist | Out-Null

foreach ($dir in 'zentao_crm', 'zentao_taizhang', 'zentao_zoucha') {
    $src  = Join-Path $root $dir
    $meta = Get-Content (Join-Path $src 'plugin.json') -Raw | ConvertFrom-Json
    $zip  = Join-Path $dist "$($dir)-$($meta.version).zip"
    if (Test-Path $zip) { Remove-Item $zip -Confirm:$false }
    Compress-Archive -Path (Join-Path $src '*') -DestinationPath $zip
    Write-Host "packed: $zip"
}
