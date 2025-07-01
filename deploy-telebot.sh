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
APP_KEY=$APP_KEY
TELEGRAM_BOT_TOKEN=$TELEGRAM_TOKEN
STRIPE_KEY=$STRIPE_PUBLIC
STRIPE_SECRET=$STRIPE_SECRET
DOMAIN=$DOMAIN
EOF

# Update docker-compose.yml with the provided credentials
echo "🔧 Updating Docker configuration..."
cat > docker-compose.yml << EOF
version: '3.8'

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
    environment:
      - APP_NAME=TeleBot
      - APP_ENV=production
      - APP_KEY=base64:${APP_KEY}
      - APP_DEBUG=false
      - APP_URL=https://${DOMAIN}
      - DB_CONNECTION=sqlite
      - DB_DATABASE=/var/www/html/database/database.sqlite
      - TELEGRAM_BOT_TOKEN=${TELEGRAM_TOKEN}
      - STRIPE_KEY=${STRIPE_PUBLIC}
      - STRIPE_SECRET=${STRIPE_SECRET}
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

echo ""
echo "🎉 Deployment Complete!"
echo "======================"
echo ""
echo "📋 Next Steps:"
echo ""
echo "1. 🌐 Your TeleBot is running on port 8000"
echo "   Test: http://$(curl -s ifconfig.me):8000"
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
echo "   • View logs: $DOCKER_COMPOSE_CMD logs -f"
echo "   • Restart: $DOCKER_COMPOSE_CMD restart"
echo "   • Stop: $DOCKER_COMPOSE_CMD down"
echo ""
echo "🎯 Your TeleBot will be live at: https://$DOMAIN"
echo ""
echo "⚠️  IMPORTANT: Make sure your domain DNS points to this server's IP!"
echo "   Server IP: $(curl -s ifconfig.me)"
