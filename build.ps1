# Builds installable zips into dist/ (used by blueprint.json and for manual upload on any host).
# Uses bsdtar (ships with Windows 10+) so entry paths are POSIX and unzip cleanly on Linux.
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
New-Item -ItemType Directory -Force dist | Out-Null
Remove-Item dist\*.zip -ErrorAction SilentlyContinue
tar.exe -a -cf dist\fei-core.zip -C wp-content\plugins fei-core
tar.exe -a -cf dist\fei-kadence-child.zip -C wp-content\themes fei-kadence-child
Get-ChildItem dist\*.zip | ForEach-Object { '{0,-28} {1,8:N0} bytes' -f $_.Name, $_.Length }
