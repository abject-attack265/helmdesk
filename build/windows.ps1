param(
    [Parameter(Mandatory = $true)]
    [ValidateNotNullOrEmpty()]
    [string] $AppVersion,
    [string] $PhpVersion = '8.5.8',
    [string] $PhpSha256 = 'A7323E50A1E29FDDC278D9297CA9A4EC134BD1CCA5396AF39115284A125CAB2F',
    [string] $PhpDevelSha256 = 'CA335C955311141DED17F08F4BA64C7CBD8B62728F8ADD0CF3406E137F09F564',
    [string] $ComposerVersion = '2.10.2',
    [string] $ComposerSha256 = '5EE7125F8A30A34D246CEFDC0BC85B8A783B28F2AEC968994118512350D28027',
    [string] $VcRedistSha256 = '843068991DAAA1F73AD9F6239BCE4D0F6A07A51F18C37EA2A867E9BECA71295C',
    [string] $InnoChineseSha256 = '7D544B9BB1D142CFA11F2E5D3CC8ABE2E55F8E066C5124E3772675AA236E1278',
    [string] $VcpkgCommit = '82b6bc886d7b0f8342e34babc2e0b8943f79b0e1',
    [string] $VsToolset = 'vs17',
    [string] $Triplet = 'x64-windows',
    [string] $VsDevCmd = '',
    [string] $InnoCompiler = '',
    [string] $SigningCertificateThumbprint = '',
    [string] $TimestampUrl = 'http://timestamp.digicert.com',
    [switch] $RequireSigning,
    [switch] $SigningCertificateMachineStore,
    [switch] $SkipComposer,
    [switch] $SkipNpm,
    [switch] $SkipFrontend,
    [switch] $SkipInstaller,
    [switch] $NoZip
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$RepoRoot = Split-Path -Parent $PSScriptRoot
$BuildRoot = Join-Path $RepoRoot 'build\windows'
$OutputRoot = Join-Path $RepoRoot 'build\output'
$PhpZip = Join-Path $BuildRoot 'php.zip'
$PhpDevelZip = Join-Path $BuildRoot 'php-devel.zip'
$PhpBin = Join-Path $BuildRoot 'php'
$PhpDevelRoot = Join-Path $BuildRoot 'php-devel'
$PhpDevel = Join-Path $PhpDevelRoot "php-$PhpVersion-devel-$VsToolset-x64"
$ComposerPhar = Join-Path $BuildRoot 'composer.phar'
$VcRedist = Join-Path $BuildRoot 'vc_redist.x64.exe'
$InnoChineseMessages = Join-Path $BuildRoot 'ChineseSimplified.isl'
$VcpkgRoot = Join-Path $BuildRoot 'vcpkg'
$VcpkgManifest = Join-Path $BuildRoot 'vcpkg-manifest'
$VcpkgInstalled = Join-Path $VcpkgManifest "vcpkg_installed\$Triplet"
$AppStaging = Join-Path $BuildRoot 'app-staging'
$FrankenphpEmbedded = Join-Path $BuildRoot 'frankenphp-embedded'
$PackageDir = Join-Path $OutputRoot 'helmdesk-windows-amd64'
$PackageZip = Join-Path $OutputRoot 'helmdesk-windows-amd64.zip'
$InstallerPath = Join-Path $OutputRoot 'HelmDesk-Setup.exe'
$VersionResource = Join-Path $RepoRoot 'cmd\helmdesk\helmdesk-version.rc'
$VersionCompiledResource = Join-Path $RepoRoot 'cmd\helmdesk\helmdesk-version.res'
$VersionObject = Join-Path $RepoRoot 'cmd\helmdesk\helmdesk-version.syso'

# Write-Step 输出当前构建阶段。
function Write-Step([string] $Message) {
    Write-Host ''
    Write-Host "==> $Message"
}

# Invoke-Checked 执行外部命令并检查退出码。
function Invoke-Checked([string] $FilePath, [string[]] $Arguments, [string] $WorkingDirectory = $RepoRoot) {
    Write-Host "> $FilePath $($Arguments -join ' ')"
    Push-Location $WorkingDirectory
    try {
        & $FilePath @Arguments
        if ($LASTEXITCODE -ne 0) {
            throw "Command failed with exit code $LASTEXITCODE`: $FilePath"
        }
    } finally {
        Pop-Location
    }
}

# Assert-Sha256 校验下载文件与构建清单中的哈希一致。
function Assert-Sha256([string] $Path, [string] $Expected, [string] $Name) {
    if ($Expected -notmatch '^[0-9A-Fa-f]{64}$') {
        throw "$Name SHA256 is invalid: $Expected"
    }
    $actual = (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash
    if (-not $actual.Equals($Expected, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "$Name SHA256 mismatch. Expected $Expected, got $actual."
    }
}

# Get-VerifiedDownload 下载并校验固定版本的构建依赖。
function Get-VerifiedDownload([string] $Uri, [string] $Path, [string] $ExpectedSha256, [string] $Name) {
    if (Test-Path -LiteralPath $Path) {
        try {
            Assert-Sha256 $Path $ExpectedSha256 $Name
            return
        } catch {
            Write-Host "$Name cache does not match the build manifest and will be refreshed."
        }
    }

    $download = "$Path.download"
    if (Test-Path -LiteralPath $download) {
        Remove-Item -LiteralPath $download -Force
    }
    Invoke-WebRequest -Uri $Uri -OutFile $download
    Assert-Sha256 $download $ExpectedSha256 $Name
    Move-Item -LiteralPath $download -Destination $Path -Force
}

# 将应用版本转换为 Windows VERSIONINFO 四段数字。
function Get-NumericVersion {
    $match = [regex]::Match(
        $AppVersion,
        '^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$'
    )
    if (-not $match.Success) {
        throw "AppVersion must be a semantic version without a v prefix: $AppVersion"
    }
    $precedenceVersion = $AppVersion.Split('+')[0]
    $separator = $precedenceVersion.IndexOf('-')
    if ($separator -ge 0) {
        foreach ($identifier in $precedenceVersion.Substring($separator + 1).Split('.')) {
            if ($identifier -match '^0[0-9]+$') {
                throw "AppVersion contains a numeric prerelease identifier with a leading zero: $AppVersion"
            }
        }
    }
    $parts = @(
        [int] $match.Groups[1].Value,
        [int] $match.Groups[2].Value,
        [int] $match.Groups[3].Value,
        0
    )
    foreach ($part in $parts) {
        if ($part -lt 0 -or $part -gt 65535) {
            throw "AppVersion numeric components must be between 0 and 65535: $AppVersion"
        }
    }
    return $parts
}

# Remove-BuildTree 删除构建目录内的指定路径。
function Remove-BuildTree([string] $Path, [string] $AllowedRoot) {
    if (-not (Test-Path -LiteralPath $Path)) {
        return
    }

    $resolved = (Resolve-Path -LiteralPath $Path).Path
    $allowed = (Resolve-Path -LiteralPath $AllowedRoot).Path
    if (-not $resolved.StartsWith($allowed, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Path is outside build root: $resolved"
    }

    if (Test-Path -LiteralPath $resolved -PathType Container) {
        Get-ChildItem -LiteralPath $resolved -Recurse -Force -ErrorAction Ignore | ForEach-Object {
            $_.Attributes = [System.IO.FileAttributes]::Normal
        }
        $longPath = if ($resolved.StartsWith('\\?\')) { $resolved } else { '\\?\' + $resolved }
        [System.IO.Directory]::Delete($longPath, $true)
        return
    }

    $item = Get-Item -LiteralPath $resolved -Force
    $item.Attributes = [System.IO.FileAttributes]::Normal
    Remove-Item -LiteralPath $resolved -Force
}

# Find-VsDevCmd 查找 Visual Studio C++ 构建环境入口。
function Find-VsDevCmd {
    if ($VsDevCmd -ne '') {
        return (Resolve-Path -LiteralPath $VsDevCmd).Path
    }

    $vswhere = Join-Path ${env:ProgramFiles(x86)} 'Microsoft Visual Studio\Installer\vswhere.exe'
    if (Test-Path -LiteralPath $vswhere) {
        $path = & $vswhere -latest -products * -requires Microsoft.VisualStudio.Component.VC.Tools.x86.x64 -find 'Common7\Tools\VsDevCmd.bat' | Select-Object -First 1
        if ($path) {
            return $path
        }
    }

    $candidates = @(
        "$env:ProgramFiles\Microsoft Visual Studio\2022\Community\Common7\Tools\VsDevCmd.bat",
        "$env:ProgramFiles\Microsoft Visual Studio\2022\Professional\Common7\Tools\VsDevCmd.bat",
        "$env:ProgramFiles\Microsoft Visual Studio\2022\Enterprise\Common7\Tools\VsDevCmd.bat",
        "$env:ProgramFiles\Microsoft Visual Studio\2022\BuildTools\Common7\Tools\VsDevCmd.bat"
    )
    foreach ($candidate in $candidates) {
        if (Test-Path -LiteralPath $candidate) {
            return $candidate
        }
    }

    throw 'Visual Studio C++ build tools were not found.'
}

# Find-InnoCompiler 查找 Inno Setup 命令行编译器。
function Find-InnoCompiler {
    if ($InnoCompiler -ne '') {
        return (Resolve-Path -LiteralPath $InnoCompiler).Path
    }

    $command = Get-Command 'ISCC.exe' -ErrorAction SilentlyContinue
    if ($command) {
        return $command.Source
    }

    $candidates = @(
        "$env:LOCALAPPDATA\Programs\Inno Setup 6\ISCC.exe",
        "${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe",
        "$env:ProgramFiles\Inno Setup 6\ISCC.exe"
    )
    foreach ($candidate in $candidates) {
        if (Test-Path -LiteralPath $candidate) {
            return $candidate
        }
    }

    throw 'Inno Setup 6 was not found. Install it or use -SkipInstaller.'
}

# Find-SignTool 查找 Windows SDK 代码签名工具。
function Find-SignTool {
    $command = Get-Command 'signtool.exe' -ErrorAction SilentlyContinue
    if ($command) {
        return $command.Source
    }

    if ($env:WindowsSdkDir) {
        $candidate = Get-ChildItem -LiteralPath (Join-Path $env:WindowsSdkDir 'bin') -Recurse -Filter 'signtool.exe' -ErrorAction SilentlyContinue |
            Where-Object FullName -Match '\\x64\\signtool\.exe$' |
            Sort-Object FullName -Descending |
            Select-Object -First 1
        if ($candidate) {
            return $candidate.FullName
        }
    }

    throw 'Windows SDK signtool.exe was not found.'
}

# Import-VsEnvironment 导入 Visual Studio amd64 编译环境。
function Import-VsEnvironment([string] $DevCmd) {
    Write-Step 'Loading Visual Studio build environment'
    cmd /s /c "`"$DevCmd`" -arch=amd64 -host_arch=amd64 >nul && set" | ForEach-Object {
        $index = $_.IndexOf('=')
        if ($index -gt 0) {
            [Environment]::SetEnvironmentVariable($_.Substring(0, $index), $_.Substring($index + 1), 'Process')
        }
    }
}

# Install-Php 准备 PHP 线程安全运行时、开发包和 Composer。
function Install-Php {
    Write-Step "Preparing PHP $PhpVersion TS runtime"
    New-Item -ItemType Directory -Force -Path $BuildRoot | Out-Null

    $phpUrl = "https://windows.php.net/downloads/releases/archives/php-$PhpVersion-Win32-$VsToolset-x64.zip"
    $phpDevelUrl = "https://windows.php.net/downloads/releases/archives/php-devel-pack-$PhpVersion-Win32-$VsToolset-x64.zip"
    Get-VerifiedDownload $phpUrl $PhpZip $PhpSha256 "PHP $PhpVersion runtime"
    Get-VerifiedDownload $phpDevelUrl $PhpDevelZip $PhpDevelSha256 "PHP $PhpVersion development pack"

    $phpMarker = Join-Path $PhpBin '.source.sha256'
    $phpNeedsExtraction = -not (Test-Path -LiteralPath $phpMarker) -or
        -not ((Get-Content -LiteralPath $phpMarker -Raw).Trim().Equals($PhpSha256, [System.StringComparison]::OrdinalIgnoreCase))
    if ($phpNeedsExtraction) {
        Remove-BuildTree $PhpBin $BuildRoot
        Expand-Archive -LiteralPath $PhpZip -DestinationPath $PhpBin -Force
        Set-Content -LiteralPath $phpMarker -Value $PhpSha256 -NoNewline -Encoding ascii
    }

    $phpDevelMarker = Join-Path $PhpDevelRoot '.source.sha256'
    $phpDevelNeedsExtraction = -not (Test-Path -LiteralPath $PhpDevelMarker) -or
        -not ((Get-Content -LiteralPath $phpDevelMarker -Raw).Trim().Equals($PhpDevelSha256, [System.StringComparison]::OrdinalIgnoreCase))
    if ($phpDevelNeedsExtraction) {
        Remove-BuildTree $PhpDevelRoot $BuildRoot
        New-Item -ItemType Directory -Force -Path $PhpDevelRoot | Out-Null
        Expand-Archive -LiteralPath $PhpDevelZip -DestinationPath $PhpDevelRoot -Force
        Set-Content -LiteralPath $phpDevelMarker -Value $PhpDevelSha256 -NoNewline -Encoding ascii
    }
    if (-not (Test-Path -LiteralPath $PhpDevel)) {
        throw "PHP development pack layout is invalid: $PhpDevel"
    }

    $phpIni = Join-Path $PhpBin 'php.ini'
    Copy-Item -LiteralPath (Join-Path $PhpBin 'php.ini-production') -Destination $phpIni -Force
    $ini = Get-Content -LiteralPath $phpIni -Raw
    $ini = $ini -replace '(?m)^;?extension_dir\s*=.*$', 'extension_dir = "ext"'
    foreach ($extension in @('curl', 'fileinfo', 'gd', 'intl', 'mbstring', 'openssl', 'pdo_sqlite', 'sqlite3', 'zip')) {
        $ini = $ini -replace "(?m)^;extension=$extension\s*$", "extension=$extension"
    }
    Set-Content -LiteralPath $phpIni -Value $ini -Encoding ascii

    $composerUrl = "https://getcomposer.org/download/$ComposerVersion/composer.phar"
    Get-VerifiedDownload $composerUrl $ComposerPhar $ComposerSha256 "Composer $ComposerVersion"
}

# Install-VcRedist 下载并验证固定哈希且由微软签名的 x64 VC++ v14 运行时。
function Install-VcRedist {
    Write-Step 'Preparing Microsoft Visual C++ runtime'
    Get-VerifiedDownload `
        'https://aka.ms/vc14/vc_redist.x64.exe' `
        $VcRedist `
        $VcRedistSha256 `
        'Microsoft Visual C++ x64 runtime'

    $signature = Get-AuthenticodeSignature -LiteralPath $VcRedist
    if ($signature.Status -ne [System.Management.Automation.SignatureStatus]::Valid -or
        $signature.SignerCertificate.Subject -notmatch '(^|,\s*)CN=Microsoft Corporation(,|$)') {
        throw "Microsoft Visual C++ runtime signature is invalid: $($signature.Status) $($signature.SignerCertificate.Subject)"
    }
}

# Install-Vcpkg 安装 Windows 链接依赖。
function Install-Vcpkg {
    Write-Step 'Preparing vcpkg dependencies'
    if ($VcpkgCommit -notmatch '^[0-9a-fA-F]{40}$') {
        throw "VcpkgCommit must be a full Git commit: $VcpkgCommit"
    }
    if (-not (Test-Path -LiteralPath $VcpkgRoot)) {
        Invoke-Checked 'git' @('clone', '--filter=blob:none', '--no-checkout', 'https://github.com/microsoft/vcpkg.git', $VcpkgRoot)
    }
    Invoke-Checked 'git' @('-C', $VcpkgRoot, 'fetch', '--depth', '1', 'origin', $VcpkgCommit)
    Invoke-Checked 'git' @('-C', $VcpkgRoot, 'checkout', '--detach', $VcpkgCommit)
    $resolvedCommit = (& git -C $VcpkgRoot rev-parse HEAD).Trim()
    if (-not $resolvedCommit.Equals($VcpkgCommit, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "vcpkg checkout mismatch. Expected $VcpkgCommit, got $resolvedCommit."
    }

    $vcpkgExe = Join-Path $VcpkgRoot 'vcpkg.exe'
    if (-not (Test-Path -LiteralPath $vcpkgExe)) {
        Invoke-Checked 'cmd' @('/c', (Join-Path $VcpkgRoot 'bootstrap-vcpkg.bat'), '-disableMetrics') $VcpkgRoot
    }

    New-Item -ItemType Directory -Force -Path $VcpkgManifest | Out-Null
    Set-Content -LiteralPath (Join-Path $VcpkgManifest 'vcpkg.json') -Encoding ascii -Value @"
{
  "builtin-baseline": "$VcpkgCommit",
  "dependencies": [
    "brotli",
    "pthreads"
  ]
}
"@
    Invoke-Checked $vcpkgExe @('install', "--triplet=$Triplet", "--x-manifest-root=$VcpkgManifest")
}

# Build-Application 安装生产依赖并构建前端资源。
function Build-Application {
    $env:PATH = "$PhpBin;$env:PATH"
    $env:PHPRC = $PhpBin

    if (-not $SkipComposer) {
        Write-Step 'Installing production Composer dependencies'
        Invoke-Checked (Join-Path $PhpBin 'php.exe') @($ComposerPhar, 'install', '--no-dev', '--optimize-autoloader', '--no-interaction')
    }
    if (-not $SkipNpm) {
        Write-Step 'Installing npm dependencies'
        Invoke-Checked 'npm' @('ci')
    }
    if (-not $SkipFrontend) {
        Write-Step 'Building frontend assets'
        Invoke-Checked 'npm' @('run', 'build')
    }
}

# Stage-Application 整理独立二进制需要内嵌的 Laravel 文件。
function Stage-Application {
    Write-Step 'Preparing Laravel application'
    Remove-BuildTree $AppStaging $BuildRoot
    New-Item -ItemType Directory -Force -Path $AppStaging | Out-Null

    foreach ($directory in @('app', 'bootstrap', 'config', 'database', 'lang', 'public', 'resources', 'routes', 'vendor')) {
        Copy-Item -LiteralPath (Join-Path $RepoRoot $directory) -Destination $AppStaging -Recurse -Force
    }
    foreach ($file in @('artisan', 'composer.json', 'composer.lock')) {
        Copy-Item -LiteralPath (Join-Path $RepoRoot $file) -Destination $AppStaging -Force
    }

    foreach ($relative in @(
        '.env',
        'bootstrap\ssr',
        'public\hot',
        'public\node_modules',
        'public\storage',
        'storage',
        'vendor\jetbrains\phpstorm-stubs\tests'
    )) {
        Remove-BuildTree (Join-Path $AppStaging $relative) $AppStaging
    }
    Get-ChildItem -LiteralPath (Join-Path $AppStaging 'bootstrap\cache') -Filter '*.php' -Force | ForEach-Object {
        Remove-BuildTree $_.FullName $AppStaging
    }

    $embeddedNodeModules = Get-ChildItem -LiteralPath $AppStaging -Directory -Filter 'node_modules' -Recurse -Force
    if ($embeddedNodeModules) {
        throw "node_modules must not be embedded in the application archive: $($embeddedNodeModules.FullName -join ', ')"
    }

    Write-Host 'Embedded application contents:'
    Get-ChildItem -LiteralPath $AppStaging -Force | ForEach-Object {
        $size = if ($_.PSIsContainer) {
            (Get-ChildItem -LiteralPath $_.FullName -File -Recurse -Force | Measure-Object -Property Length -Sum).Sum
        } else {
            $_.Length
        }
        Write-Host ('{0,10:N1} MiB  {1}' -f ($size / 1MB), $_.Name)
    }
}

# Initialize-EmbeddedApp 将 Laravel 应用打包到 FrankenPHP 模块副本。
function Initialize-EmbeddedApp {
    Write-Step 'Embedding Laravel application'
    $env:GOFLAGS = '-mod=mod'
    Invoke-Checked 'go' @('mod', 'download', 'github.com/dunglas/frankenphp')
    $frankenphpDir = (& go list -mod=mod -m -f '{{.Dir}}' github.com/dunglas/frankenphp).Trim()

    Remove-BuildTree $FrankenphpEmbedded $BuildRoot
    Copy-Item -LiteralPath $frankenphpDir -Destination $FrankenphpEmbedded -Recurse -Force

    $appTar = Join-Path $FrankenphpEmbedded 'app.tar'
    if (Test-Path -LiteralPath $appTar) {
        (Get-Item -LiteralPath $appTar -Force).IsReadOnly = $false
        Remove-Item -LiteralPath $appTar -Force
    }
    $entries = @('app', 'bootstrap', 'config', 'database', 'lang', 'public', 'resources', 'routes', 'vendor', 'artisan', 'composer.json', 'composer.lock')
    Invoke-Checked 'tar' (@('-cf', $appTar, '-C', $AppStaging) + $entries)
    Write-Host ('Application archive: {0:N1} MiB' -f ((Get-Item -LiteralPath $appTar).Length / 1MB))

    $hash = (Get-FileHash -LiteralPath $appTar -Algorithm SHA256).Hash.ToLowerInvariant()
    $checksumPath = Join-Path $FrankenphpEmbedded 'app_checksum.txt'
    if (Test-Path -LiteralPath $checksumPath) {
        (Get-Item -LiteralPath $checksumPath -Force).IsReadOnly = $false
    }
    Set-Content -LiteralPath $checksumPath -Value $hash -NoNewline -Encoding ascii

    $modfile = Join-Path $BuildRoot 'helmdesk-windows.mod'
    Copy-Item -LiteralPath (Join-Path $RepoRoot 'go.mod') -Destination $modfile -Force
    $frankenphpModule = $FrankenphpEmbedded.Replace('\', '/')
    Add-Content -LiteralPath $modfile -Value "`nreplace github.com/dunglas/frankenphp => $frankenphpModule" -Encoding ascii
    return $modfile
}

# Set-GoEnvironment 配置 Windows CGO 编译和链接参数。
function Set-GoEnvironment {
    Write-Step 'Configuring Go CGO environment'
    $llvmBin = Join-Path $env:ProgramFiles 'LLVM\bin'
    if (-not (Test-Path -LiteralPath $llvmBin)) {
        throw 'LLVM tools were not found.'
    }

    $env:PATH = "$llvmBin;$(Join-Path $VcpkgInstalled 'bin');$PhpBin;$env:PATH"
    $env:PHPRC = $PhpBin
    $env:GOFLAGS = '-mod=mod'
    $env:CGO_ENABLED = '1'
    $env:CC = 'clang'
    $env:CXX = 'clang++'
    $env:CGO_CFLAGS = "-I$(Join-Path $VcpkgInstalled 'include') -I$PhpDevel\include -I$PhpDevel\include\main -I$PhpDevel\include\TSRM -I$PhpDevel\include\Zend -I$PhpDevel\include\ext -I$PhpDevel\include\win32"
    $env:CGO_LDFLAGS = "-L$(Join-Path $VcpkgInstalled 'lib') -lbrotlienc -L$PhpBin -L$PhpBin\dev -L$PhpDevel\lib -lphp8ts -lphp8embed"
}

# Initialize-VersionResource 为 Windows 二进制生成文件属性中的版本资源。
function Initialize-VersionResource {
    Write-Step 'Generating Windows version metadata'
    $version = @(Get-NumericVersion)
    $versionNumbers = $version -join ','
    $versionText = "$($version[0]).$($version[1]).$($version[2]).$($version[3])"
    Set-Content -LiteralPath $VersionResource -Encoding ascii -Value @"
#include <windows.h>

VS_VERSION_INFO VERSIONINFO
 FILEVERSION $versionNumbers
 PRODUCTVERSION $versionNumbers
 FILEFLAGSMASK 0x3fL
 FILEFLAGS 0x0L
 FILEOS VOS_NT_WINDOWS32
 FILETYPE VFT_APP
 FILESUBTYPE VFT2_UNKNOWN
BEGIN
    BLOCK "StringFileInfo"
    BEGIN
        BLOCK "040904B0"
        BEGIN
            VALUE "CompanyName", "HelmDesk\0"
            VALUE "FileDescription", "HelmDesk\0"
            VALUE "FileVersion", "$versionText\0"
            VALUE "InternalName", "helmdesk\0"
            VALUE "LegalCopyright", "Copyright (c) HelmDesk\0"
            VALUE "OriginalFilename", "helmdesk.exe\0"
            VALUE "ProductName", "HelmDesk\0"
            VALUE "ProductVersion", "$AppVersion\0"
        END
    END
    BLOCK "VarFileInfo"
    BEGIN
        VALUE "Translation", 0x0409, 1200
    END
END
"@
    Invoke-Checked 'rc.exe' @('/nologo', "/fo$VersionCompiledResource", $VersionResource)
    Invoke-Checked 'cvtres.exe' @('/NOLOGO', '/MACHINE:X64', "/OUT:$VersionObject", $VersionCompiledResource)
}

# Build-WindowsBinary 编译 Windows x86_64 独立二进制。
function Build-WindowsBinary([string] $Modfile) {
    Write-Step 'Building Windows amd64 binary'
    Remove-BuildTree $PackageDir $OutputRoot
    New-Item -ItemType Directory -Force -Path $PackageDir | Out-Null
    $appCommit = (& git rev-parse HEAD).Trim()
    if ($LASTEXITCODE -ne 0 -or $appCommit -notmatch '^[0-9a-fA-F]{40}$') {
        throw 'Unable to resolve the Git commit for version metadata.'
    }
    $appBuildDate = [DateTime]::UtcNow.ToString('yyyy-MM-ddTHH:mm:ssZ')
    $linkerFlags = "-s -w -extldflags=-fuse-ld=lld -X helmdesk/internal/buildinfo.Version=$AppVersion -X helmdesk/internal/buildinfo.Commit=$appCommit -X helmdesk/internal/buildinfo.BuildDate=$appBuildDate"

    Invoke-Checked 'go' @(
        'build',
        "-modfile=$Modfile",
        '-tags', 'nowatcher,nobadger,nomysql,nopgx',
        '-ldflags', $linkerFlags,
        '-o', (Join-Path $PackageDir 'helmdesk.exe'),
        '.\cmd\helmdesk'
    )
}

# Copy-WindowsRuntime 复制 PHP 和链接库运行文件。
function Copy-WindowsRuntime {
    Write-Step 'Copying Windows runtime files'
    foreach ($directory in @('ext', 'extras')) {
        $source = Join-Path $PhpBin $directory
        if (Test-Path -LiteralPath $source) {
            Copy-Item -LiteralPath $source -Destination $PackageDir -Recurse -Force
        }
    }
    Get-ChildItem -LiteralPath $PhpBin -Filter '*.dll' -Force | ForEach-Object {
        if ($_.Name -notin @('php8apache2_4.dll', 'php8phpdbg.dll')) {
            Copy-Item -LiteralPath $_.FullName -Destination $PackageDir -Force
        }
    }
    Copy-Item -LiteralPath (Join-Path $PhpBin 'php.ini') -Destination $PackageDir -Force
    Get-ChildItem -LiteralPath (Join-Path $VcpkgInstalled 'bin') -Filter '*.dll' -Force | ForEach-Object {
        Copy-Item -LiteralPath $_.FullName -Destination $PackageDir -Force
    }
    Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'helmdesk-console.cmd') -Destination $PackageDir -Force
    Copy-Item -LiteralPath $VcRedist -Destination (Join-Path $PackageDir 'vc_redist.x64.exe') -Force
}

# Sign-Artifact 使用 Windows 证书存储中的代码签名证书签署发布文件。
function Sign-Artifact([string] $Path) {
    if ($SigningCertificateThumbprint -eq '') {
        if ($RequireSigning) {
            throw 'Code signing is required, but SigningCertificateThumbprint is empty.'
        }
        return
    }

    $thumbprint = $SigningCertificateThumbprint -replace '\s', ''
    if ($thumbprint -notmatch '^[0-9A-Fa-f]{40,64}$') {
        throw "SigningCertificateThumbprint is invalid: $SigningCertificateThumbprint"
    }
    $arguments = @('sign', '/sha1', $thumbprint, '/fd', 'SHA256', '/tr', $TimestampUrl, '/td', 'SHA256', '/d', 'HelmDesk')
    if ($SigningCertificateMachineStore) {
        $arguments += '/sm'
    }
    $arguments += $Path
    Invoke-Checked (Find-SignTool) $arguments

    $signature = Get-AuthenticodeSignature -LiteralPath $Path
    if ($signature.Status -ne [System.Management.Automation.SignatureStatus]::Valid) {
        throw "Code signature validation failed for $Path`: $($signature.Status)"
    }
}

# Get-VcRedistVersion 读取安装器检测系统运行时所需的四段版本号。
function Get-VcRedistVersion {
    $fileVersion = (Get-Item -LiteralPath $VcRedist).VersionInfo.FileVersion
    $match = [regex]::Match($fileVersion, '^(\d+)\.(\d+)\.(\d+)\.(\d+)')
    if (-not $match.Success) {
        throw "Microsoft Visual C++ runtime version is invalid: $fileVersion"
    }
    return @(
        [int] $match.Groups[1].Value,
        [int] $match.Groups[2].Value,
        [int] $match.Groups[3].Value,
        [int] $match.Groups[4].Value
    )
}

# Compress-WindowsPackage 创建 Windows 发布压缩包。
function Compress-WindowsPackage {
    if ($NoZip) {
        return
    }
    Write-Step 'Creating Windows zip package'
    if (Test-Path -LiteralPath $PackageZip) {
        Remove-Item -LiteralPath $PackageZip -Force
    }
    Compress-Archive -Path $PackageDir -DestinationPath $PackageZip -CompressionLevel Optimal -Force
}

# Build-WindowsInstaller 将完整运行目录封装为单文件安装器。
function Build-WindowsInstaller {
    if ($SkipInstaller) {
        return
    }
    Write-Step 'Building Windows installer'
    Get-VerifiedDownload `
        'https://raw.githubusercontent.com/jrsoftware/issrc/cfdf48923178df4b4f040e038b423aa555a61ffc/Files/Languages/Unofficial/ChineseSimplified.isl' `
        $InnoChineseMessages `
        $InnoChineseSha256 `
        'Inno Setup Simplified Chinese messages'
    $compiler = Find-InnoCompiler
    $vcVersion = @(Get-VcRedistVersion)
    Invoke-Checked $compiler @(
        "/DAppVersion=$AppVersion",
        "/DSourceDir=$PackageDir",
        "/DOutputDir=$OutputRoot",
        "/DChineseMessagesFile=$InnoChineseMessages",
        "/DVcRedistMajor=$($vcVersion[0])",
        "/DVcRedistMinor=$($vcVersion[1])",
        "/DVcRedistBuild=$($vcVersion[2])",
        "/DVcRedistRevision=$($vcVersion[3])",
        (Join-Path $PSScriptRoot 'helmdesk.iss')
    )
    Sign-Artifact $InstallerPath
}

# Show-Summary 输出 Windows 构建产物信息。
function Show-Summary {
    Write-Step 'Windows package complete'
    $exe = Get-Item -LiteralPath (Join-Path $PackageDir 'helmdesk.exe')
    Write-Host "Version:   $AppVersion"
    Write-Host "Directory: $PackageDir"
    Write-Host ('Exe size:  {0} MB' -f ([math]::Round($exe.Length / 1MB, 2)))
    if (-not $NoZip) {
        Write-Host "Zip:       $PackageZip"
    }
    if (-not $SkipInstaller) {
        Write-Host "Installer: $InstallerPath"
    }
    $vcVersion = (Get-Item -LiteralPath $VcRedist).VersionInfo.FileVersion
    Write-Host "VC++:      $vcVersion"
    $signing = if ($SigningCertificateThumbprint -ne '') { 'signed' } else { 'unsigned development build' }
    Write-Host "Signing:   $signing"
    Write-Host 'Run:       helmdesk serve'
}

Push-Location $RepoRoot
try {
    Get-NumericVersion | Out-Null
    New-Item -ItemType Directory -Force -Path $BuildRoot | Out-Null
    New-Item -ItemType Directory -Force -Path $OutputRoot | Out-Null
    Import-VsEnvironment (Find-VsDevCmd)
    Install-Php
    Install-VcRedist
    Install-Vcpkg
    Build-Application
    Stage-Application
    $modfile = Initialize-EmbeddedApp
    Set-GoEnvironment
    Initialize-VersionResource
    Build-WindowsBinary $modfile
    Copy-WindowsRuntime
    Sign-Artifact (Join-Path $PackageDir 'helmdesk.exe')
    Compress-WindowsPackage
    Build-WindowsInstaller
    Show-Summary
} finally {
    foreach ($path in @($VersionResource, $VersionCompiledResource, $VersionObject)) {
        if (Test-Path -LiteralPath $path) {
            Remove-Item -LiteralPath $path -Force
        }
    }
    Pop-Location
}
