# 🚀 Deploy Laravel Telegram Bot to Vercel - Complete Guide

## ⚡ Quick Overview
This guide will help you deploy your Laravel Telegram bot to Vercel in about 10 minutes using GitHub integration.

**💰 COMPLETELY FREE HOSTING STACK:**
- ✅ **Vercel**: Free hosting (100GB bandwidth, serverless functions)
- ✅ **Neon**: Free PostgreSQL database (500MB, no credit card)
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

### Option A: Neon (PostgreSQL) - **FREE FOREVER** ⭐
1. Go to **https://neon.tech** → Create account (no credit card required)
2. Create new project: `telebot-db`
3. **Free tier**: 500MB storage, 100 hours compute/month (resets monthly)
4. Copy the connection string from dashboard
5. **Why Neon**: Generous free tier, PostgreSQL compatible, excellent for small projects

### Option B: PlanetScale (MySQL) - **FREE TIER**
1. Go to **https://planetscale.com** → Create account
2. Click **"Create database"**
3. Database name: `telebot-db`
4. **Free tier**: 1 database, 1GB storage, 1 billion reads/month
5. Go to **"Connect"** → **"Create password"**
6. Copy the connection details

### Option C: Turso (SQLite-compatible) - **FREE**
1. Go to **https://turso.tech** → Create account
2. **Free tier**: 500MB storage, 1M row reads/month
3. Uses LibSQL (SQLite-compatible) - minimal migration needed
4. Perfect if you want to keep SQLite-like syntax

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

### 🗄️ Database Variables (100% FREE):

**For Neon (PostgreSQL) - RECOMMENDED FREE:**
```bash
DB_CONNECTION=pgsql
DB_HOST=ep-cool-tree-123456.us-east-1.aws.neon.tech
DB_PORT=5432
DB_DATABASE=neondb
DB_USERNAME=your-username
DB_PASSWORD=your-password
```

**For PlanetScale (MySQL) - FREE TIER:**
```bash
DB_CONNECTION=mysql
DB_HOST=aws.connect.psdb.cloud
DB_PORT=3306
DB_DATABASE=telebot-db
DB_USERNAME=your-username-from-planetscale
DB_PASSWORD=pscale_pw_your-password-from-planetscale
```

**For Turso (SQLite-compatible) - FREE:**
```bash
DB_CONNECTION=libsql
DB_URL=libsql://your-database-name.turso.io
DB_AUTH_TOKEN=your-turso-auth-token
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

After successful deployment, you need to create the database tables:

### Method 1: Update your local .env temporarily
1. Copy your production database credentials to your local `.env` file
2. Run: `php artisan migrate --force`
3. Restore your local `.env` file

### Method 2: Use database GUI (easier)
1. Connect to your database using TablePlus, phpMyAdmin, or similar
2. Import your database schema manually

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

### ❌ Build Fails
**Problem**: Deployment fails during build
**Solution**: 
- Check Node.js version is 18.x in Vercel project settings
- Verify all environment variables are set correctly

### ❌ 500 Internal Server Error
**Problem**: App deployed but shows 500 error
**Solutions**:
- Check Vercel function logs: Project → Functions → View logs
- Verify `APP_KEY` is set correctly
- Confirm database connection works

### ❌ Database Connection Failed
**Problem**: Can't connect to database
**Solutions**:
- Double-check database credentials
- Ensure database allows connections from Vercel
- Try connecting from your local machine first

### ❌ Telegram Webhook Not Working
**Problem**: Bot doesn't respond to messages
**Solutions**:
- Verify webhook URL is correct
- Check if route `/telegram/webhook` exists
- Ensure `TELEGRAM_BOT_TOKEN` is correct

---

## 📝 Environment Variables Quick Reference

Copy this template and fill in your values:

```bash
# === REQUIRED ===
APP_KEY=base64:your-generated-key-here
APP_URL=https://your-project-name.vercel.app
TELEGRAM_BOT_TOKEN=your-telegram-bot-token

# === FREE DATABASE (choose one) ===
# Option 1: Neon (PostgreSQL) - RECOMMENDED FREE
DB_CONNECTION=pgsql
DB_HOST=ep-cool-tree-123456.us-east-1.aws.neon.tech
DB_PORT=5432
DB_DATABASE=neondb
DB_USERNAME=your-username
DB_PASSWORD=your-password

# Option 2: PlanetScale (MySQL) - FREE TIER
# DB_CONNECTION=mysql
# DB_HOST=aws.connect.psdb.cloud
# DB_PORT=3306
# DB_DATABASE=telebot-db
# DB_USERNAME=your-username
# DB_PASSWORD=your-password

# Option 3: Turso (SQLite-compatible) - FREE
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
- ✅ **FREE database** (Neon PostgreSQL 500MB)

**Your bot URL**: `https://your-project-name.vercel.app`
**Monthly cost**: **$0** 💰

---

## 🔄 Future Updates

To update your bot:
1. Make changes to your code locally
2. Push to GitHub: `git push origin master`
3. Vercel automatically deploys the changes!

That's it! No server management needed. 🚀 
