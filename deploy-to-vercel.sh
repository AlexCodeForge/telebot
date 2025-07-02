#!/bin/bash

# Laravel to Vercel Deployment Setup Script
# This script sets up all necessary files for deploying Laravel to Vercel

set -e

echo "🚀 Setting up Laravel application for Vercel deployment..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Create api directory and entry point
echo -e "${BLUE}📁 Creating Vercel API entry point...${NC}"
mkdir -p api
cat > api/index.php << 'EOF'
<?php

// Forward Vercel requests to Laravel's public/index.php
require __DIR__ . '/../public/index.php';
EOF

# Create Vercel configuration
echo -e "${BLUE}⚙️  Creating vercel.json configuration...${NC}"
cat > vercel.json << 'EOF'
{
    "version": 2,
    "framework": null,
    "functions": {
        "api/index.php": {
            "runtime": "vercel-php@0.7.1"
        }
    },
    "routes": [
        {
            "src": "/build/(.*)",
            "dest": "/public/build/$1"
        },
        {
            "src": "/favicon.ico",
            "dest": "/public/favicon.ico"
        },
        {
            "src": "/robots.txt",
            "dest": "/public/robots.txt"
        },
        {
            "src": "/(.*)",
            "dest": "/api/index.php"
        }
    ],
    "outputDirectory": "public",
    "env": {
        "APP_ENV": "production",
        "APP_DEBUG": "false",
        "LOG_CHANNEL": "stderr",
        "SESSION_DRIVER": "cookie",
        "CACHE_DRIVER": "array",
        "QUEUE_CONNECTION": "sync",
        "APP_CONFIG_CACHE": "/tmp/config.php",
        "APP_EVENTS_CACHE": "/tmp/events.php",
        "APP_PACKAGES_CACHE": "/tmp/packages.php",
        "APP_ROUTES_CACHE": "/tmp/routes.php",
        "APP_SERVICES_CACHE": "/tmp/services.php",
        "VIEW_COMPILED_PATH": "/tmp"
    }
}
EOF

