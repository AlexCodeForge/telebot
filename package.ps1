# Telegram Video Bot - Package Script (PowerShell)
# Creates a clean distribution package for Windows

$PROJECT_NAME = "telegram-video-bot"
$VERSION = Get-Date -Format "yyyyMMdd"
$PACKAGE_NAME = "$PROJECT_NAME-v$VERSION"

Write-Host "📦 Creating distribution package: $PACKAGE_NAME" -ForegroundColor Blue

# Create temporary directory
if (Test-Path "dist") { Remove-Item "dist" -Recurse -Force }
New-Item -ItemType Directory -Path "dist\$PACKAGE_NAME" -Force | Out-Null

# Copy essential directories
Write-Host "📁 Copying project files..." -ForegroundColor Yellow
$directories = @("app", "bootstrap", "config", "database", "docs", "public", "resources", "routes", "storage", "tests")
foreach ($dir in $directories) {
    if (Test-Path $dir) {
        Copy-Item $dir -Destination "dist\$PACKAGE_NAME\" -Recurse
    }
}

# Copy essential files
$files = @(
    ".env.example", "artisan", "composer.json", "composer.lock",
    "package.json", "package-lock.json", "phpunit.xml", "README.md",
    "vite.config.js", "tailwind.config.js", "postcss.config.js",
    "install.sh", "INSTALL.md"
)

foreach ($file in $files) {
    if (Test-Path $file) {
        Copy-Item $file -Destination "dist\$PACKAGE_NAME\"
    }
}

# Clean up development files
Write-Host "🧹 Cleaning up development files..." -ForegroundColor Yellow
$cleanupPaths = @(
    "dist\$PACKAGE_NAME\storage\logs\*",
    "dist\$PACKAGE_NAME\storage\framework\cache\*",
    "dist\$PACKAGE_NAME\storage\framework\sessions\*",
    "dist\$PACKAGE_NAME\storage\framework\views\*",
    "dist\$PACKAGE_NAME\bootstrap\cache\*"
)

foreach ($path in $cleanupPaths) {
    if (Test-Path $path) {
        Remove-Item $path -Force -Recurse -ErrorAction SilentlyContinue
    }
}

# Create .gitkeep files for empty directories
$keepDirs = @(
    "dist\$PACKAGE_NAME\storage\logs",
    "dist\$PACKAGE_NAME\storage\framework\cache",
    "dist\$PACKAGE_NAME\storage\framework\sessions",
    "dist\$PACKAGE_NAME\storage\framework\views",
    "dist\$PACKAGE_NAME\bootstrap\cache"
)

foreach ($dir in $keepDirs) {
    if (Test-Path $dir) {
        New-Item -ItemType File -Path "$dir\.gitkeep" -Force | Out-Null
    }
}

Write-Host "✅ Package created: dist\$PACKAGE_NAME\" -ForegroundColor Green
Write-Host ""
Write-Host "📋 Package contents:" -ForegroundColor Cyan
Write-Host "   - Complete Laravel application"
Write-Host "   - Automated installation script (install.sh)"
Write-Host "   - Customer-friendly README (INSTALL.md)"
Write-Host "   - Production setup documentation (docs/)"
Write-Host ""
Write-Host "🚀 Ready for distribution!" -ForegroundColor Green
Write-Host ""
Write-Host "📤 To distribute:" -ForegroundColor Yellow
Write-Host "   1. Zip the dist\$PACKAGE_NAME\ folder"
Write-Host "   2. Send to customer"
Write-Host "   3. Customer extracts and runs: bash install.sh yourdomain.com bot_token"
# Telegram Video Bot - Package Script (PowerShell)
# Creates a clean distribution package for Windows

$PROJECT_NAME = "telegram-video-bot"
$VERSION = Get-Date -Format "yyyyMMdd"
$PACKAGE_NAME = "$PROJECT_NAME-v$VERSION"

Write-Host "?? Creating distribution package: $PACKAGE_NAME" -ForegroundColor Blue

# Create temporary directory
if (Test-Path "dist") { Remove-Item "dist" -Recurse -Force }
New-Item -ItemType Directory -Path "dist\$PACKAGE_NAME" -Force | Out-Null

# Copy essential directories
Write-Host "?? Copying project files..." -ForegroundColor Yellow
$directories = @("app", "bootstrap", "config", "database", "docs", "public", "resources", "routes", "storage", "tests")
foreach ($dir in $directories) {
    if (Test-Path $dir) {
        Copy-Item $dir -Destination "dist\$PACKAGE_NAME\" -Recurse
    }
}

# Copy essential files
$files = @(
    ".env.example", "artisan", "composer.json", "composer.lock", 
    "package.json", "package-lock.json", "phpunit.xml", "README.md",
    "vite.config.js", "tailwind.config.js", "postcss.config.js",
    "install.sh", "INSTALL.md"
)

foreach ($file in $files) {
    if (Test-Path $file) {
        Copy-Item $file -Destination "dist\$PACKAGE_NAME\"
    }
}

# Clean up development files
Write-Host "?? Cleaning up development files..." -ForegroundColor Yellow
$cleanupPaths = @(
    "dist\$PACKAGE_NAME\storage\logs\*",
    "dist\$PACKAGE_NAME\storage\framework\cache\*",
    "dist\$PACKAGE_NAME\storage\framework\sessions\*",
    "dist\$PACKAGE_NAME\storage\framework\views\*",
    "dist\$PACKAGE_NAME\bootstrap\cache\*"
)

foreach ($path in $cleanupPaths) {
    if (Test-Path $path) {
        Remove-Item $path -Force -Recurse -ErrorAction SilentlyContinue
    }
}

# Create .gitkeep files for empty directories
$keepDirs = @(
    "dist\$PACKAGE_NAME\storage\logs",
    "dist\$PACKAGE_NAME\storage\framework\cache",
    "dist\$PACKAGE_NAME\storage\framework\sessions", 
    "dist\$PACKAGE_NAME\storage\framework\views",
    "dist\$PACKAGE_NAME\bootstrap\cache"
)

foreach ($dir in $keepDirs) {
    if (Test-Path $dir) {
        New-Item -ItemType File -Path "$dir\.gitkeep" -Force | Out-Null
    }
}

Write-Host "? Package created: dist\$PACKAGE_NAME\" -ForegroundColor Green
Write-Host ""
Write-Host "?? Package contents:" -ForegroundColor Cyan
Write-Host "   - Complete Laravel application"
Write-Host "   - Automated installation script (install.sh)"
Write-Host "   - Customer-friendly README (INSTALL.md)"
Write-Host "   - Production setup documentation (docs/)"
Write-Host ""
Write-Host "?? Ready for distribution!" -ForegroundColor Green
Write-Host ""
Write-Host "?? To distribute:" -ForegroundColor Yellow
Write-Host "   1. Zip the dist\$PACKAGE_NAME\ folder"
Write-Host "   2. Send to customer"
Write-Host "   3. Customer extracts and runs: bash install.sh yourdomain.com bot_token"
