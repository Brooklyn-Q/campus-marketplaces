# 🚀 Alwaysdata Deployment Guide

## 📋 Overview

This guide will help you deploy the profile picture and WhatsApp join fixes to your alwaysdata hosting. The fixes include:

- ✅ Profile picture preview functionality on registration
- ✅ Enhanced WhatsApp channel join flow with validation
- ✅ Default avatar image for users without profile pictures
- ✅ Improved error handling and user feedback

## 🎯 Deployment Goals

After deployment, users will be able to:
1. **Preview profile pictures** during registration (sellers only)
2. **Join WhatsApp channel** with proper validation
3. **See default avatars** instead of broken images
4. **Experience smooth onboarding** with better error handling

## 📁 Files to Deploy

### Modified Files
```
register.php              - Added profile picture preview
whatsapp_join.php         - Enhanced validation & debugging  
edit_profile.php          - Updated default avatar path
```

### New Files
```
assets/img/default-avatar.svg - Default avatar image
```

### Deployment Files (for your reference)
```
DEPLOYMENT_CHECKLIST.md      - Comprehensive checklist
deploy_database_migration.sql - Database migration script
deploy.sh / deploy.bat        - Automated deployment scripts
check_requirements.php       - Server requirements checker
DEPLOYMENT_GUIDE.md          - This guide
```

## 🔧 Prerequisites

### Server Requirements
- **PHP**: 7.4 or higher
- **Database**: PostgreSQL 
- **Extensions**: PDO, PDO_PGSQL, GD, fileinfo, mbstring, json, session
- **Permissions**: Write access to upload directories

### Tools Needed
- **FTP/SFTP Client**: FileZilla, WinSCP, or similar
- **Database Access**: alwaysdata admin panel or SSH
- **Text Editor**: For configuration changes

## 📋 Step-by-Step Deployment

### Step 1: Pre-Deployment Checks

1. **Run requirements checker locally:**
   ```bash
   # Open in browser
   http://localhost/marketplace/check_requirements.php
   ```

2. **Verify all functionality works locally:**
   - Test profile picture upload and preview
   - Test WhatsApp join flow
   - Check error logs

3. **Backup current version:**
   - Download existing files from alwaysdata
   - Export database (optional but recommended)

### Step 2: Upload Files

#### Option A: Using FileZilla (Manual)
1. Connect to your alwaysdata SFTP/FTP
2. Navigate to your website directory
3. Upload the modified files:
   - `register.php`
   - `whatsapp_join.php` 
   - `edit_profile.php`
   - `assets/img/default-avatar.svg`

#### Option B: Using WinSCP (Windows)
1. Open WinSCP and connect to alwaysdata
2. Use the provided `deploy.bat` script:
   ```cmd
   # Edit deploy.bat with your credentials first
   deploy.bat
   ```

#### Option C: Using SSH (Linux/Mac)
1. Make the deploy script executable:
   ```bash
   chmod +x deploy.sh
   ```
2. Edit the script with your credentials
3. Run the deployment:
   ```bash
   ./deploy.sh
   ```

### Step 3: Create Directories & Set Permissions

Create these directories if they don't exist:
```bash
mkdir -p uploads/avatars
mkdir -p uploads/banners
```

Set appropriate permissions:
```bash
chmod 755 uploads
chmod 755 uploads/avatars  
chmod 755 uploads/banners
chmod 644 *.php
chmod 644 assets/img/default-avatar.svg
```

### Step 4: Database Migration

Run the migration script to ensure your database has the required columns:

#### Option A: Via alwaysdata Admin Panel
1. Go to alwaysdata admin panel
2. Navigate to PostgreSQL management
3. Open SQL interface
4. Copy and paste the contents of `deploy_database_migration.sql`
5. Execute the script

#### Option B: Via SSH
```bash
# Connect to your alwaysdata server via SSH
psql -h localhost -U your_db_user -d your_database_name -f deploy_database_migration.sql
```

### Step 5: Post-Deployment Testing

1. **Test Registration Flow:**
   - Go to your registration page
   - Select "seller" mode
   - Try uploading a profile picture
   - Verify the preview works
   - Complete registration

2. **Test WhatsApp Join:**
   - After registration, you should be redirected to WhatsApp join
   - Click the WhatsApp channel link
   - Verify the continue button becomes enabled
   - Complete the join process

3. **Test Edit Profile:**
   - Go to edit profile page
   - Verify default avatar shows for users without pictures
   - Test profile picture upload and preview

4. **Check Error Logs:**
   - Monitor alwaysdata error logs
   - Check for any PHP errors or warnings

## 🔍 Troubleshooting

### Common Issues & Solutions

#### Profile Picture Preview Not Working
**Symptoms:** Preview doesn't appear when selecting image
**Solutions:**
- Check browser console for JavaScript errors
- Verify `default-avatar.svg` is accessible
- Ensure file paths are correct
- Check if `previewSelectedImage` function exists in page source

#### WhatsApp Join Button Not Enabling
**Symptoms:** Continue button stays disabled after clicking WhatsApp link
**Solutions:**
- Check browser console for JavaScript errors
- Verify `enableConfirm()` function exists
- Check if onclick handlers are working
- Test in different browser

#### File Upload Fails
**Symptoms:** Profile picture upload fails with permission error
**Solutions:**
- Check directory permissions (755 or 775)
- Verify upload directories exist
- Check PHP upload settings
- Review server error logs

#### Database Errors
**Symptoms:** SQL errors or missing columns
**Solutions:**
- Run the migration script
- Verify database connection
- Check if all required columns exist
- Review PostgreSQL logs

### Getting Help

If you encounter issues:

1. **Check the logs:**
   - Alwaysdata admin panel → Logs
   - Browser developer console (F12)

2. **Verify file integrity:**
   - Compare local files with uploaded files
   - Check file sizes match

3. **Test in isolation:**
   - Create a simple test file to verify basic functionality
   - Test database connection separately

4. **Rollback if needed:**
   - Restore files from backup
   - Revert database changes if migration was run

## ✅ Deployment Checklist

Before going live, verify:

- [ ] All modified files uploaded successfully
- [ ] Upload directories created with proper permissions
- [ ] Database migration completed without errors
- [ ] Profile picture preview works on registration page
- [ ] WhatsApp join flow completes successfully
- [ ] Default avatar displays correctly
- [ ] No PHP errors in logs
- [ ] No JavaScript errors in browser console
- [ ] All functionality tested on mobile and desktop

## 📊 Post-Deployment Monitoring

After deployment, monitor:

1. **User Registration Rate:** Should remain stable or improve
2. **Profile Picture Uploads:** Should increase with new preview feature
3. **WhatsApp Join Completion:** Should improve with better validation
4. **Error Rates:** Should decrease with improved error handling
5. **User Feedback:** Collect feedback on new features

## 🔄 Future Updates

When deploying future updates:

1. Always backup before deploying
2. Test in staging environment first
3. Use the same deployment process
4. Update this documentation with any changes

## 📞 Support Resources

- **Alwaysdata Documentation:** https://alwaysdata.com/documentation/
- **PHP Documentation:** https://www.php.net/docs.php
- **PostgreSQL Documentation:** https://www.postgresql.org/docs/

---

## 🎉 Deployment Complete!

Once you've completed all steps and verified everything works, your deployment is complete! Users will now enjoy:

- 📸 **Live profile picture preview** during registration
- 📱 **Smooth WhatsApp channel joining** with validation
- 🖼️ **Professional default avatars** for all users
- 🛡️ **Better error handling** and user feedback

Congratulations on deploying these improvements! 🚀