# Create .vercelignore
echo -e "${BLUE}🚫 Creating .vercelignore...${NC}"
cat > .vercelignore << 'EOF'
/vendor
/node_modules
/.git
/storage/logs/*
/storage/framework/sessions/*
/storage/framework/views/*
/storage/framework/cache/*
.env
.env.*
*.log
.DS_Store
Thumbs.db
EOF

# Update package.json with Node.js version
echo -e "${BLUE}📦 Updating package.json with Node.js version...${NC}"
cat > package.json << 'EOF'
{
    "$schema": "https://json.schemastore.org/package.json",
    "private": true,
    "type": "module",
    "engines": {
        "node": "18.x"
    },
    "scripts": {
        "build": "vite build",
        "dev": "vite",
        "vercel-build": "npm run build"
    },
    "devDependencies": {
        "@tailwindcss/forms": "^0.5.2",
        "@tailwindcss/vite": "^4.0.0",
        "alpinejs": "^3.4.2",
        "autoprefixer": "^10.4.2",
        "axios": "^1.8.2",
        "concurrently": "^9.0.1",
        "laravel-vite-plugin": "^1.2.0",
        "postcss": "^8.4.31",
        "tailwindcss": "^3.1.0",
        "vite": "^6.2.4"
    }
}
EOF

# Update bootstrap/app.php to trust proxies
echo -e "${BLUE}🔧 Updating bootstrap/app.php to trust proxies...${NC}"
cat > bootstrap/app.php << 'EOF'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies for Vercel deployment
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: [
            'telegram/webhook',
            'telegram/bot-emulator'
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
EOF

# Create .env.vercel.example for environment variables
echo -e "${BLUE}🔐 Creating .env.vercel.example with required environment variables...${NC}"
cat > .env.vercel.example << 'EOF'
# Laravel App Configuration
APP_NAME="YourAppName"
APP_KEY=base64:your-generated-app-key-here
APP_URL=https://your-app.vercel.app

# Environment Settings (Don't change these for Vercel)
APP_ENV=production
APP_DEBUG=false
LOG_CHANNEL=stderr
SESSION_DRIVER=cookie
CACHE_DRIVER=array
QUEUE_CONNECTION=sync

# Cache Paths (Don't change these for Vercel)
APP_CONFIG_CACHE=/tmp/config.php
APP_EVENTS_CACHE=/tmp/events.php
APP_PACKAGES_CACHE=/tmp/packages.php
APP_ROUTES_CACHE=/tmp/routes.php
APP_SERVICES_CACHE=/tmp/services.php
VIEW_COMPILED_PATH=/tmp

# Database Configuration (IMPORTANT: SQLite won't work on Vercel)
# Use external database like PlanetScale, Neon, or Turso
DB_CONNECTION=mysql
DB_HOST=your-database-host
DB_PORT=3306
DB_DATABASE=your-database-name
DB_USERNAME=your-database-username
DB_PASSWORD=your-database-password

# Telegram Bot Configuration
TELEGRAM_BOT_TOKEN=your-telegram-bot-token-here
TELEGRAM_ASYNC_REQUESTS=false

# Stripe Configuration (for Cashier)
STRIPE_KEY=pk_live_your-stripe-publishable-key
STRIPE_SECRET=sk_live_your-stripe-secret-key
STRIPE_WEBHOOK_SECRET=whsec_your-stripe-webhook-secret
CASHIER_CURRENCY=usd

# Session Configuration
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

# Mail Configuration (if needed)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
EOF

# Update .gitignore to include Vercel files
echo -e "${BLUE}📝 Updating .gitignore for Vercel...${NC}"
if ! grep -q ".vercel" .gitignore; then
    echo "" >> .gitignore
    echo "# Vercel" >> .gitignore
    echo ".vercel" >> .gitignore
fi

# Create comprehensive deployment guide
echo -e "${BLUE}📄 Creating comprehensive deployment guide...${NC}"
cat > VERCEL_DEPLOY_GUIDE.md << 'EOF'
# 🚀 Deploy Laravel Telegram Bot to Vercel - Complete Guide

## ⚡ Quick Overview
This guide will help you deploy your Laravel Telegram bot to Vercel in about 10 minutes using GitHub integration.

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

## 🗄️ Step 3: Set Up External Database

**⚠️ CRITICAL**: SQLite won't work on Vercel. Choose one of these options:

### Option A: PlanetScale (MySQL) - Recommended
1. Go to **https://planetscale.com** → Create account
2. Click **"Create database"**
3. Database name: `telebot-db`
4. Region: Choose closest to your users
5. Click **"Create database"**
6. Go to **"Connect"** → **"Create password"**
7. Copy the connection details

### Option B: Neon (PostgreSQL)
1. Go to **https://neon.tech** → Create account
2. Create new project: `telebot-db`
3. Copy the connection string from dashboard

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

### 🗄️ Database Variables:

**For PlanetScale (MySQL):**
```bash
DB_CONNECTION=mysql
DB_HOST=aws.connect.psdb.cloud
DB_PORT=3306
DB_DATABASE=telebot-db
DB_USERNAME=your-username-from-planetscale
DB_PASSWORD=pscale_pw_your-password-from-planetscale
```

**For Neon (PostgreSQL):**
```bash
DB_CONNECTION=pgsql
DB_HOST=ep-cool-tree-123456.us-east-1.aws.neon.tech
DB_PORT=5432
DB_DATABASE=telebot-db
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

# === DATABASE (choose MySQL or PostgreSQL) ===
DB_CONNECTION=mysql
DB_HOST=your-database-host
DB_PORT=3306
DB_DATABASE=your-database-name
DB_USERNAME=your-username
DB_PASSWORD=your-password

# === STRIPE (optional, for payments) ===
STRIPE_KEY=your-stripe-publishable-key
STRIPE_SECRET=your-stripe-secret-key
STRIPE_WEBHOOK_SECRET=your-webhook-secret
CASHIER_CURRENCY=usd
```

---

## 🎉 Success!

Your Laravel Telegram bot is now live on Vercel!

- ✅ Automatic deployments on every GitHub push
- ✅ Serverless scaling
- ✅ Free hosting (within limits)
- ✅ SSL certificate included
- ✅ Global CDN

**Your bot URL**: `https://your-project-name.vercel.app`

---

## 🔄 Future Updates

To update your bot:
1. Make changes to your code locally
2. Push to GitHub: `git push origin master`
3. Vercel automatically deploys the changes!

That's it! No server management needed. 🚀
EOF

# Install Vercel CLI if not already installed
echo -e "${BLUE}🔧 Checking Vercel CLI installation...${NC}"
if ! command -v vercel &> /dev/null; then
    echo -e "${YELLOW}Vercel CLI not found. Installing...${NC}"
    npm install -g vercel
else
    echo -e "${GREEN}✓ Vercel CLI already installed${NC}"
fi

echo ""
echo -e "${GREEN}🎉 Vercel deployment setup complete!${NC}"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "1. Set up your database (see VERCEL_DATABASE_SETUP.md)"
echo "2. Follow deployment instructions (see DEPLOY_TO_VERCEL.md)"
echo "3. Run: ${BLUE}composer install --no-dev --optimize-autoloader${NC}"
echo "4. Run: ${BLUE}npm install && npm run build${NC}"
echo "5. Run: ${BLUE}vercel${NC} to deploy"
echo ""
echo -e "${RED}⚠️  Important:${NC} SQLite won't work on Vercel. Set up an external database first!"
echo ""
echo -e "${GREEN}Happy deploying! 🚀${NC}"
