# ChatProjects - Build ZIP Script
# Creates a distribution-ready ZIP file, excluding files listed in .distignore

param(
    [string]$OutputPath = "..\chatprojects.zip"
)

$PluginDir = $PSScriptRoot
$PluginName = "chatprojects"
$DistIgnore = Join-Path $PluginDir ".distignore"

# Read .distignore and build exclusion patterns
$ExcludePatterns = @()
if (Test-Path $DistIgnore) {
    $ExcludePatterns = Get-Content $DistIgnore | Where-Object {
        $_ -and $_ -notmatch '^\s*#' -and $_ -notmatch '^\s*$'
    } | ForEach-Object { $_.Trim() }
}

# Additional patterns to always exclude
$ExcludePatterns += @(
    "build-zip.ps1",
    "*.zip"
)

Write-Host "Building ChatProjects distribution ZIP..." -ForegroundColor Cyan
Write-Host "Excluding patterns:" -ForegroundColor Yellow
$ExcludePatterns | ForEach-Object { Write-Host "  - $_" -ForegroundColor DarkGray }

# Create temp directory
$TempDir = Join-Path $env:TEMP "chatprojects-build-$(Get-Date -Format 'yyyyMMddHHmmss')"
$TempPluginDir = Join-Path $TempDir $PluginName

Write-Host "`nCreating temporary build directory..." -ForegroundColor Yellow
New-Item -ItemType Directory -Path $TempPluginDir -Force | Out-Null

# Function to check if path matches any exclude pattern
function Test-Excluded {
    param([string]$RelativePath)

    foreach ($pattern in $ExcludePatterns) {
        # Handle directory patterns (ending with /)
        $cleanPattern = $pattern.TrimEnd('/')

        # Handle glob patterns
        if ($pattern -match '\*') {
            # Convert glob to regex
            $regexPattern = "^" + [regex]::Escape($cleanPattern).Replace("\*\*", ".*").Replace("\*", "[^/\\]*") + "$"
            if ($RelativePath -match $regexPattern) {
                return $true
            }
            # Also check if any parent directory matches
            $parts = $RelativePath -split '[/\\]'
            for ($i = 0; $i -lt $parts.Count; $i++) {
                $partialPath = $parts[0..$i] -join '/'
                if ($partialPath -match $regexPattern) {
                    return $true
                }
            }
        }
        else {
            # Exact match or starts with pattern
            if ($RelativePath -eq $cleanPattern -or
                $RelativePath -like "$cleanPattern/*" -or
                $RelativePath -like "$cleanPattern\*" -or
                $RelativePath -like "*/$cleanPattern" -or
                $RelativePath -like "*\$cleanPattern" -or
                $RelativePath -like "*/$cleanPattern/*" -or
                $RelativePath -like "*\$cleanPattern\*") {
                return $true
            }
            # Check filename match
            $fileName = Split-Path $RelativePath -Leaf
            if ($fileName -eq $cleanPattern) {
                return $true
            }
        }
    }
    return $false
}

# Copy files, excluding patterns
Write-Host "Copying files..." -ForegroundColor Yellow
$AllFiles = Get-ChildItem -Path $PluginDir -Recurse -Force
$CopiedCount = 0
$ExcludedCount = 0

foreach ($File in $AllFiles) {
    $RelativePath = $File.FullName.Substring($PluginDir.Length + 1)

    if (Test-Excluded $RelativePath) {
        $ExcludedCount++
        continue
    }

    $DestPath = Join-Path $TempPluginDir $RelativePath

    if ($File.PSIsContainer) {
        if (!(Test-Path $DestPath)) {
            New-Item -ItemType Directory -Path $DestPath -Force | Out-Null
        }
    }
    else {
        $DestDir = Split-Path $DestPath -Parent
        if (!(Test-Path $DestDir)) {
            New-Item -ItemType Directory -Path $DestDir -Force | Out-Null
        }
        Copy-Item -Path $File.FullName -Destination $DestPath -Force
        $CopiedCount++
    }
}

Write-Host "  Copied: $CopiedCount files" -ForegroundColor Green
Write-Host "  Excluded: $ExcludedCount items" -ForegroundColor DarkGray

# Remove empty directories
Get-ChildItem -Path $TempPluginDir -Recurse -Directory |
    Sort-Object { $_.FullName.Length } -Descending |
    Where-Object { (Get-ChildItem $_.FullName -Force).Count -eq 0 } |
    Remove-Item -Force

# Create ZIP
$ZipPath = if ([System.IO.Path]::IsPathRooted($OutputPath)) {
    $OutputPath
} else {
    Join-Path $PluginDir $OutputPath
}

Write-Host "`nCreating ZIP archive..." -ForegroundColor Yellow
# Use .NET ZipFile for proper cross-platform paths
Add-Type -AssemblyName System.IO.Compression.FileSystem

# Remove existing ZIP if present
if (Test-Path $ZipPath) {
    Remove-Item $ZipPath -Force
}

# Create ZIP with forward slashes for Linux compatibility
$zip = [System.IO.Compression.ZipFile]::Open($ZipPath, 'Create')
$basePath = $TempPluginDir

Get-ChildItem -Path $TempPluginDir -Recurse -File | ForEach-Object {
    $relativePath = $_.FullName.Substring($basePath.Length + 1)
    # Convert backslashes to forward slashes for cross-platform compatibility
    $entryName = "chatprojects/" + ($relativePath -replace '\\', '/')
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $entryName, 'Optimal') | Out-Null
}
$zip.Dispose()

# Cleanup temp directory
Remove-Item -Path $TempDir -Recurse -Force

# Final output
$ZipSize = [math]::Round((Get-Item $ZipPath).Length / 1MB, 2)
Write-Host ""
Write-Host "Build complete!" -ForegroundColor Green
Write-Host "Output: $ZipPath" -ForegroundColor Cyan
Write-Host "Size: $ZipSize MB" -ForegroundColor Cyan
