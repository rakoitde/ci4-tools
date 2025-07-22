param (
    [string]$c,  # current version
    [string]$v,  # target version
    [string]$e,  # git user.email
    [string]$n,  # git user.name
    [switch]$h   # help
)

function Show-Help {
    @"
Usage: .\patch.ps1 [-c <current version>] [-v <target version>] [-e <user.email>] [-n <user.name>] [-h]

Patches an existing CodeIgniter 4 project repo to a different version of the framework.

Options:
  -h             Help. Show this help message and exit
  -c commit-ish  Alternate version to consider "current"
  -v commit-ish  Version to use for patching. Defaults to latest
  -e             Git global user.email
  -n             Git global user.name

Examples:
  .\patch.ps1
  .\patch.ps1 -v 4.1.2
  .\patch.ps1 -c 4.0.4 -v dev-develop#commit
"@
}

if ($h) {
    Show-Help
    exit 0
}

function Try-ExitIfFailed($LastExitCode, $Message) {
    if ($LastExitCode -ne 0) {
        Write-Error "ERROR $LastExitCode : $Message"
        exit $LastExitCode
    }
}

$ROOT = Get-Location
$SCRIPTS = Split-Path -Parent $MyInvocation.MyCommand.Definition
$ITEMS = @("app", "public", "env", "spark")

# Check git
git --version | Out-Null
Try-ExitIfFailed $LASTEXITCODE "Git must be installed."

# Git identity for containers
if ($e -and $n) {
    git config --global user.email $e
    git config --global user.name $n
}

# Check composer
composer --version | Out-Null
Try-ExitIfFailed $LASTEXITCODE "Composer must be installed."

# Verify .git folder
if (-not (Test-Path "$ROOT\.git")) {
    Write-Error "$ROOT is not a valid git repository."
    exit 1
}

$BASE = (git rev-parse --abbrev-ref HEAD).Trim()

# Require clean branch
if ((git status --porcelain).Trim() -ne "") {
    Write-Error "You have unresolved issues in branch $BASE. Please resolve before patching."
    exit 1
}

# Detect vendor package
if (Test-Path "$ROOT\vendor\codeigniter4\framework") {
    $PACKAGE = "codeigniter4/framework"
} elseif (Test-Path "$ROOT\vendor\codeigniter4\codeigniter4") {
    $PACKAGE = "codeigniter4/codeigniter4"
} else {
    Write-Error "Unable to locate a valid vendor path."
    exit 1
}

# Delete conflicting branches
foreach ($branch in "ci4-tools/scratch", "ci4-tools/patches") {
    if (git rev-parse --verify --quiet $branch) {
        if ((git log HEAD..$branch)) {
            Write-Error "Unmerged commits on $branch"
            exit 1
        }
        git branch -d $branch | Out-Null
        Try-ExitIfFailed $LASTEXITCODE "Unable to delete branch $branch"
    }
}

# Display environment info
Write-Host "************************************"
Write-Host "*          CONFIGURATION           *"
Write-Host "************************************`n"
Write-Host "Scripts Directory: $SCRIPTS"
Write-Host "Project Directory: $ROOT"
Write-Host "Target Version:    $v"
Write-Host "Current Version:   $c"
Write-Host "Source Package:    $PACKAGE"
Write-Host "Base Branch:       $BASE"
Write-Host "Selected Items:    $($ITEMS -join ', ')`n"

# From here on: stop on error
$ErrorActionPreference = "Stop"

# STAGING
Write-Host "************************************"
Write-Host "*             STAGING              *"
Write-Host "************************************`n"

git checkout --orphan ci4-tools/scratch
git rm -rf . | Out-Null
git checkout $BASE -- .gitignore composer.* | Out-Null
git clean -fd | Out-Null

Write-Host "Current version override"
# Current version override
if ($c) {
    composer require --no-scripts --with-all-dependencies $PACKAGE $c
    git restore composer.* | Out-Null
}

Write-Host "Copy source items"
# Copy source items
foreach ($item in $ITEMS) {
    Copy-Item -Recurse -Force "vendor/$PACKAGE/$item" "$ROOT"
}

Write-Host "git add . | Out-Null"
git add . | Out-Null
Write-Host "git reset composer.* | Out-Null"
git reset composer.* | Out-Null
Write-Host "git commit -m 'Stage framework' --no-verify | Out-Null"
git commit -m "Stage framework" --no-verify | Out-Null

Write-Host "Upgrade"
# Upgrade
if ($v) {
    $OUTPUT = (composer require --no-scripts --with-all-dependencies $PACKAGE $v 2>&1)
} else {
    #$OUTPUT = (composer update --no-scripts --with-all-dependencies $PACKAGE 2>&1)
    $ErrorActionPreference = "SilentlyContinue"
    $cmd = "composer update --no-scripts --with-all-dependencies $PACKAGE"
    $OUTPUT = & cmd /c $cmd 2>&1
    $ErrorActionPreference = "Continue" # ggf. zurücksetzen
}

$FROMTO = ""
foreach ($line in $OUTPUT) {
    if ($line -match "$PACKAGE.+\((v[\d\.]+ => v[\d\.]+)\)") {
        $FROMTO = "($($Matches[1]))"
    }
    Write-Host $line
}

Write-Host "Apply upgrade files"
# Apply upgrade files
foreach ($item in $ITEMS) {
    Remove-Item -Recurse -Force $item
    Copy-Item -Recurse -Force "vendor/$PACKAGE/$item" "$ROOT"
}

git add . | Out-Null
git reset composer.* | Out-Null
git commit -m "Patch framework $FROMTO" --no-verify | Out-Null

Write-Host "Clean Up"
# Clean up
Remove-Item composer.* -Force

Write-Host "Create new working branch"
# Create new working branch
git checkout -b ci4-tools/patches $BASE | Out-Null
composer install --no-scripts | Out-Null

# MERGE
Write-Host "`n************************************"
Write-Host "*              MERGING             *"
Write-Host "************************************`n"

$ErrorActionPreference = "Continue"
git cherry-pick ci4-tools/scratch
if ($LASTEXITCODE -eq 0) {
    Write-Host "`n************************************"
    Write-Host "*              SUCCESS             *"
    Write-Host "************************************`n"
    Write-Host "Patch successful! Updated files are on branch ci4-tools/patches."
    git branch -D ci4-tools/scratch | Out-Null
    exit 0
} else {
    git status
    Write-Host "`n************************************"
    Write-Host "*            RESOLUTION            *"
    Write-Host "************************************`n"
    Write-Host "Conflicts detected during patch! Resolve manually in branch ci4-tools/patches."
    Write-Host "After resolution, delete the old branch: ci4-tools/scratch.`n"
    exit 1
}
