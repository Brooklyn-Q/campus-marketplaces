# 🚀 COMPLETE DEPLOYMENT GUIDE (SUPABASE)
## Campus Marketplace: Netlify + Render + Supabase

**Status:** ✅ Ready for Deployment | **Timeline:** 1-2 hours | **Cost:** $0 (Completely Free)

---

## 📋 DEPLOYMENT OVERVIEW

Your marketplace will be deployed across three cloud platforms (ALL FREE):

```
Frontend (React)       Backend (PHP)          Database (PostgreSQL)
   ↓                      ↓                        ↓
Netlify.com      ←→   Render.com    ←→    Supabase.com
(Static Hosting)    (Backend Server)   (Managed PostgreSQL)
    CDN                    API            Free Tier Perfect
```

**Everything is FREE!** Supabase free tier is generous and production-ready.

---

## ✨ WHY SUPABASE?

| Feature | Supabase | Railway | PlanetScale |
|---------|----------|---------|------------|
| **Type** | PostgreSQL | MySQL | MySQL |
| **Free Tier** | ✅ VERY GENEROUS | Free + $5/mo | Limited |
| **Storage** | 500MB | Limited | Limited |
| **Real-time** | ✅ YES (bonus!) | NO | NO |
| **Auth** | ✅ Built-in | NO | NO |
| **REST API** | ✅ Auto-generated | NO | NO |
| **Cost** | $0 | $0 | Paid |

**Supabase free tier includes:**
- 500MB database storage
- Real-time subscriptions
- Built-in authentication
- Auto-generated REST API
- No credit card required

---

## ⏱️ STEP-BY-STEP INSTRUCTIONS

### STEP 1: Create Accounts (5 minutes)

#### 1.1 Create Supabase Account (Database)
1. Go to https://supabase.com/
2. Click **"Start your project"** or **"Sign Up"**
3. Sign up with GitHub (recommended) or email
4. Create new organization
5. You'll see Supabase dashboard

#### 1.2 Create Render Account (Backend)
1. Go to https://render.com/
2. Click **"Sign Up Free"**
3. Sign up with GitHub (recommended)
4. Connect GitHub account
5. Dashboard ready

#### 1.3 Create Netlify Account (Frontend)
1. Go to https://www.netlify.com/
2. Click **"Sign up"**
3. Sign up with GitHub
4. Authorize Netlify
5. Dashboard ready

✅ **All three accounts created!**

---

### STEP 2: Set Up Database on Supabase (15 minutes)

#### 2.1 Create Supabase Project
1. In Supabase dashboard, click **"New project"**
2. **Project name:** `campus-marketplace`
3. **Database password:** Create strong password (save it!)
4. **Region:** Choose closest to you
5. Click **"Create new project"**
6. Wait ~2 minutes for database to initialize

#### 2.2 Get Connection Details
1. In Supabase dashboard, go to **"Settings"** → **"Database"**
2. Find **"Connection string"** section
3. You'll see different formats:
   ```
   postgresql://postgres:[PASSWORD]@[HOST]:5432/postgres
   ```
4. Copy these values:
   - **Host:** `[HOST]` from above
   - **User:** `postgres`
   - **Password:** The password you created
   - **Database:** `postgres`
   - **Port:** `5432`

5. **Save these** - you'll need them for Render

#### 2.3 Create Database Tables
1. In Supabase, go to **"SQL Editor"** (left sidebar)
2. Click **"New Query"**
3. Copy and paste your database schema (all CREATE TABLE statements)
4. Or use this basic schema to start:

