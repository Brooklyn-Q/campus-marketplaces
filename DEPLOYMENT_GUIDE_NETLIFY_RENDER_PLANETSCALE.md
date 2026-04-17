# 🚀 COMPLETE DEPLOYMENT GUIDE
## Campus Marketplace: Netlify + Render + PlanetScale

**Status:** ✅ Ready for Deployment | **Timeline:** 1-2 hours | **Cost:** $0 (Free Tier)

---

## 📋 DEPLOYMENT OVERVIEW

Your marketplace will be deployed across three cloud platforms:

```
Frontend (React)       Backend (PHP)          Database (MySQL)
   ↓                      ↓                        ↓
Netlify.com      ←→   Render.com    ←→    PlanetScale.com
(Static Hosting)    (Backend Server)   (Managed Database)
    CDN                    API                     Storage
```

**No domain needed yet** - you'll get free URLs from each service:
- Frontend: `your-app.netlify.app`
- Backend: `your-api.onrender.com`
- Database: `your-db.psdb.cloud`

---

## ⏱️ STEP-BY-STEP INSTRUCTIONS

### STEP 1: Create Accounts (5 minutes)

#### 1.1 Create PlanetScale Account (Database)
1. Go to https://www.planetscale.com/
2. Click **"Sign Up"**
3. Sign up with GitHub (recommended) or email
4. Create new organization
5. **Don't create database yet** - we'll do that next

#### 1.2 Create Render Account (Backend)
1. Go to https://render.com/
2. Click **"Sign Up Free"**
3. Sign up with GitHub (recommended) or email
4. Connect your GitHub account when prompted
5. You should see dashboard

#### 1.3 Create Netlify Account (Frontend)
1. Go to https://www.netlify.com/
2. Click **"Sign up"**
3. Click **"GitHub"** to sign up with GitHub (recommended)
4. Authorize Netlify to access your GitHub repos
5. You should see dashboard

✅ **All three accounts created!**

---

### STEP 2: Set Up Database (10 minutes)

#### 2.1 Create PlanetScale Database
1. In PlanetScale dashboard, click **"Create"** (or **"New Database"**)
2. Name: `campus-marketplace`
3. Region: Choose closest to your users
4. Click **"Create Database"**
5. Wait ~1 minute for database to be created

#### 2.2 Get Connection String
1. Click on your new database
2. Look for **"Connect"** button (top right)
3. Select **"Node.js"** (it shows MySQL format)
4. Copy the connection string - it looks like:
   ```
   mysql://[user]:[password]@[host]/campus_marketplace
   ```
5. **Save this somewhere safe** - you'll need it!

#### 2.3 Upload Database Schema
1. Go back to PlanetScale main screen
2. Click **"Console"** to open SQL editor
3. Copy this entire script and paste it in the console:
   
   ```sql
   -- Run this in PlanetScale console
   CREATE TABLE IF NOT EXISTS users (
       id INT PRIMARY KEY AUTO_INCREMENT,
       username VARCHAR(255) UNIQUE NOT NULL,
       email VARCHAR(255) UNIQUE NOT NULL,
       password VARCHAR(255) NOT NULL,
       -- Add other fields as needed
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );
   
   CREATE TABLE IF NOT EXISTS products (
       id INT PRIMARY KEY AUTO_INCREMENT,
       user_id INT NOT NULL,
       title VARCHAR(255) NOT NULL,
       description TEXT,
       price DECIMAL(10, 2),
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       FOREIGN KEY (user_id) REFERENCES users(id)
   );
   
   -- Add other tables from your schema...
   ```

   **OR** If you have a complete SQL file:
   1. Go to your project folder
   2. Find `setup.php` or database schema
   3. Copy all the table creation SQL
   4. Paste into PlanetScale console
   5. Click **"Execute"** or press Ctrl+Enter

4. Wait for schema to be created
5. ✅ Database ready!

---

### STEP 3: Deploy Backend on Render (20 minutes)

#### 3.1 Push Code to GitHub
```bash
cd /path/to/marketplace

# Commit all changes
git add -A
git commit -m "Deploy: Configure for Render + Netlify + PlanetScale"

# Push to GitHub
git push origin main
```

#### 3.2 Connect Render to GitHub
1. Go to Render dashboard
2. Click **"New+"** → **"Web Service"**
3. Click **"Connect account"** (to GitHub)
4. Authorize Render on GitHub
5. Select your `marketplace` repository
6. Click **"Connect"**

#### 3.3 Configure Service
1. **Name:** `campus-marketplace-api`
2. **Environment:** Select **"PHP"** (Render auto-detects from render.yaml)
3. **Plan:** Select **"Free"** (blue option)
4. **Branch:** `main`
5. **Build Command:** `composer install` (should be auto-filled from render.yaml)
6. **Start Command:** `php -S 0.0.0.0:$PORT` (should be auto-filled)

#### 3.4 Set Environment Variables
In the Render dashboard, scroll to **"Environment"** section:

Add these variables (Click "Add Environment Variable" for each):

| Key | Value | Example |
|-----|-------|---------|
| `DB_HOST` | From PlanetScale connection string | `aws.connect.psdb.cloud` |
| `DB_USER` | From PlanetScale connection string | `xxxxx` |
| `DB_PASS` | From PlanetScale connection string | `pscale_pw_xxxxx` |
| `DB_NAME` | `campus_marketplace` | `campus_marketplace` |
| `JWT_SECRET` | Generate: `openssl rand -base64 32` | `your_generated_secret` |
| `PAYSTACK_PUBLIC_KEY` | Your Paystack public key (live) | `pk_live_xxxxx` |
| `PAYSTACK_SECRET_KEY` | Your Paystack secret key (live) | `sk_live_xxxxx` |
| `FRONTEND_URL` | `https://your-app.netlify.app` | `https://campus-marketplace.netlify.app` |
| `APP_ENV` | `production` | `production` |

