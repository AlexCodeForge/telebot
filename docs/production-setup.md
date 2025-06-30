# Production Setup Guide

This guide will help you deploy your Telegram Video Bot to a production server using Nginx Proxy Manager.

## Prerequisites

-   VPS/Server with Ubuntu 20.04+ or similar
-   Domain name (e.g., `yourdomain.com`)
-   Basic command line knowledge

## 1. Server Setup

### Install Docker & Docker Compose

```bash
sudo apt update
sudo apt install docker.io docker-compose -y
sudo systemctl enable docker
sudo usermod -aG docker $USER
```

### Install PHP & Dependencies

```bash
sudo apt install php8.1 php8.1-fpm php8.1-sqlite3 php8.1-curl php8.1-zip php8.1-mbstring nginx -y
sudo systemctl enable php8.1-fpm nginx
```

## 2. Deploy Your Application

### Clone & Setup

```bash
cd /var/www
sudo git clone [your-repo-url] telebot
sudo chown -R $USER:www-data telebot
cd telebot

# Install dependencies
composer install --no-dev --optimize-autoloader
npm install && npm run build

# Setup environment
cp .env.example .env
php artisan key:generate
```

### Configure Environment

Edit `.env` file:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Your Telegram Bot Token
TELEGRAM_BOT_TOKEN=your_bot_token_here

# Payment keys (if using Stripe)
STRIPE_KEY=your_stripe_key
STRIPE_SECRET=your_stripe_secret
```

### Setup Database & Admin

```bash
php artisan migrate --force
php artisan db:seed --class=AdminUserSeeder
```

## 3. Nginx Proxy Manager Setup

### Install Nginx Proxy Manager

```bash
mkdir ~/nginx-proxy-manager
cd ~/nginx-proxy-manager

# Create docker-compose.yml
cat > docker-compose.yml << EOF
version: '3'
services:
  nginx-proxy-manager:
    image: 'jc21/nginx-proxy-manager:latest'
    restart: unless-stopped
    ports:
      - '80:80'
      - '81:81'
      - '443:443'
    volumes:
      - ./data:/data
      - ./letsencrypt:/etc/letsencrypt
EOF

docker-compose up -d
```

### Configure Your Domain

1. Open `http://your-server-ip:81`
2. Login with default credentials:
    - Email: `admin@example.com`
    - Password: `changeme`
3. Change the default password immediately
4. Add new Proxy Host:
    - **Domain**: `yourdomain.com`
    - **Forward IP**: `127.0.0.1`
    - **Forward Port**: `8000`
    - **Enable SSL**: Yes (Let's Encrypt)

## 4. Configure Local Nginx

Create nginx config for your app:

```bash
sudo nano /etc/nginx/sites-available/telebot
```

Add this configuration:

```nginx
server {
    listen 8000;
    server_name localhost;
    root /var/www/telebot/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    }
}
```

Enable the site:

```bash
sudo ln -s /etc/nginx/sites-available/telebot /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## 5. Set Telegram Webhook

```bash
cd /var/www/telebot
php artisan tinker
```

In tinker, run:

```php
use Telegram\Bot\Laravel\Facades\Telegram;
Telegram::setWebhook(['url' => 'https://yourdomain.com/telegram/webhook']);
exit
```

## 6. Final Steps

### Set Permissions

```bash
sudo chown -R www-data:www-data /var/www/telebot/storage
sudo chown -R www-data:www-data /var/www/telebot/bootstrap/cache
sudo chmod -R 775 /var/www/telebot/storage
sudo chmod -R 775 /var/www/telebot/bootstrap/cache
```

### Test Your Setup

1. Visit `https://yourdomain.com` - should show your video store
2. Login to admin: `https://yourdomain.com/login`
    - Email: `admin@example.com`
    - Password: `password`
3. Send a video to your Telegram bot - should appear in admin panel

## 🎉 You're Live!

Your Telegram Video Bot is now running in production. Customers can:

-   Browse videos at `https://yourdomain.com`
-   Purchase videos with automatic Telegram delivery
-   Start bot chat at `https://t.me/your_bot_username`

## Support

-   Check logs: `tail -f /var/www/telebot/storage/logs/laravel.log`
-   Restart services: `sudo systemctl restart nginx php8.1-fpm`
-   Update app: `git pull && composer install && php artisan migrate`

---

**Security Note**: Change the default admin password immediately after setup!