```sql
-- Users table
CREATE TABLE users (
  id BIGSERIAL PRIMARY KEY,
  username VARCHAR(255) UNIQUE NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(50) DEFAULT 'buyer',
  seller_tier VARCHAR(50) DEFAULT 'basic',
  balance DECIMAL(10, 2) DEFAULT 0,
  profile_pic VARCHAR(500),
  verified BOOLEAN DEFAULT FALSE,
  suspended BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT NOT NULL REFERENCES users(id),
  title VARCHAR(255) NOT NULL,
  description TEXT,
  price DECIMAL(10, 2) NOT NULL,
  category VARCHAR(100),
  quantity INT DEFAULT 1,
  status VARCHAR(50) DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Orders table
CREATE TABLE orders (
  id BIGSERIAL PRIMARY KEY,
  buyer_id BIGINT NOT NULL REFERENCES users(id),
  seller_id BIGINT NOT NULL REFERENCES users(id),
  product_id BIGINT NOT NULL REFERENCES products(id),
  quantity INT DEFAULT 1,
  status VARCHAR(50) DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Messages table
CREATE TABLE messages (
  id BIGSERIAL PRIMARY KEY,
  sender_id BIGINT NOT NULL REFERENCES users(id),
  receiver_id BIGINT NOT NULL REFERENCES users(id),
  message TEXT,
  is_read BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Transactions table
CREATE TABLE transactions (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT NOT NULL REFERENCES users(id),
  type VARCHAR(50),
  amount DECIMAL(10, 2),
  status VARCHAR(50) DEFAULT 'pending',
  reference VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Reviews table
CREATE TABLE reviews (
  id BIGSERIAL PRIMARY KEY,
  product_id BIGINT NOT NULL REFERENCES products(id),
  buyer_id BIGINT NOT NULL REFERENCES users(id),
  rating INT CHECK (rating >= 1 AND rating <= 5),
  comment TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Settings table
CREATE TABLE settings (
  id BIGSERIAL PRIMARY KEY,
  setting_key VARCHAR(255) UNIQUE NOT NULL,
  setting_value TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Audit logs
CREATE TABLE security_logs (
  id BIGSERIAL PRIMARY KEY,
  event_type VARCHAR(50),
  description TEXT,
  user_id BIGINT REFERENCES users(id),
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Payment verification logs
CREATE TABLE payment_verification_logs (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT NOT NULL REFERENCES users(id),
  reference VARCHAR(100) UNIQUE,
  status VARCHAR(50) DEFAULT 'pending',
  error_message TEXT,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

5. Click **"Run"** or press Ctrl+Enter
6. Wait for tables to be created
7. ✅ Database schema ready!

#### 2.4 Verify Tables Created
1. Go to **"Table Editor"** (left sidebar)
2. You should see all your tables listed
3. Click each to verify structure

✅ **Database setup complete!**

---

### STEP 3: Update PHP Code for PostgreSQL (5 minutes)

Your code uses MySQL but Supabase uses PostgreSQL. They're similar, but need a small fix:

#### 3.1 Update database connection (already done!)
Your `backend/config/database.php` already supports both:
```php
$dsn = "pgsql:host=$host;port=$port;dbname=$name";  // PostgreSQL
// or
$dsn = "mysql:host=$host;port=$port;dbname=$name";  // MySQL
```

#### 3.2 Update a few SQL functions
Some functions need PostgreSQL syntax:

**Change this:**
```php
LAST_INSERT_ID()  → LASTVAL()
AUTO_INCREMENT    → SERIAL (already done in schema above)
```

But your code mostly uses PDO, so it works!

#### 3.3 Commit changes
```bash
cd /path/to/marketplace
git add -A
git commit -m "Update: PostgreSQL support for Supabase"
git push origin main
```

✅ **Code ready for PostgreSQL!**

---

### STEP 4: Deploy Backend on Render (20 minutes)

#### 4.1 Push Code to GitHub
```bash
# Already did this above ✓
git push origin main
```

#### 4.2 Connect Render to GitHub
1. Go to Render dashboard
2. Click **"New+"** → **"Web Service"**
3. Click **"Connect account"**
4. Select your `marketplace` repository
5. Click **"Connect"**

#### 4.3 Configure Service
1. **Name:** `campus-marketplace-api`
2. **Environment:** Select **"PHP"**
3. **Plan:** Select **"Free"**
4. **Branch:** `main`
5. **Build Command:** `composer install`
6. **Start Command:** `php -S 0.0.0.0:$PORT`

#### 4.4 Set Environment Variables
In Render, add these (from Supabase):

| Key | Value | From Supabase |
|-----|-------|---------------|
| `DB_HOST` | Your Supabase host | Settings → Database → Host |
| `DB_USER` | `postgres` | postgres |
| `DB_PASS` | Your database password | The password you created |
| `DB_NAME` | `postgres` | postgres |
| `DB_PORT` | `5432` | 5432 |
| `JWT_SECRET` | Generate: `openssl rand -base64 32` | Your choice |
| `PAYSTACK_PUBLIC_KEY` | Your Paystack key | pk_live_xxxxx |
| `PAYSTACK_SECRET_KEY` | Your Paystack key | sk_live_xxxxx |
| `FRONTEND_URL` | Your Netlify URL | https://app-xxxxx.netlify.app |
| `APP_ENV` | `production` | production |

**How to get Supabase connection details:**
1. In Supabase, go to **Settings** → **Database** → **Connection info**
2. Copy each value to corresponding Render variable

#### 4.5 Deploy
1. Click **"Create Web Service"**
2. Render starts deployment (~2-3 min)
3. Watch logs for success
4. Get URL: `https://campus-marketplace-api-xxxxx.onrender.com`
5. **Save this URL!**

✅ **Backend deployed!**

---

### STEP 5: Deploy Frontend on Netlify (10 minutes)

#### 5.1 Update Frontend Environment
1. Open `frontend/.env.production`
2. Update:
   ```
   VITE_API_URL=https://campus-marketplace-api-xxxxx.onrender.com
   VITE_PAYSTACK_PUBLIC_KEY=pk_live_xxxxx
   ```
