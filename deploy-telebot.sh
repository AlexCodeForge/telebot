#!/bin/bash

# TeleBot Complete Deployment Script
# Usage: ./deploy-telebot.sh <telegram_token> <stripe_public> <stripe_secret> [domain]

set -e

echo "🚀 TeleBot Complete Deployment Script"
echo "====================================="

# Check arguments
if [ $# -lt 3 ]; then
    echo "❌ Error: Missing required arguments"
    echo ""
    echo "Usage: $0 <telegram_token> <stripe_public> <stripe_secret> [domain]"
    echo ""
    echo "Example:"
    echo "$0 \"YOUR_TELEGRAM_BOT_TOKEN\" \\"
    echo "  \"YOUR_STRIPE_PUBLIC_KEY\" \\"
    echo "  \"YOUR_STRIPE_SECRET_KEY\""
    echo ""
    exit 1
fi

TELEGRAM_TOKEN="$1"
STRIPE_PUBLIC="$2"
STRIPE_SECRET="$3"
DOMAIN="${4:-alexcodeforge.com}"

echo "📋 Configuration:"
echo "   Domain: $DOMAIN"
echo "   Telegram Token: ${TELEGRAM_TOKEN:0:10}..."
echo "   Stripe Public: ${STRIPE_PUBLIC:0:20}..."
echo "   Stripe Secret: ${STRIPE_SECRET:0:20}..."
echo ""

# Function to install Docker on different systems
install_docker() {
    echo "🐳 Installing Docker..."

    if command -v apt-get &> /dev/null; then
        # Ubuntu/Debian
        echo "   Detected Ubuntu/Debian system"
        sudo apt-get update
        sudo apt-get install -y apt-transport-https ca-certificates curl gnupg lsb-release
        curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg
        echo "deb [arch=amd64 signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
        sudo apt-get update
        sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin

    elif command -v yum &> /dev/null; then
        # CentOS/RHEL
        echo "   Detected CentOS/RHEL system"
        sudo yum install -y yum-utils
        sudo yum-config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
        sudo yum install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin

    elif command -v dnf &> /dev/null; then
        # Fedora
        echo "   Detected Fedora system"
        sudo dnf -y install dnf-plugins-core
        sudo dnf config-manager --add-repo https://download.docker.com/linux/fedora/docker-ce.repo
        sudo dnf install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin

    else
        # Generic installation
        echo "   Using generic Docker installation"
        curl -fsSL https://get.docker.com -o get-docker.sh
        sudo sh get-docker.sh
        rm get-docker.sh
    fi

    # Start and enable Docker
    sudo systemctl start docker
    sudo systemctl enable docker

    # Add current user to docker group
    sudo usermod -aG docker $USER

    echo "✅ Docker installed successfully"
}

# Function to detect Docker Compose command
get_docker_compose_cmd() {
    if command -v docker-compose &> /dev/null; then
        echo "docker-compose"
    elif docker compose version &> /dev/null 2>&1; then
        echo "docker compose"
    else
        echo ""
    fi
}

# Function to install Docker Compose if not available
install_docker_compose() {
    if [ -z "$(get_docker_compose_cmd)" ]; then
        echo "🔧 Installing Docker Compose..."
        sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
        sudo chmod +x /usr/local/bin/docker-compose
        echo "✅ Docker Compose installed"
    fi
}

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "⚠️  Docker not found. Installing Docker..."
    install_docker
    echo "🔄 Please log out and log back in, then run this script again."
    echo "   (This is needed for Docker group permissions)"
    exit 0
else
    echo "✅ Docker is already installed"
fi

# Check if Docker is running
if ! docker info &> /dev/null; then
    echo "🔄 Starting Docker service..."
    sudo systemctl start docker
fi

# Install Docker Compose if needed
install_docker_compose

# Get the Docker Compose command to use
DOCKER_COMPOSE_CMD=$(get_docker_compose_cmd)
if [ -z "$DOCKER_COMPOSE_CMD" ]; then
    echo "❌ Error: Docker Compose is not available after installation"
    exit 1
fi
echo "✅ Using Docker Compose command: $DOCKER_COMPOSE_CMD"

# Pre-flight checks
echo "🔍 Running pre-flight checks..."

# Check available disk space (need at least 2GB)
AVAILABLE_SPACE=$(df / | awk 'NR==2 {print $4}')
if [ "$AVAILABLE_SPACE" -lt 2097152 ]; then  # 2GB in KB
    echo "⚠️  Warning: Low disk space (less than 2GB free). Docker build may fail."
    echo "   Available: $(($AVAILABLE_SPACE / 1024))MB"
fi

# Check if ports are available
for port in 8000 80 443 81; do
    if netstat -tuln 2>/dev/null | grep -q ":$port "; then
        echo "⚠️  Warning: Port $port is already in use. This may cause conflicts."
    fi
done

# Check memory (need at least 1GB available)
if [ -f /proc/meminfo ]; then
    AVAILABLE_MEM=$(grep MemAvailable /proc/meminfo | awk '{print $2}')
    if [ "$AVAILABLE_MEM" -lt 1048576 ]; then  # 1GB in KB
        echo "⚠️  Warning: Low memory (less than 1GB available). Build may be slow."
    fi
fi

echo "✅ Pre-flight checks completed"

# Check if we're in the telebot directory
if [ ! -f "docker-compose.yml" ]; then
    if [ -d "telebot" ]; then
        echo "📁 Found existing telebot directory, updating..."
        cd telebot
        git pull origin master || {
            echo "⚠️  Failed to update existing repository. Removing and re-cloning..."
            cd ..
            rm -rf telebot
            git clone https://github.com/AlexCodeForge/telebot.git
            cd telebot
        }
    else
        echo "📥 Cloning TeleBot repository..."
        git clone https://github.com/AlexCodeForge/telebot.git
        cd telebot
    fi
else
    echo "📁 Using existing TeleBot directory"
fi

# Create necessary directories
echo "📁 Creating directories..."
mkdir -p docker database

# Create SQLite database file
if [ ! -f database/database.sqlite ]; then
    echo "📊 Creating SQLite database..."
    touch database/database.sqlite
fi

# Generate Laravel app key
echo "🔑 Generating Laravel app key..."
APP_KEY=$(docker run --rm php:8.2-cli php -r "echo 'base64:' . base64_encode(random_bytes(32));")

# Create environment file with provided credentials
echo "📝 Creating environment configuration..."
cat > .env << EOF
APP_NAME=TeleBot
APP_ENV=local
APP_KEY=$APP_KEY
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/database/database.sqlite

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="\${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

VITE_PUSHER_APP_KEY="\${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="\${PUSHER_HOST}"
VITE_PUSHER_PORT="\${PUSHER_PORT}"
VITE_PUSHER_SCHEME="\${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="\${PUSHER_APP_CLUSTER}"

# Telegram Bot Configuration
TELEGRAM_BOT_TOKEN=$TELEGRAM_TOKEN

# Stripe Configuration
STRIPE_KEY=$STRIPE_PUBLIC
STRIPE_SECRET=$STRIPE_SECRET
CASHIER_CURRENCY=usd
CASHIER_CURRENCY_LOCALE=en_US

# Domain Configuration
DOMAIN=$DOMAIN
EOF

# Update docker-compose.yml with the provided credentials
echo "🔧 Updating Docker configuration..."
cat > docker-compose.yml << EOF
services:
  # Laravel Telebot Application
  telebot:
    build: .
    container_name: telebot-app
    restart: unless-stopped
    ports:
      - "8000:80"
    volumes:
      - ./storage:/var/www/html/storage
      - ./database/database.sqlite:/var/www/html/database/database.sqlite
      - ./.env:/var/www/html/.env
    environment:
      - APP_NAME=TeleBot
      - APP_ENV=local
      - APP_KEY=$APP_KEY
      - APP_DEBUG=true
      - APP_URL=http://$(curl -s ifconfig.me):8000
      - DB_CONNECTION=sqlite
      - DB_DATABASE=/var/www/html/database/database.sqlite
      - TELEGRAM_BOT_TOKEN=$TELEGRAM_TOKEN
      - STRIPE_KEY=$STRIPE_PUBLIC
      - STRIPE_SECRET=$STRIPE_SECRET
      - CASHIER_CURRENCY=usd
      - CASHIER_CURRENCY_LOCALE=en_US
    networks:
      - proxy-network

  # Nginx Proxy Manager
  nginx-proxy-manager:
    image: 'jc21/nginx-proxy-manager:latest'
    container_name: nginx-proxy-manager
    restart: unless-stopped
    ports:
      - '80:80'      # HTTP
      - '443:443'    # HTTPS
      - '81:81'      # Admin panel
    volumes:
      - npm-data:/data
      - npm-letsencrypt:/etc/letsencrypt
    networks:
      - proxy-network

networks:
  proxy-network:
    driver: bridge

volumes:
  npm-data:
  npm-letsencrypt:
EOF

# Stop any existing containers
echo "🛑 Stopping any existing containers..."
$DOCKER_COMPOSE_CMD down 2>/dev/null || true

# Clean up Docker system to free space and prevent cache issues
echo "🧹 Cleaning up Docker system..."
docker system prune -f || true

# Remove any existing images to ensure fresh build
echo "🗑️  Removing existing telebot images..."
docker rmi telebot-telebot telebot_telebot 2>/dev/null || true
docker rmi $(docker images -q --filter "reference=telebot*") 2>/dev/null || true

# Build and start containers
echo "🚀 Building and starting containers..."
if ! $DOCKER_COMPOSE_CMD up --build -d; then
    echo "⚠️  Alpine build failed. Trying Ubuntu-based fallback..."

    # Use Ubuntu-based Dockerfile as fallback
    if [ -f "Dockerfile.ubuntu" ]; then
        echo "🔄 Switching to Ubuntu-based Docker image..."
        mv Dockerfile Dockerfile.alpine.backup
        mv Dockerfile.ubuntu Dockerfile

        # Try building again with Ubuntu base
        if ! $DOCKER_COMPOSE_CMD up --build -d; then
            echo "❌ Both Alpine and Ubuntu builds failed."
            echo "🔍 Checking Docker logs..."
            $DOCKER_COMPOSE_CMD logs
            exit 1
        else
            echo "✅ Ubuntu-based build successful!"
        fi
    else
        echo "❌ Alpine build failed and no Ubuntu fallback available."
        echo "🔍 Checking Docker logs..."
        $DOCKER_COMPOSE_CMD logs
        exit 1
    fi
else
    echo "✅ Alpine-based build successful!"
fi

# Wait for containers to be ready
echo "⏳ Waiting for containers to start..."
sleep 30

# Check container status
echo "📊 Container Status:"
$DOCKER_COMPOSE_CMD ps

# Check if the application is responding
echo ""
echo "🔍 Checking application health..."
SERVER_IP=$(curl -s ifconfig.me 2>/dev/null || echo "YOUR_SERVER_IP")
if curl -s --max-time 10 "http://$SERVER_IP:8000" > /dev/null; then
    echo "✅ Application is responding!"
else
    echo "⚠️  Application may not be ready yet. Checking logs..."
    echo ""
    echo "📋 Recent Laravel Logs:"
    $DOCKER_COMPOSE_CMD exec -T telebot tail -20 /var/www/html/storage/logs/laravel.log 2>/dev/null || echo "No logs found yet"
    echo ""
    echo "📋 Container Logs:"
    $DOCKER_COMPOSE_CMD logs --tail=20 telebot
fi

echo ""
echo "🎉 Deployment Complete!"
echo "======================"
echo ""
echo "📋 Next Steps:"
echo ""
echo "1. 🌐 Your TeleBot is running on port 8000"
echo "   Test: http://$(curl -s ifconfig.me):8000"
echo "   If you see a 500 error, check the troubleshooting section below."
echo ""
echo "2. 🔧 Configure SSL Certificate:"
echo "   • Go to: http://$(curl -s ifconfig.me):81"
echo "   • Login: admin@example.com / changeme"
echo "   • IMPORTANT: Change the password immediately!"
echo ""
echo "3. 🔒 Add SSL Proxy Host:"
echo "   • Domain: $DOMAIN"
echo "   • Forward to: telebot-app:80"
echo "   • Enable SSL with Let's Encrypt"
echo "   • Use your email for Let's Encrypt notifications"
echo ""
echo "4. 🤖 Set Telegram Webhook:"
echo "   Once SSL is working, set your webhook to:"
echo "   https://$DOMAIN/api/telegram/webhook"
echo ""
echo "5. 👤 Access Admin Panel:"
echo "   • URL: https://$DOMAIN"
echo "   • Email: admin@admin.com"
echo "   • Password: password"
echo ""
echo "🛠️  Useful Commands:"
echo "   • View logs: $DOCKER_COMPOSE_CMD logs -f telebot"
echo "   • Laravel logs: $DOCKER_COMPOSE_CMD exec telebot tail -f /var/www/html/storage/logs/laravel.log"
echo "   • Restart: $DOCKER_COMPOSE_CMD restart telebot"
echo "   • Rebuild: $DOCKER_COMPOSE_CMD up --build -d"
echo "   • Stop: $DOCKER_COMPOSE_CMD down"
echo ""
echo "🔧 Troubleshooting:"
echo "   If you see a 500 error:"
echo "   1. Check Laravel logs: $DOCKER_COMPOSE_CMD logs telebot"
echo "   2. Check file permissions: $DOCKER_COMPOSE_CMD exec telebot ls -la storage/"
echo "   3. Check database: $DOCKER_COMPOSE_CMD exec telebot ls -la database/"
echo "   4. Run migrations manually: $DOCKER_COMPOSE_CMD exec telebot php artisan migrate"
echo ""
echo "🎯 Your TeleBot will be live at: https://$DOMAIN"
echo ""
echo "⚠️  IMPORTANT: Make sure your domain DNS points to this server's IP!"
echo "   Server IP: $(curl -s ifconfig.me)"
