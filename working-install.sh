#!/bin/bash
set -e

echo "🚀 TeleBot WORKING Installer"
echo "============================="

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

# Install Docker
if ! command -v docker &> /dev/null; then
    echo "🐳 Installing Docker..."
    curl -fsSL https://get.docker.com | sh
    systemctl start docker
    systemctl enable docker
fi

# Install Git
apt-get update && apt-get install -y git

# Configure firewall
ufw allow 22
ufw allow 80
ufw allow 443
ufw allow 8000
ufw allow 81
ufw --force enable

# Setup project
cd /opt
rm -rf telebot
git clone https://github.com/AlexCodeForge/telebot.git
cd telebot

# Create .env
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

# Create working Dockerfile
cat > Dockerfile << 'EOF'
FROM php:8.2-fpm-alpine

# Install everything we need
RUN apk add --no-cache \
    nginx \
    supervisor \
    sqlite \
    sqlite-dev \
    nodejs \
    npm \
    git \
    curl \
    zip \
    unzip \
    openssl

# Install ALL PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_sqlite \
    bcmath \
    opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy everything
COPY . .

# Fix git ownership
RUN git config --global --add safe.directory /var/www/html

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-bcmath

# Install Node dependencies and build
RUN npm ci && npm run build

# Create directories and database
RUN mkdir -p storage/app storage/framework/{cache,sessions,views} bootstrap/cache database
RUN touch database/database.sqlite

# Set permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 775 storage bootstrap/cache database
RUN chmod 664 database/database.sqlite

# Copy configs
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]
EOF

# Create startup script
mkdir -p docker
cat > docker/start.sh << 'EOF'
#!/bin/sh

# Generate key if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    php artisan key:generate --force
fi

# Run migrations
php artisan migrate:fresh --force --seed

# Cache everything
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start services
php-fpm -D
nginx -g 'daemon off;'
EOF

# Create nginx config
cat > docker/nginx.conf << 'EOF'
events {
    worker_connections 1024;
}

http {
    include       /etc/nginx/mime.types;
    default_type  application/octet-stream;
    sendfile        on;
    keepalive_timeout  65;

    server {
        listen 80;
        server_name _;
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
version: '3.8'
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
echo "🚀 Building and starting..."
docker compose up -d --build

echo ""
echo "✅ DONE! TeleBot is running!"
echo "🌐 App: http://$DOMAIN:8000"
echo "🔧 NPM: http://$DOMAIN:81 (admin@example.com / changeme)"
echo "👤 Admin: admin@telebot.com / admin123"
EOF