3. Commit and push:
   ```bash
   git add frontend/.env.production
   git commit -m "Update: Render API URL"
   git push origin main
   ```

#### 5.2 Connect Netlify to GitHub
1. Go to Netlify dashboard
2. Click **"Add new site"** → **"Import an existing project"**
3. Select **"GitHub"**
4. Select your `marketplace` repo
5. Click **"Deploy site"**

#### 5.3 Set Environment Variables
1. In Netlify, go to **Settings** → **Build & deploy** → **Environment**
2. Add:

| Key | Value |
|-----|-------|
| `VITE_API_URL` | `https://campus-marketplace-api-xxxxx.onrender.com` |
| `VITE_PAYSTACK_PUBLIC_KEY` | Your Paystack public key |

3. Save and Netlify redeploys

#### 5.4 Verify
1. Netlify shows deployment status
2. Once done, get URL: `https://campus-marketplace-xxxxx.netlify.app`
3. Open it in browser
4. Should see marketplace homepage

✅ **Frontend deployed!**

---

### STEP 6: Test Everything (5 minutes)

#### 6.1 Test Frontend
1. Open your Netlify URL
2. Should see homepage
3. Check browser console (F12) - no errors

#### 6.2 Test Registration
1. Click "Register"
2. Create account with password 12+ chars
3. Should succeed
4. Check Supabase → **Table Editor** → **users** table
5. Should see your new user! ✅

#### 6.3 Test Database
1. Go to Supabase → **SQL Editor**
2. Run: `SELECT COUNT(*) FROM users;`
3. Should show `1`

✅ **Everything working!**

---

## 🔐 AFTER DEPLOYMENT CHECKLIST

- [ ] ✅ Both services deployed
- [ ] ✅ Can register account
- [ ] ✅ Can login
- [ ] ✅ Can upload products
- [ ] ✅ Can send messages
- [ ] ✅ User appears in Supabase
- [ ] ✅ No console errors

---

## 💰 COST BREAKDOWN (100% FREE)

| Service | Free Tier | Cost |
|---------|-----------|------|
| **Supabase** | 500MB storage | $0 |
| **Render** | 1 web service | $0 |
| **Netlify** | Unlimited static | $0 |
| **Total Month 1** | Everything | **$0** |

**When you scale (optional):**
- Supabase: Pay per GB (~$0-50/mo)
- Render: $7/mo (always-on)
- Netlify: Free forever

---

## ⚡ SUPABASE BONUS FEATURES (Free!)

You get extra stuff with Supabase:

1. **Real-time Database**
   ```javascript
   // Watch for changes in real-time
   supabase
     .from('products')
     .on('*', payload => console.log('Changed!', payload))
     .subscribe()
   ```

2. **Built-in Authentication** (optional)
   - User management
   - Magic links
   - OAuth providers

3. **Auto-generated REST API**
   - Endpoints created automatically
   - Great for mobile apps later

4. **Real-time Subscriptions**
   - Update UI when data changes
   - Perfect for chat/messaging

5. **PostgreSQL Power**
   - Better than MySQL
   - Advanced queries
   - Better performance

---

## 🐛 TROUBLESHOOTING

### Can't connect to Supabase?
1. Check connection string in Render environment
2. Verify password is correct (special chars need escaping)
3. Test with MySQL client:
   ```bash
   psql postgresql://postgres:[PASSWORD]@[HOST]/postgres
   ```

### Backend deployment failed?
1. Check Render logs
2. Verify all environment variables set
3. Check database password format

### Frontend shows errors?
1. Check browser console (F12)
2. Verify API URL in Netlify environment
3. Check CORS headers

### No data in database?
1. Go to Supabase → **Table Editor**
2. Check if tables exist
3. Run test insert:
   ```sql
   INSERT INTO users (username, email, password) 
   VALUES ('test', 'test@test.com', 'hashed');
   ```

---

## 🎯 NEXT STEPS

1. ✅ Create three accounts (Supabase, Render, Netlify)
2. ✅ Set up Supabase database with schema
3. ✅ Deploy backend on Render
4. ✅ Deploy frontend on Netlify
5. ✅ Test everything
6. 🔮 When ready: Get custom domain

**You're now live!** 🚀

---

## 📚 HELPFUL LINKS

- **Supabase Docs:** https://supabase.com/docs
- **Supabase Dashboard:** https://app.supabase.com
- **PostgreSQL Docs:** https://www.postgresql.org/docs/
- **PHP PDO PostgreSQL:** https://www.php.net/manual/en/ref.pdo-pgsql.php

---

**ALL THREE SERVICES ARE COMPLETELY FREE!** 🎉

Your marketplace is production-ready and costs $0/month to run on free tier.

Supabase is a great choice - you get PostgreSQL power + real-time features + zero cost!
