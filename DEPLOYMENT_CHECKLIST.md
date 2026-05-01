# 🚀 Alwaysdata Deployment Checklist

## 📋 **Files Modified - Need to Upload**

### Core PHP Files
- ✅ `register.php` - Added profile picture preview functionality
- ✅ `whatsapp_join.php` - Enhanced validation and debugging
- ✅ `edit_profile.php` - Updated default avatar path

### New Assets
- ✅ `assets/img/default-avatar.svg` - New default avatar image

## 🔍 **Pre-Deployment Verification**

### 1. Local Testing Checklist
- [ ] Test profile picture upload and preview on registration page
- [ ] Test WhatsApp join flow end-to-end
- [ ] Verify all JavaScript functions work in browser
- [ ] Check file upload permissions work correctly
- [ ] Test database operations complete successfully

### 2. Server Requirements Check
- [ ] PHP version >= 7.4 (preferably 8.0+)
- [ ] PostgreSQL database access
- [ ] GD library for image processing
- [ ] File upload permissions enabled
- [ ] Proper write permissions for upload directories

## 🗄️ **Database Compatibility**

### Required Columns (Verify these exist on alwaysdata)
```sql
-- Check these columns exist in users table:
- whatsapp_joined (BOOLEAN, DEFAULT false)
- terms_accepted (BOOLEAN, DEFAULT false)  
- profile_pic (VARCHAR, DEFAULT NULL)
```

### Database Migration (if needed)
```sql
-- Run these if columns don't exist:
ALTER TABLE users ADD COLUMN IF NOT EXISTS whatsapp_joined BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS terms_accepted BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_pic VARCHAR(255) DEFAULT NULL;
```

## 📁 **Directory Structure & Permissions**

### Required Directories (Create if not exist)
```
uploads/
├── avatars/     (chmod 755 or 775)
└── banners/     (chmod 755 or 775)
assets/
└── img/
    └── default-avatar.svg
```

### Permission Settings
- `uploads/` - 755 (or 775)
- `uploads/avatars/` - 755 (or 775) 
- `uploads/banners/` - 755 (or 775)
- PHP files - 644
- SVG file - 644

## 🔧 **Configuration Updates**

### Database Configuration
- [ ] Update database credentials in alwaysdata panel
- [ ] Verify PostgreSQL connection string
- [ ] Test database connectivity

### File Upload Settings
Check these PHP settings on alwaysdata:
```ini
file_uploads = On
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
```

## 📤 **Deployment Steps**

### Step 1: Backup Current Version
- [ ] Backup existing files on alwaysdata
- [ ] Export current database (if needed)

### Step 2: Upload Files
- [ ] Upload modified PHP files
- [ ] Upload new default avatar SVG
- [ ] Verify file integrity after upload

### Step 3: Setup Directories
- [ ] Create upload directories if missing
- [ ] Set proper permissions
- [ ] Test directory writability

### Step 4: Database Setup
- [ ] Run migration scripts if needed
- [ ] Verify database schema
- [ ] Test database connectivity

### Step 5: Post-Deployment Testing
- [ ] Test user registration flow
- [ ] Test profile picture upload
- [ ] Test WhatsApp join flow
- [ ] Test edit profile functionality
- [ ] Check error logs for issues

## ⚠️ **Common Issues & Solutions**

### File Upload Issues
- **Problem**: Permission denied errors
- **Solution**: Check directory permissions, ensure 755/775

### Database Issues  
- **Problem**: Connection failed
- **Solution**: Verify credentials, check PostgreSQL settings

### Image Processing Issues
- **Problem**: GD functions not working
- **Solution**: Ensure GD library installed on server

### JavaScript Issues
- **Problem**: Preview not working
- **Solution**: Check browser console for errors, verify file paths

## 🆘 **Rollback Plan**

If deployment fails:
1. Restore original files from backup
2. Remove any new directories created
3. Revert database changes if migration was run
4. Test that original functionality is restored

## ✅ **Final Verification**

After deployment, verify:
- [ ] Registration page loads without errors
- [ ] Profile picture preview works
- [ ] File uploads succeed
- [ ] WhatsApp join flow completes
- [ ] No PHP errors in logs
- [ ] No JavaScript errors in browser console

---

## 📞 **Support Information**

If you encounter issues during deployment:
1. Check alwaysdata error logs
2. Verify file permissions
3. Test database connection
4. Check PHP error reporting
5. Review browser console for JavaScript errors

## 🔄 **Version Information**

- **Deployment Date**: [Add date]
- **Version**: 1.1 (Profile Picture & WhatsApp Fixes)
- **Changes**: Enhanced registration, profile preview, WhatsApp validation