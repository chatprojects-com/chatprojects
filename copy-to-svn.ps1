# PowerShell script to copy ChatProjects plugin files to SVN trunk
# Excludes development files per .distignore

$source = "c:\websites\demo-free.chatprojects.com\wp-content\plugins\chatprojects"
$dest = "C:\svn\chatprojects\trunk"

# Clean destination first (optional - uncomment if needed)
# Remove-Item -Path "$dest\*" -Recurse -Force -ErrorAction SilentlyContinue

# Create destination if it doesn't exist
New-Item -ItemType Directory -Path $dest -Force | Out-Null

# Files to copy (root level)
$rootFiles = @(
    "chatprojects.php",
    "uninstall.php",
    "readme.txt",
    "stream-endpoint.php"
)

# Folders to copy entirely
$folders = @(
    "admin",
    "includes",
    "public",
    "languages",
    "licenses"
)

# Copy root files
foreach ($file in $rootFiles) {
    $srcPath = Join-Path $source $file
    if (Test-Path $srcPath) {
        Copy-Item -Path $srcPath -Destination $dest -Force
        Write-Host "Copied: $file" -ForegroundColor Green
    } else {
        Write-Host "Skipped (not found): $file" -ForegroundColor Yellow
    }
}

# Copy folders (remove destination first to avoid nested folders)
foreach ($folder in $folders) {
    $srcPath = Join-Path $source $folder
    $destPath = Join-Path $dest $folder
    if (Test-Path $srcPath) {
        # Remove existing destination folder to prevent nesting
        if (Test-Path $destPath) {
            Remove-Item -Path $destPath -Recurse -Force
        }
        Copy-Item -Path $srcPath -Destination $destPath -Recurse -Force
        Write-Host "Copied folder: $folder" -ForegroundColor Green
    } else {
        Write-Host "Skipped (not found): $folder" -ForegroundColor Yellow
    }
}

# Copy assets folder (only css, js, dist - exclude src)
$assetsSource = Join-Path $source "assets"
$assetsDest = Join-Path $dest "assets"
New-Item -ItemType Directory -Path $assetsDest -Force | Out-Null

# Copy assets subfolders (excluding src)
$assetSubfolders = @("css", "js", "dist")
foreach ($subfolder in $assetSubfolders) {
    $srcPath = Join-Path $assetsSource $subfolder
    $destPath = Join-Path $assetsDest $subfolder
    if (Test-Path $srcPath) {
        # Remove existing destination folder to prevent nesting
        if (Test-Path $destPath) {
            Remove-Item -Path $destPath -Recurse -Force
        }
        Copy-Item -Path $srcPath -Destination $destPath -Recurse -Force
        Write-Host "Copied: assets/$subfolder" -ForegroundColor Green
    }
}

Write-Host ""
Write-Host "====================================" -ForegroundColor Cyan
Write-Host "Copy complete!" -ForegroundColor Cyan
Write-Host "Destination: $dest" -ForegroundColor Cyan
Write-Host "====================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Right-click on C:\svn\chatprojects\trunk" -ForegroundColor White
Write-Host "2. Select TortoiseSVN -> Add..." -ForegroundColor White
Write-Host "3. Select all files and click OK" -ForegroundColor White
