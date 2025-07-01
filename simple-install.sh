#!/bin/bash
set -e

echo "🚀 TeleBot Simple Installer"
echo "================================"

# Parse command line arguments
DOMAIN=""
TELEGRAM_TOKEN=""
STRIPE_PUBLIC=""
STRIPE_SECRET=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --domain=*)
            DOMAIN="${1#*=}"
            shift
            ;;
        --telegram-token=*)
            TELEGRAM_TOKEN="${1#*=}"
            shift
            ;;
        --stripe-public=*)
            STRIPE_PUBLIC="${1#*=}"
            shift
            ;;
        --stripe-secret=*)
            STRIPE_SECRET="${1#*=}"
            shift
            ;;
        *)
            echo "Unknown option $1"
            exit 1
            ;;
    esac
done

# Validate required parameters
if [[ -z "$DOMAIN" || -z "$TELEGRAM_TOKEN" || -z "$STRIPE_PUBLIC" || -z "$STRIPE_SECRET" ]]; then
    echo "❌ Missing required parameters!"
    echo "Usage: $0 --domain=your-domain.com --telegram-token=your-token --stripe-public=pk_xxx --stripe-secret=sk_xxx"
    exit 1
fi

echo "📋 Configuration:"
echo "   Domain: $DOMAIN"
echo "   Telegram Token: ${TELEGRAM_TOKEN:0:20}..."
echo "   Stripe Public: ${STRIPE_PUBLIC:0:20}..."
echo "   Stripe Secret: ${STRIPE_SECRET:0:20}..."

# Install Docker if not present
if ! command -v docker &> /dev/null; then
    echo "🐳 Installing Docker..."
    curl -fsSL https://get.docker.com -o get-docker.sh
    sh get-docker.sh
    systemctl enable docker
    systemctl start docker
fi

# Install Git if not present
if ! command -v git &> /dev/null; then
    echo "📦 Installing Git..."
    apt-get update
    apt-get install -y git
fi

# Configure firewall
echo "🔥 Configuring firewall..."
ufw allow 22
ufw allow 80
ufw allow 443
ufw allow 8000
ufw allow 81
ufw --force enable

# Create project directory
cd /opt
rm -rf telebot
git clone https://github.com/AlexCodeForge/telebot.git
cd telebot

# Create environment file
echo "⚙️ Creating environment configuration..."
cat > .env << EOF
APP_NAME=TeleBot
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=UTC
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

# Create docker-compose file
echo "🐳 Creating Docker configuration..."
cat > docker-compose.yml << 'EOF'
version: '3.8'

services:
  app:
    build: .
    container_name: telebot-app
    restart: unless-stopped
    ports:
      - "8000:80"
    env_file:
      - .env
    volumes:
      - ./database:/var/www/html/database
    networks:
      - proxy-network

  nginx-proxy-manager:
    image: 'jc21/nginx-proxy-manager:latest'
    container_name: nginx-proxy-manager
    restart: unless-stopped
    ports:
      - '80:80'
      - '81:81'
      - '443:443'
    volumes:
      - npm_data:/data
      - npm_letsencrypt:/etc/letsencrypt
    networks:
      - proxy-network

volumes:
  npm_data:
  npm_letsencrypt:

networks:
  proxy-network:
    driver: bridge
EOF

# Create simple Dockerfile
echo "📦 Creating Dockerfile..."
cat > Dockerfile << 'EOF'
FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk update && apk add --no-cache \
    nginx \
    supervisor \
    sqlite \
    sqlite-dev \
    nodejs \
    npm \
    git \
    curl \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_sqlite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader
RUN npm ci && npm run build

# Create database directory
RUN mkdir -p database && touch database/database.sqlite

# Set permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Copy configuration files
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Generate app key and run migrations
RUN php artisan key:generate --force
RUN php artisan migrate:fresh --force
RUN php artisan db:seed --force

# Cache config
RUN php artisan config:cache
RUN php artisan route:cache
RUN php artisan view:cache

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
EOF

# Create simple entrypoint (no complex logic)
mkdir -p docker
cat > docker/supervisord.conf << 'EOF'
[supervisord]
nodaemon=true
user=root

[program:php-fpm]
command=php-fpm
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:nginx]
command=nginx -g 'daemon off;'
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
EOF

# Generate app key before building
echo "🔑 Generating application key..."
APP_KEY=$(docker run --rm php:8.2-alpine php -r "echo 'base64:' . base64_encode(random_bytes(32));")
sed -i "s/APP_KEY=/APP_KEY=$APP_KEY/" .env

# Start services
echo "🚀 Starting TeleBot..."
docker compose up -d --build

echo ""
echo "✅ TeleBot Installation Complete!"
echo ""
echo "🌐 Application: http://$DOMAIN:8000"
echo "🔧 Nginx Proxy Manager: http://$DOMAIN:81"
echo "   Default login: admin@example.com / changeme"
echo ""
echo "📱 Admin Login: admin@telebot.com / admin123"
echo ""
echo "🔧 Management Commands:"
echo "   docker compose logs -f app    # View logs"
echo "   docker compose restart app    # Restart app"
echo "   docker compose down           # Stop all"
echo ""
EOF