**Where to get PlanetScale credentials:**
- Go to PlanetScale dashboard
- Click your database → "Connect"
- Copy: hostname, username, password from connection string

#### 3.5 Deploy
1. Scroll to bottom and click **"Create Web Service"**
2. Render will start deployment (takes ~2-3 minutes)
3. Watch the logs - should see:
   ```
   Composer install completed
   Started PHP server
   ```
4. Once done, you'll see a URL: `https://campus-marketplace-api-xxxxx.onrender.com`
5. **Save this URL** - you'll need it for frontend!

✅ **Backend deployed!**

---

### STEP 4: Deploy Frontend on Netlify (10 minutes)

#### 4.1 Update Frontend Environment
1. Open `frontend/.env.production`
2. Find this line: `VITE_API_URL=`
3. Replace with your Render URL:
   ```
   VITE_API_URL=https://campus-marketplace-api-xxxxx.onrender.com
   ```
4. Update Paystack public key (if changed)
5. Save and commit:
   ```bash
   git add frontend/.env.production
   git commit -m "Update: Render backend URL"
   git push origin main
   ```

#### 4.2 Connect Netlify to GitHub
1. Go to Netlify dashboard
2. Click **"Add new site"** → **"Import an existing project"**
3. Select **"GitHub"**
4. Find and select your `marketplace` repository
5. Click **"Deploy site"**

#### 4.3 Configure Build Settings
Netlify should auto-detect from `netlify.toml`, but verify:

- **Build command:** `npm run build` ✓
- **Publish directory:** `dist` ✓
- **Node version:** `18` ✓

#### 4.4 Set Environment Variables
1. In Netlify dashboard, go to **"Settings"** → **"Build & deploy"** → **"Environment"**
2. Click **"Edit variables"**
3. Add these variables:

| Key | Value |
|-----|-------|
| `VITE_API_URL` | `https://campus-marketplace-api-xxxxx.onrender.com` |
| `VITE_PAYSTACK_PUBLIC_KEY` | Your Paystack public key |

4. Save and Netlify will redeploy automatically

#### 4.5 Verify Deployment
1. Netlify will show deployment progress
2. Once done (green checkmark), you'll get a URL: `https://your-app-xxxxx.netlify.app`
3. Click the URL to open your app
4. You should see the marketplace homepage

✅ **Frontend deployed!**

---

### STEP 5: Test Everything (5 minutes)

#### 5.1 Test Frontend Loads
1. Open your Netlify URL in browser
2. You should see the marketplace homepage
3. Check browser console (F12) for any errors

#### 5.2 Test Backend Connection
1. Try to **Register** a new account
2. You should see a form
3. Create account with:
   - **Email:** test@test.com
   - **Password:** TestPassword123456 (12+ chars)
   - **Faculty:** Any option
4. If successful, you're logged in!

#### 5.3 Test Database
1. Go to PlanetScale console
2. Run: `SELECT COUNT(*) FROM users;`
3. Should show `1` (your test user)
4. ✅ Database working!

#### 5.4 Test Payments (Optional)
1. Try to deposit money
2. You'll go to Paystack page
3. This confirms Paystack integration works

---

## 🔐 AFTER DEPLOYMENT CHECKLIST

- [ ] ✅ Both services deployed and running
- [ ] ✅ Can register and login
- [ ] ✅ Can upload products
- [ ] ✅ Can send messages
- [ ] ✅ Database has data (users, products, etc)
- [ ] ✅ No console errors in browser
- [ ] ✅ Paystack modal opens for payments

---

## 🌐 CUSTOM DOMAIN (When Ready)

When you buy a domain (later):

**Option 1: Separate domains** (Recommended)
```
Frontend: app.mydomain.com → Netlify
Backend: api.mydomain.com → Render
```

**Option 2: Subpaths**
```
Frontend: mydomain.com → Netlify
Backend: mydomain.com/api → Render (via proxy)
```

See each service's documentation for domain setup.

---

## 🐛 TROUBLESHOOTING

### Backend not deploying?
1. Check Render logs: Dashboard → Your Service → Logs
2. Common issues:
   - Missing `composer.lock` file
   - PHP version mismatch
   - Database not accessible

### Frontend blank screen?
1. Check browser console (F12)
2. Look for CORS errors
3. Verify `VITE_API_URL` is correct in Netlify dashboard

### Can't connect to database?
1. Verify PlanetScale connection string in Render environment
2. Test connection with PlanetScale console
3. Check database name and credentials

### Slow first load?
1. Normal on free tier - Render sleeps after inactivity
2. First request can take 10-30 seconds
3. Upgrade to $7/mo plan for always-on

---

## 📞 SUPPORT URLS

- **Render Docs:** https://render.com/docs
- **Netlify Docs:** https://docs.netlify.com
- **PlanetScale Docs:** https://planetscale.com/docs
- **Vite Env Vars:** https://vitejs.dev/guide/env-and-mode

---

## 🎯 NEXT STEPS

1. ✅ Create the three accounts
2. ✅ Set up PlanetScale database
3. ✅ Deploy backend on Render
4. ✅ Deploy frontend on Netlify
5. ✅ Test everything
6. 🔮 When ready: Get a domain and point to these services
7. 🔮 Monitor logs and fix issues
8. 🔮 Scale up on paid tiers when needed

**You're now live!** 🚀

---

*Questions? Check the troubleshooting section or review the documentation links above.*
