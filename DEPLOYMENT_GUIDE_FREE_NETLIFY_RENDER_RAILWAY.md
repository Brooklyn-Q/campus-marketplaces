# 🚀 COMPLETE DEPLOYMENT GUIDE (UPDATED)
## Campus Marketplace: Netlify + Render + Railway

**Status:** ✅ Ready for Deployment | **Timeline:** 1-2 hours | **Cost:** $0 (Completely Free)

---

## 📋 DEPLOYMENT OVERVIEW

Your marketplace will be deployed across three cloud platforms (ALL FREE):

```
Frontend (React)       Backend (PHP)          Database (MySQL)
   ↓                      ↓                        ↓
Netlify.com      ←→   Render.com    ←→    Railway.app
(Static Hosting)    (Backend Server)   (Managed Database)
    CDN                    API            Free MySQL $5/mo
```

**Everything is FREE!** Railway gives $5/month free credits every month.

---

## ⏱️ STEP-BY-STEP INSTRUCTIONS

### STEP 1: Create Accounts (5 minutes)

#### 1.1 Create Railway Account (Database)
1. Go to https://railway.app/
2. Click **"Start Free"** or **"Sign Up"**
3. Sign up with GitHub (recommended) or email
4. Authorize Railway to access your GitHub
5. You'll see dashboard

#### 1.2 Create Render Account (Backend)
1. Go to https://render.com/
2. Click **"Sign Up Free"**
3. Sign up with GitHub (recommended) or email
4. Connect your GitHub account
5. You'll see dashboard

#### 1.3 Create Netlify Account (Frontend)
1. Go to https://www.netlify.com/
2. Click **"Sign up"**
3. Click **"GitHub"** to sign up with GitHub
4. Authorize Netlify to access your GitHub repos
5. You'll see dashboard

✅ **All three accounts created!**

---

### STEP 2: Set Up Database on Railway (10 minutes)

#### 2.1 Create MySQL Database
1. In Railway dashboard, click **"Create"** (or **"New Project"**)
2. Look for **"Provision"** or **"Add"** → Search **"MySQL"**
3. Click **"MySQL"**
4. Railway will create your MySQL database automatically
5. Wait ~1 minute for database to be ready

#### 2.2 Get Connection Details
1. In Railway dashboard, find your MySQL service
2. Click on it
3. Look for **"Variables"** or **"Connection"** tab
4. You'll see:
   ```
   MYSQL_HOST=railway.app
   MYSQL_PORT=3306
   MYSQL_USER=root
   MYSQL_PASSWORD=xxxxx
   MYSQL_DATABASE=railway
   ```
5. **Copy these values** - you'll need them for Render

#### 2.3 Upload Your Database Schema
1. In Railway dashboard, find **"Command"** or **"Shell"** option
2. Or use MySQL Workbench to connect:
   ```
   Host: [MYSQL_HOST from above]
   User: [MYSQL_USER]
   Password: [MYSQL_PASSWORD]
   Database: [MYSQL_DATABASE]
   ```

3. Run your database setup SQL:
   - Either from `setup.php` script
   - Or from your database schema file
   - This creates all tables (users, products, orders, etc)

4. ✅ Database ready!

---

### STEP 3: Deploy Backend on Render (20 minutes)

#### 3.1 Push Code to GitHub
```bash
cd /path/to/marketplace

# Make sure all changes are committed
git add -A
git commit -m "Deploy: Final config for Render + Netlify + Railway"

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
2. **Environment:** Select **"PHP"** 
3. **Plan:** Select **"Free"** (blue option)
4. **Branch:** `main`
5. **Build Command:** `composer install`
6. **Start Command:** `php -S 0.0.0.0:$PORT`

#### 3.4 Set Environment Variables
In the Render dashboard, scroll to **"Environment"** section:

Add these variables (Click "Add Environment Variable" for each):

| Key | Value | From Railway |
|-----|-------|-------------|
| `DB_HOST` | From Railway MYSQL_HOST | `railway.app` |
| `DB_USER` | From Railway MYSQL_USER | `root` |
| `DB_PASS` | From Railway MYSQL_PASSWORD | `pscale_pw_...` |
| `DB_NAME` | From Railway MYSQL_DATABASE | `railway` |
| `JWT_SECRET` | Generate: `openssl rand -base64 32` | Your choice |
| `PAYSTACK_PUBLIC_KEY` | Your Paystack public key | `pk_live_xxxxx` |
| `PAYSTACK_SECRET_KEY` | Your Paystack secret key | `sk_live_xxxxx` |
| `FRONTEND_URL` | `https://your-app.netlify.app` | Your choice |
| `APP_ENV` | `production` | `production` |

**How to get Railway values:**
1. Go to Railway dashboard
2. Click on your MySQL service
3. Click **"Variables"** tab
4. Copy MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE

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
2. Find: `VITE_API_URL=`
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
Netlify should auto-detect from `netlify.toml`:
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
1. Go to Railway dashboard
2. Click on MySQL service → **"Shell"** or use MySQL client
3. Run: `SELECT COUNT(*) FROM users;`
4. Should show `1` (your test user)
5. ✅ Database working!

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
- [ ] ✅ Database has data (check Railway)
- [ ] ✅ No console errors in browser
- [ ] ✅ Paystack modal opens for payments

---

## 💰 COST BREAKDOWN (100% FREE)

| Service | Free Tier | Monthly Cost |
|---------|-----------|-------------|
| **Railway** | $5 credits/month | $0 (free tier) |
| **Render** | Free web service | $0 (sleeps after 15 min) |
| **Netlify** | Unlimited static | $0 (always free) |
| **Domain** | Not needed yet | $0 |
| **TOTAL** | **Everything** | **$0/month** |

When you scale later (optional):
- Railway: Pay for what you use (~$0-10/mo for MVP)
- Render: $7/mo (always-on)
- Netlify: $19/mo (optional pro features)

---

## 🐛 TROUBLESHOOTING

### Can't connect to Railway database?
1. Check Railway connection variables are correct
2. Verify they match in Render environment
3. Test connection with MySQL client:
   ```bash
   mysql -h [HOST] -u [USER] -p [PASSWORD]
   ```

### Backend not deploying?
1. Check Render logs: Dashboard → Your Service → Logs
2. Common issues:
   - Missing `composer.lock`
   - PHP version issues
   - Database not accessible

### Frontend blank screen?
1. Check browser console (F12)
2. Look for CORS errors
3. Verify `VITE_API_URL` in Netlify dashboard is correct

### Slow first load?
1. Normal on free tier
2. Render sleeps after 15 min of inactivity
3. First request takes 10-30 seconds to wake up
4. Upgrade to $7/mo for always-on

---

## 🎯 NEXT STEPS

1. ✅ Create the three accounts
2. ✅ Set up Railway database
3. ✅ Deploy backend on Render
4. ✅ Deploy frontend on Netlify
5. ✅ Test everything
6. 🔮 When ready: Get a domain
7. 🔮 Monitor logs and fix issues

**You're now live!** 🚀

---

*All three services are FREE. You get $5/month credits on Railway which is more than enough for MVP phase.*
