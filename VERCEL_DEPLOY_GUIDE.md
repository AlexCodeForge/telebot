# 🚀 Deploy Laravel Telegram Bot to Vercel - Complete Guide

## ⚡ Quick Overview
This guide will help you deploy your Laravel Telegram bot to Vercel in about 10 minutes using GitHub integration.

**💰 COMPLETELY FREE HOSTING STACK:**
- ✅ **Vercel**: Free hosting (100GB bandwidth, serverless functions)
- ✅ **Supabase**: Free PostgreSQL database (500MB, no credit card)
- ✅ **GitHub**: Free repository hosting
- ✅ **Total cost**: $0/month forever!

---

## 📋 Step 1: Create Vercel Account
1. Go to **https://vercel.com**
2. Click **"Sign Up"**
3. Choose **"Continue with GitHub"**
4. Authorize Vercel to access your repositories

---

## 📦 Step 2: Import Project from GitHub
1. In Vercel dashboard, click **"New Project"**
2. Find your **`telebot`** repository
3. Click **"Import"**
4. **IMPORTANT**: Set Framework Preset to **"Other"**
5. Root Directory: Leave as **"/"** (default)
6. Click **"Deploy"**

⚠️ **Expected**: First deployment will FAIL - this is normal! We need environment variables.

---

## 🗄️ Step 3: Set Up FREE External Database

**⚠️ CRITICAL**: SQLite won't work on Vercel. Here are the **100% FREE** options:

### Option A: Supabase (PostgreSQL) - **REQUIRED FOR VERCEL** ⭐⭐⭐
1. Go to **https://supabase.com** → Click **"Start your project"**
2. **Sign up with GitHub** (instant signup, no forms!)
3. Click **"New Project"**
4. **Project settings**:
   - **Name**: `telebot-db`
   - **Database Password**: Create a strong password (save it!)
   - **Region**: Choose closest to you
5. Click **"Create new project"** (takes ~2 minutes)
6. **CRITICAL**: After project is ready, go to **Settings** → **Database**
7. **Find "Connection Pooling"** section (NOT the regular connection string!)
8. **Mode**: Choose **"Transaction"** (required for serverless)
9. **Copy the Transaction Pooler connection string** - it should look like:
   ```
   postgresql://postgres.xyz:[YOUR-PASSWORD]@aws-0-us-east-2.pooler.supabase.com:6543/postgres
   ```

**⚠️ IMPORTANT**: You MUST use the **Transaction Pooler** connection string, not the regular one! The regular connection string uses IPv6 which doesn't work with Vercel.

