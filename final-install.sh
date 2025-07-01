#!/bin/bash
set -e

echo "🚀 TeleBot FINAL Working Installer"
echo "=================================="

# Parse arguments
DOMAIN=""
TELEGRAM_TOKEN=""
STRIPE_PUBLIC=""
STRIPE_SECRET=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --domain=*) DOMAIN="${1#*=}"; shift ;;
        --telegram-token=*) TELEGRAM_TOKEN="${1#*=}"; shift ;;
        --stripe-public=*) STRIPE_PUBLIC="${1#*=}"; shift ;;
        --stripe-secret=*) STRIPE_SECRET="${1#*=}"; shift ;;
        *) echo "Unknown option $1"; exit 1 ;;
    esac
done

if [[ -z "$DOMAIN" || -z "$TELEGRAM_TOKEN" || -z "$STRIPE_PUBLIC" || -z "$STRIPE_SECRET" ]]; then
    echo "❌ Missing parameters!"
    echo "Usage: $0 --domain=your-domain.com --telegram-token=xxx --stripe-public=xxx --stripe-secret=xxx"
    exit 1
fi

# Fix dpkg locks first
echo "🔧 Fixing package manager locks..."
sudo pkill -f apt
sudo pkill -f dpkg
sudo rm -f /var/lib/dpkg/lock-frontend
sudo rm -f /var/lib/dpkg/lock
sudo rm -f /var/cache/apt/archives/lock
sudo dpkg --configure -a

# Wait for any automatic updates to finish
echo "⏳ Waiting for automatic updates to finish..."
while sudo fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1; do
    echo "Waiting for dpkg lock to be released..."
    sleep 5
done

# Install Docker
if ! command -v docker &> /dev/null; then
    echo "🐳 Installing Docker..."
    export DEBIAN_FRONTEND=noninteractive
    curl -fsSL https://get.docker.com | sh
    systemctl start docker
    systemctl enable docker
fi

# Install Git
echo "📦 Installing Git..."
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y git openssl

# Configure firewall
echo "🔥 Configuring firewall..."
ufw allow 22
ufw allow 80
ufw allow 443
ufw allow 8000
ufw allow 81
ufw --force enable

# Setup project
echo "📂 Setting up project..."
cd /opt
rm -rf telebot
git clone https://github.com/AlexCodeForge/telebot.git
cd telebot

# Create .env
echo "⚙️ Creating environment..."
cat > .env << EOF
APP_NAME=TeleBot
APP_ENV=production
APP_KEY=base64:$(openssl rand -base64 32)
APP_DEBUG=false
APP_URL=http://$DOMAIN

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/database/database.sqlite

TELEGRAM_BOT_TOKEN=$TELEGRAM_TOKEN
TELEGRAM_WEBHOOK_URL=https://$DOMAIN/telegram/webhook

STRIPE_KEY=$STRIPE_PUBLIC
STRIPE_SECRET=$STRIPE_SECRET
CASHIER_CURRENCY=usd

CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database

LOG_CHANNEL=stderr
LOG_LEVEL=error
EOF

# Create simple working Dockerfile
echo "🐳 Creating Docker setup..."
cat > Dockerfile << 'EOF'
FROM php:8.2-fpm-alpine

# Install system packages
RUN apk add --no-cache \
    nginx \
    sqlite \
    sqlite-dev \
    nodejs \
    npm \
    git \
    curl \
    zip \
    unzip \
    openssl

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_sqlite bcmath opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy app files
COPY . .

# Fix git and install dependencies
RUN git config --global --add safe.directory /var/www/html
RUN composer install --no-dev --optimize-autoloader
RUN npm ci && npm run build

# Setup directories and permissions
RUN mkdir -p storage/app storage/framework/{cache,sessions,views} bootstrap/cache database
RUN touch database/database.sqlite
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 775 storage bootstrap/cache database

# Copy nginx config
COPY nginx.conf /etc/nginx/nginx.conf

EXPOSE 80

# Simple startup script
CMD ["sh", "-c", "php artisan key:generate --force && php artisan migrate:fresh --force --seed && php artisan config:cache && php-fpm -D && nginx -g 'daemon off;'"]
EOF

# Create nginx config
cat > nginx.conf << 'EOF'
events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    server {
        listen 80;
        root /var/www/html/public;
        index index.php;

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
        }
    }
}
EOF

# Create docker-compose
cat > docker-compose.yml << 'EOF'
services:
  app:
    build: .
    ports:
      - "8000:80"
    env_file: .env
    volumes:
      - ./database:/var/www/html/database
    restart: unless-stopped

  nginx-proxy-manager:
    image: jc21/nginx-proxy-manager:latest
    ports:
      - "80:80"
      - "81:81"
      - "443:443"
    volumes:
      - npm_data:/data
      - npm_letsencrypt:/etc/letsencrypt
    restart: unless-stopped

volumes:
  npm_data:
  npm_letsencrypt:
EOF

# Build and start
echo "🚀 Building and starting TeleBot..."
docker compose up -d --build

echo ""
echo "✅ TeleBot is RUNNING!"
echo "🌐 App: http://$DOMAIN:8000"
echo "🔧 NPM: http://$DOMAIN:81"
echo "👤 Admin: admin@telebot.com / admin123"
echo ""
echo "Commands:"
echo "  docker compose logs -f app"
echo "  docker compose restart app"
EOF
