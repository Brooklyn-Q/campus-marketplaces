# Automated Deployment Script for Campus Marketplace
Write-Host "🚀 Starting Campus Marketplace Deployment..." -ForegroundColor Green

# Set working directory
Set-Location "C:\xampp\htdocs\marketplace"

# Check if deployment script exists
if (-not (Test-Path "deploy_to_alwaysdata.bat")) {
    Write-Host "❌ ERROR: deploy_to_alwaysdata.bat not found!" -ForegroundColor Red
    exit 1
}

Write-Host "📁 Found deployment script" -ForegroundColor Cyan
Write-Host "🔧 Starting deployment process..." -ForegroundColor Yellow

# Run the deployment script
try {
    & .\deploy_to_alwaysdata.bat
    Write-Host "✅ Deployment completed!" -ForegroundColor Green
} catch {
    Write-Host "❌ Deployment failed: $_" -ForegroundColor Red
    exit 1
}

Write-Host "🎯 Next step: Run Supabase database migration" -ForegroundColor Cyan
Write-Host "📋 Open: https://app.supabase.com/project/ontxylkqzojjqhzimrcg/sql" -ForegroundColor White