**Why Supabase Transaction Pooler is REQUIRED:**
- ✅ **IPv4 compatible** (Vercel doesn't support IPv6)
- ✅ **Serverless optimized** for functions like Vercel
- ✅ **No connection timeouts** during cold starts
- ✅ **100% free** (no additional costs)
- ✅ **Perfect Laravel migrations** (no transaction errors)

### Alternative Options (If Supabase Doesn't Work):

### Option B: Turso (SQLite) - **SIMPLEST SETUP** ⭐⭐
1. Go to **https://turso.tech** → Create account
2. Create new database: `telebot-db`
3. **Free tier**: 500MB storage, 1M row reads/month
4. **Perfect if**: You want zero PostgreSQL complexity
5. **Bonus**: Your existing SQLite migrations work perfectly!

### Option C: Neon (PostgreSQL) - **CAN BE PROBLEMATIC** ⚠️
1. Go to **https://neon.tech** → Create account
2. **Free tier**: 500MB storage
3. **Warning**: Often has migration transaction errors (as you experienced)
4. **Use only if**: Supabase doesn't work for some reason

### Option D: PlanetScale (MySQL)
1. Go to **https://planetscale.com** → Create account
2. **Free tier**: 1GB storage
3. **Good alternative**, but requires MySQL knowledge

---

## 🔑 Step 4: Generate Laravel APP_KEY
On your local machine, run:
```bash
php artisan key:generate --show
```
Copy the output (looks like: `base64:abcd1234...`) - you'll need this!

---

## ⚙️ Step 5: Configure Environment Variables in Vercel

1. Go to your Vercel project → **Settings** → **Environment Variables**
2. Add these variables one by one:

### 🔐 Required Variables (Copy these exactly):

```bash
# Laravel App Key (from Step 4)
APP_KEY=base64:abcd1234efgh5678ijkl9012mnop3456qrst7890uvwx1234yz567890

# App URL (update with your actual Vercel domain after first successful deploy)
APP_URL=https://your-project-name.vercel.app

# Telegram Bot Token (get from @BotFather)
TELEGRAM_BOT_TOKEN=1234567890:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijk
```

### 🗄️ Database Variables (MUST USE TRANSACTION POOLER):

**For Supabase Transaction Pooler (REQUIRED) - Use these exact format:**
```bash
DB_CONNECTION=pgsql
DB_HOST=aws-0-us-east-2.pooler.supabase.com
DB_PORT=6543
DB_DATABASE=postgres
DB_USERNAME=postgres.shnjjgyewqjkaejstmeq
DB_PASSWORD=npg_Gk4NScgxV0BQ
```

**⚠️ CRITICAL NOTES:**
- **Port must be 6543** (not 5432) - this is the Transaction Pooler port
- **Host must end with .pooler.supabase.com** - this is IPv4 compatible
- **Username must include the project ID** (e.g., postgres.xyz123)
- **Never use the regular Supabase connection string** - it uses IPv6 and won't work

**For Turso (SQLite) - SIMPLEST:**
```bash
DB_CONNECTION=libsql
DB_URL=libsql://your-database-name.turso.io
DB_AUTH_TOKEN=your-turso-auth-token
```

**For Neon (PostgreSQL) - BACKUP OPTION:**
```bash
DB_CONNECTION=pgsql
DB_HOST=ep-cool-tree-123456.us-east-1.aws.neon.tech
DB_PORT=5432
DB_DATABASE=neondb
DB_USERNAME=your-username
DB_PASSWORD=your-password
```

### 💳 Stripe Variables (if using payments):
```bash
STRIPE_KEY=pk_live_51ABCDEfghijklmnopqrstuvwxyz123456789
STRIPE_SECRET=sk_live_51ABCDEfghijklmnopqrstuvwxyz123456789
STRIPE_WEBHOOK_SECRET=whsec_1234567890abcdefghijklmnop
CASHIER_CURRENCY=usd
```

---

## 🔄 Step 6: Redeploy
1. Go to **Deployments** tab in your Vercel project
2. Click **"Redeploy"** on the latest (failed) deployment
3. **UNCHECK** "Use existing Build Cache"
4. Click **"Redeploy"**

Wait for deployment to complete (~2-3 minutes).

---

## 🗃️ Step 7: Run Database Migrations

After successful deployment, visit this URL to set up your database:

```
https://your-project-name.vercel.app/run-migrations-setup-once
```

**This endpoint will:**
- ✅ **Run all migrations** automatically with Supabase compatibility
- ✅ **Create an admin user** (email: admin@telebot.local, password: admin123456)
- ✅ **Self-disable** after first successful run for security
- ✅ **Handle Supabase Transaction Pooler perfectly** (no IPv6 or transaction errors!)

**⚠️ IMPORTANT**: Remove this route from `routes/web.php` after successful setup!

---

## 🤖 Step 8: Update Telegram Webhook

Replace the URL with your actual Vercel deployment URL:

```bash
https://api.telegram.org/bot1234567890:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijk/setWebhook?url=https://your-project-name.vercel.app/telegram/webhook
```

**Example with real values:**
```bash
https://api.telegram.org/bot1234567890:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijk/setWebhook?url=https://telebot-abc123.vercel.app/telegram/webhook
```

Visit this URL in your browser - you should see: `{"ok":true,"result":true}`

---

## ✅ Step 9: Test Your Bot

1. Send a message to your Telegram bot
2. Check if it responds correctly
3. If using payments, test a purchase flow

---

## 🔧 Common Issues & Solutions

### ❌ "role neondb_owner does not exist"
**Problem**: You're using old Neon-specific database configuration
**Solution**: 
1. Update your Vercel environment variables to use Supabase Transaction Pooler
2. Make sure `DB_HOST` ends with `.pooler.supabase.com`
3. Make sure `DB_PORT` is `6543` (not 5432)

### ❌ IPv6 Connection Errors
**Problem**: Using regular Supabase connection string (IPv6)
**Solution**: Use Transaction Pooler connection string (IPv4 compatible)

### ❌ "Cannot assign requested address"
**Problem**: IPv6 connectivity issue between Vercel and database
**Solution**: Switch to Supabase Transaction Pooler immediately

### ❌ Migration timeouts or transaction errors
**Problem**: Database connection not optimized for serverless
**Solution**: Transaction Pooler is specifically designed for serverless functions

---

## 📝 Environment Variables Quick Reference

Copy this template and fill in your values:

```bash
# === REQUIRED ===
APP_KEY=base64:your-generated-key-here
APP_URL=https://your-project-name.vercel.app
TELEGRAM_BOT_TOKEN=your-telegram-bot-token

# === SUPABASE DATABASE (RECOMMENDED) ===
DB_CONNECTION=pgsql
DB_HOST=db.abc123.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=your-supabase-password

# === ALTERNATIVE: TURSO (SIMPLEST) ===
# DB_CONNECTION=libsql
# DB_URL=libsql://your-database-name.turso.io
# DB_AUTH_TOKEN=your-turso-auth-token

# === STRIPE (optional, for payments) ===
STRIPE_KEY=your-stripe-publishable-key
STRIPE_SECRET=your-stripe-secret-key
STRIPE_WEBHOOK_SECRET=your-webhook-secret
CASHIER_CURRENCY=usd
```

---

## 🎉 Success!

Your Laravel Telegram bot is now live on Vercel - **COMPLETELY FREE**! 

- ✅ Automatic deployments on every GitHub push
- ✅ Serverless scaling (handles traffic spikes automatically)
- ✅ **100% FREE hosting** (no hidden costs, no credit card required)
- ✅ SSL certificate included (secure HTTPS)
- ✅ Global CDN (fast worldwide access)
- ✅ **FREE Supabase database** (500MB PostgreSQL)

**Your bot URL**: `https://your-project-name.vercel.app`
**Monthly cost**: **$0** 💰

---

## 🔄 Future Updates

To update your bot:
1. Make changes to your code locally
2. Push to GitHub: `git push origin master`
3. Vercel automatically deploys the changes!

That's it! No server management needed. 🚀 

## 📋 Final Checklist

- ✅ **Database**: Using Supabase Transaction Pooler (port 6543)
- ✅ **Deployment**: Successful on Vercel with no errors
- ✅ **Environment**: All variables set correctly in Vercel
- ✅ **Migrations**: Completed successfully via the setup endpoint
- ✅ **Webhook**: Updated with your Vercel URL
- ✅ **Security**: Removed migration route from code
- ✅ **Testing**: Bot responds to messages
