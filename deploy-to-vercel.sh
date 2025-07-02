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

# Create database migration note
echo -e "${BLUE}📋 Creating database setup instructions...${NC}"
cat > VERCEL_DATABASE_SETUP.md << 'EOF'
# Database Setup for Vercel Deployment

⚠️ **IMPORTANT**: SQLite will NOT work on Vercel because it's a serverless platform.

## Recommended Database Options:

### 1. **PlanetScale (MySQL)** - Recommended
- Free tier available
- Serverless MySQL platform
- Great for Laravel applications
- Easy to set up and use

Setup:
1. Create account at https://planetscale.com
2. Create a database
3. Get connection details
4. Add to Vercel environment variables

### 2. **Neon (PostgreSQL)**
- Free tier: 500MB storage
- Postgres-compatible
- Good performance

Setup:
1. Create account at https://neon.tech
2. Create a database
3. Get connection string
4. Update DB_CONNECTION=pgsql in environment variables

### 3. **Turso (SQLite-compatible)**
- LibSQL (SQLite-compatible)
- Serverless
- Good for SQLite migration

Setup:
1. Create account at https://turso.tech
2. Install Turso driver for Laravel
3. Configure connection

## Environment Variables for Database:

Add these to your Vercel project settings:

```
DB_CONNECTION=mysql  # or pgsql for PostgreSQL
DB_HOST=your-host
DB_PORT=3306
DB_DATABASE=your-database
DB_USERNAME=your-username
DB_PASSWORD=your-password
```

## Migration Commands:

After setting up the database, you'll need to run migrations. You can:
1. Run migrations locally against the production database
2. Use a deployment script
3. Set up a one-time Vercel function to run migrations

Example local migration to production DB:
```bash
php artisan migrate --force
```
EOF

# Create deployment instructions
echo -e "${BLUE}📄 Creating deployment instructions...${NC}"
cat > DEPLOY_TO_VERCEL.md << 'EOF'
# Deploy Laravel to Vercel - Instructions

## Prerequisites
1. Vercel account (https://vercel.com)
2. Vercel CLI installed: `npm i -g vercel`
3. External database set up (see VERCEL_DATABASE_SETUP.md)

## Quick Deployment Steps:

### 1. Install Dependencies & Build
```bash
# Install PHP dependencies (optimized for production)
composer install --no-dev --optimize-autoloader

# Install Node dependencies and build assets
npm install
npm run build
```

### 2. Generate App Key
```bash
php artisan key:generate --show
```
Copy this key - you'll need it for Vercel environment variables.

### 3. Deploy to Vercel
```bash
# Login to Vercel
vercel login

# Deploy (first time will fail - that's expected)
vercel

# Or deploy to production directly
vercel --prod
```

### 4. Set Environment Variables in Vercel
Go to your Vercel project dashboard → Settings → Environment Variables

**Required Variables:**
- `APP_KEY`: The key from step 2
- `APP_URL`: Your Vercel app URL (e.g., https://your-app.vercel.app)
- Database variables (see VERCEL_DATABASE_SETUP.md)
- `TELEGRAM_BOT_TOKEN`: Your Telegram bot token
- Stripe variables for payments

**Copy from .env.vercel.example and customize:**
```bash
APP_NAME=YourAppName
APP_KEY=base64:your-generated-key
APP_URL=https://your-app.vercel.app
DB_CONNECTION=mysql
DB_HOST=your-db-host
# ... etc
```

### 5. Set Up Database
See VERCEL_DATABASE_SETUP.md for detailed instructions.

### 6. Configure Telegram Webhook
Update your Telegram webhook URL to point to your Vercel deployment:
```
https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook?url=https://your-app.vercel.app/telegram/webhook
```

### 7. Redeploy
After setting environment variables:
```bash
vercel --prod
```

## File Storage Considerations

Vercel is serverless, so local file storage won't persist. For file uploads:
1. Use AWS S3
2. Use Cloudinary
3. Use Vercel Blob (for simple files)

## Troubleshooting

### Build Fails
- Check Node.js version is 18.x
- Ensure all environment variables are set
- Check build logs in Vercel dashboard

### 500 Errors
- Check function logs in Vercel dashboard
- Verify database connection
- Ensure APP_KEY is set correctly

### Assets Not Loading
- Run `npm run build` locally first
- Check build output in `/public/build`
- Verify Vite configuration

## Performance Tips

1. Enable caching in your routes for static content
2. Optimize database queries (use indices)
3. Consider using Redis for sessions (Upstash offers free tier)
4. Use CDN for static assets

## Monitoring

- Use Vercel Analytics
- Monitor function execution time
- Set up error logging (Sentry recommended)
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
