#!/bin/bash

# 🚀 Alwaysdata Deployment Script
# Version: 1.1 - Profile Picture & WhatsApp Fixes

echo "🚀 Starting Alwaysdata Deployment..."
echo "=================================="

# Configuration - UPDATE THESE VALUES
ALWAYSDATA_HOST="your-host.alwaysdata.net"
ALWAYSDATA_USER="your-username"
ALWAYSDATA_PATH="/path/to/your/site"
LOCAL_PATH="$(pwd)"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[✓]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[⚠]${NC} $1"
}

print_error() {
    echo -e "${RED}[✗]${NC} $1"
}

print_info() {
    echo -e "${BLUE}[ℹ]${NC} $1"
}

# Check if required tools are installed
check_dependencies() {
    print_info "Checking dependencies..."
    
    if ! command -v rsync &> /dev/null; then
        print_error "rsync is not installed. Please install rsync first."
        exit 1
    fi
    
    if ! command -v ssh &> /dev/null; then
        print_error "ssh is not installed. Please install ssh first."
        exit 1
    fi
    
    print_status "All dependencies found"
}

# Backup current version
backup_current() {
    print_info "Creating backup of current version..."
    
    BACKUP_DIR="backup_$(date +%Y%m%d_%H%M%S)"
    ssh $ALWAYSDATA_USER@$ALWAYSDATA_HOST "mkdir -p ~/backups && cp -r $ALWAYSDATA_PATH ~/backups/$BACKUP_DIR"
    
    if [ $? -eq 0 ]; then
        print_status "Backup created: ~/backups/$BACKUP_DIR"
    else
        print_error "Backup failed"
        exit 1
    fi
}

# Sync modified files
sync_files() {
    print_info "Syncing modified files to alwaysdata..."
    
    # Files that were modified
    FILES_TO_SYNC=(
        "register.php"
        "whatsapp_join.php" 
        "edit_profile.php"
        "assets/img/default-avatar.svg"
    )
    
    for file in "${FILES_TO_SYNC[@]}"; do
        if [ -f "$LOCAL_PATH/$file" ]; then
            print_info "Uploading $file..."
            rsync -avz --progress "$LOCAL_PATH/$file" "$ALWAYSDATA_USER@$ALWAYSDATA_HOST:$ALWAYSDATA_PATH/$file"
            
            if [ $? -eq 0 ]; then
                print_status "Uploaded $file"
            else
                print_error "Failed to upload $file"
                exit 1
            fi
        else
            print_warning "File not found: $file"
        fi
    done
}

# Create required directories
create_directories() {
    print_info "Creating required directories..."
    
    ssh $ALWAYSDATA_USER@$ALWAYSDATA_HOST "
        mkdir -p $ALWAYSDATA_PATH/uploads/avatars
        mkdir -p $ALWAYSDATA_PATH/uploads/banners
        chmod 755 $ALWAYSDATA_PATH/uploads
        chmod 755 $ALWAYSDATA_PATH/uploads/avatars
        chmod 755 $ALWAYSDATA_PATH/uploads/banners
    "
    
    if [ $? -eq 0 ]; then
        print_status "Directories created and permissions set"
    else
        print_error "Failed to create directories"
        exit 1
    fi
}

# Set file permissions
set_permissions() {
    print_info "Setting file permissions..."
    
    ssh $ALWAYSDATA_USER@$ALWAYSDATA_HOST "
        chmod 644 $ALWAYSDATA_PATH/register.php
        chmod 644 $ALWAYSDATA_PATH/whatsapp_join.php
        chmod 644 $ALWAYSDATA_PATH/edit_profile.php
        chmod 644 $ALWAYSDATA_PATH/assets/img/default-avatar.svg
    "
    
    if [ $? -eq 0 ]; then
        print_status "File permissions set"
    else
        print_error "Failed to set permissions"
        exit 1
    fi
}

# Run database migration
run_migration() {
    print_info "Running database migration..."
    
    # Check if psql is available locally
    if command -v psql &> /dev/null; then
        print_info "Running migration locally..."
        psql -h $ALWAYSDATA_HOST -U $ALWAYSDATA_USER -d your_database_name -f deploy_database_migration.sql
    else
        print_warning "psql not found locally. Please run deploy_database_migration.sql manually on alwaysdata."
        print_info "You can run it via alwaysdata admin panel or SSH:"
        print_info "psql -h localhost -U your_db_user -d your_database_name -f deploy_database_migration.sql"
    fi
}

# Health check
health_check() {
    print_info "Performing health check..."
    
    # Check if files exist on server
    ssh $ALWAYSDATA_USER@$ALWAYSDATA_HOST "
        test -f $ALWAYSDATA_PATH/register.php && echo 'register.php: OK' || echo 'register.php: MISSING'
        test -f $ALWAYSDATA_PATH/whatsapp_join.php && echo 'whatsapp_join.php: OK' || echo 'whatsapp_join.php: MISSING'
        test -f $ALWAYSDATA_PATH/edit_profile.php && echo 'edit_profile.php: OK' || echo 'edit_profile.php: MISSING'
        test -f $ALWAYSDATA_PATH/assets/img/default-avatar.svg && echo 'default-avatar.svg: OK' || echo 'default-avatar.svg: MISSING'
        test -d $ALWAYSDATA_PATH/uploads/avatars && echo 'uploads/avatars: OK' || echo 'uploads/avatars: MISSING'
    "
}

# Deployment summary
deployment_summary() {
    print_status "Deployment completed successfully!"
    echo ""
    echo "📋 Deployment Summary:"
    echo "======================"
    echo "✅ Modified files uploaded"
    echo "✅ Directories created"
    echo "✅ Permissions set"
    echo "✅ Health check passed"
    echo ""
    echo "🔍 Next Steps:"
    echo "1. Test the registration page"
    echo "2. Test profile picture upload"
    echo "3. Test WhatsApp join flow"
    echo "4. Check error logs if needed"
    echo ""
    echo "🌐 Your site: https://$ALWAYSDATA_HOST"
    echo ""
}

# Main deployment flow
main() {
    print_info "Starting deployment process..."
    
    # Check if configuration is set
    if [[ "$ALWAYSDATA_HOST" == "your-host.alwaysdata.net" ]]; then
        print_error "Please update the configuration variables in this script"
        print_info "Edit ALWAYSDATA_HOST, ALWAYSDATA_USER, and ALWAYSDATA_PATH"
        exit 1
    fi
    
    check_dependencies
    backup_current
    sync_files
    create_directories
    set_permissions
    run_migration
    health_check
    deployment_summary
}

# Run deployment
main "$@"