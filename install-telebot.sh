#!/bin/bash

# TeleBot Complete Installation Script
# One script to rule them all - from fresh VPS to fully deployed TeleBot
echo "🚀 TeleBot Complete Installation Script"
echo "==============================================="

# Check if required parameters are provided
if [ $# -lt 3 ]; then
    echo "❌ Usage: $0 <telegram_token> <stripe_public_key> <stripe_secret_key> [domain]"
    echo "📝 Example: $0 'YOUR_TELEGRAM_TOKEN' 'pk_test_...' 'sk_test_...' 'alexcodeforge.com'"
    echo ""
    echo "🔧 To get your credentials:"
    echo "   🤖 Telegram Token: Message @BotFather on Telegram"
    echo "   💳 Stripe Keys: https://dashboard.stripe.com/test/apikeys"
    echo ""
    exit 1
fi

TELEGRAM_TOKEN="$1"
STRIPE_PUBLIC_KEY="$2"
STRIPE_SECRET_KEY="$3"
DOMAIN="${4:-localhost}"

echo "🔧 Configuration:"
echo "   🤖 Telegram Token: ${TELEGRAM_TOKEN:0:10}..."
echo "   💳 Stripe Public Key: ${STRIPE_PUBLIC_KEY:0:20}..."
echo "   💳 Stripe Secret Key: ${STRIPE_SECRET_KEY:0:20}..."
echo "   🌐 Domain: $DOMAIN"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "⚠️  This script requires root privileges. Please run with sudo:"
    echo "   sudo curl -fsSL https://raw.githubusercontent.com/AlexCodeForge/telebot/master/install-telebot.sh | sudo bash -s -- \"$1\" \"$2\" \"$3\" \"$4\""
    exit 1
fi

# Update system packages
echo "📦 Updating system packages..."
apt-get update -y

# Function to install Docker
install_docker() {
    echo "🐳 Installing Docker..."

    # Remove old Docker versions
    apt-get remove -y docker docker-engine docker.io containerd runc || true

    # Install prerequisites
    apt-get install -y ca-certificates curl gnupg lsb-release

    # Add Docker's official GPG key
    mkdir -p /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg

    # Set up the repository
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null

    # Update package index again
    apt-get update -y

    # Install Docker Engine
    apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

    # Start and enable Docker
    systemctl start docker
    systemctl enable docker

    echo "✅ Docker installed successfully!"
}

# Check if Docker is installed and running
if ! command -v docker &> /dev/null; then
    echo "🔍 Docker not found. Installing Docker..."
    install_docker
elif ! docker info > /dev/null 2>&1; then
    echo "🔍 Docker found but not running. Starting Docker..."
    systemctl start docker
    sleep 5
    if ! docker info > /dev/null 2>&1; then
        echo "❌ Failed to start Docker. Reinstalling..."
        install_docker
    fi
else
    echo "✅ Docker is already installed and running"
fi

# Verify Docker Compose
if command -v docker-compose &> /dev/null; then
    DOCKER_COMPOSE="docker-compose"
    echo "📦 Using standalone docker-compose"
elif docker compose version &> /dev/null; then
    DOCKER_COMPOSE="docker compose"
    echo "📦 Using docker compose plugin"
else
    echo "❌ Docker Compose not found. This should not happen with modern Docker installation."
    exit 1
fi

# Install git if not present
if ! command -v git &> /dev/null; then
    echo "📦 Installing git..."
    apt-get install -y git
fi

# Install other useful tools
echo "🛠️ Installing additional tools..."
apt-get install -y curl wget unzip htop nano ufw

# Set up firewall
echo "🔥 Configuring firewall..."
ufw --force enable
ufw allow ssh
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 8000/tcp
ufw allow 81/tcp
echo "✅ Firewall configured (SSH, HTTP, HTTPS, 8000, 81)"

# Clone or update repository
REPO_DIR="/opt/telebot"
echo "📁 Setting up TeleBot repository..."
if [ -d "$REPO_DIR" ]; then
    echo "   📂 Updating existing repository..."
    cd "$REPO_DIR"
    git pull origin master
else
    echo "   📂 Cloning repository..."
    git clone https://github.com/AlexCodeForge/telebot.git "$REPO_DIR"
    cd "$REPO_DIR"
fi

# Ensure we're in the right directory
cd "$REPO_DIR"
echo "📂 Working in directory: $(pwd)"

# Create .env file with the provided credentials
echo "📝 Creating environment configuration..."
cat > .env << EOF
APP_NAME="TeleBot Video Store"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_URL=https://$DOMAIN

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file
APP_MAINTENANCE_STORE=database

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/database/database.sqlite

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database
CACHE_PREFIX=

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
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

VITE_APP_NAME="\${APP_NAME}"

# Telegram Bot Configuration
TELEGRAM_BOT_TOKEN=$TELEGRAM_TOKEN

# Stripe Configuration
STRIPE_KEY=$STRIPE_PUBLIC_KEY
STRIPE_SECRET=$STRIPE_SECRET_KEY
CASHIER_CURRENCY=usd
CASHIER_CURRENCY_LOCALE=en_US
CASHIER_LOGGER=stack
EOF

echo "✅ Environment file created successfully!"

# Stop any existing containers
echo "🛑 Stopping any existing containers..."
$DOCKER_COMPOSE down --remove-orphans || true

# Clean up old images
echo "🗑️ Cleaning up old Docker images..."
docker rmi telebot-app:latest 2>/dev/null || true
docker system prune -f || true

# Build and start containers
echo "🔨 Building and starting TeleBot containers..."
echo "   ⏳ This may take a few minutes on first run..."
$DOCKER_COMPOSE up --build -d

# Wait for services to be ready
echo "⏳ Waiting for services to initialize..."
sleep 30

# Health check function
check_service() {
    local service_name="$1"
    local port="$2"
    local timeout=60
    local count=0

    echo "🔍 Checking $service_name on port $port..."
    while [ $count -lt $timeout ]; do
        if curl -s http://localhost:$port > /dev/null 2>&1; then
            echo "✅ $service_name is running!"
            return 0
        fi
        sleep 2
        count=$((count + 2))
    done
    echo "❌ $service_name failed to start"
    return 1
}

# Check if services are running
echo "🏥 Running health checks..."
APP_RUNNING=false
NPM_RUNNING=false

if docker ps | grep -q "telebot-app"; then
    if check_service "TeleBot App" 8000; then
        APP_RUNNING=true
    fi
else
    echo "❌ TeleBot app container not found"
fi

if docker ps | grep -q "npm"; then
    if check_service "Nginx Proxy Manager" 81; then
        NPM_RUNNING=true
    fi
else
    echo "❌ Nginx Proxy Manager container not found"
fi

# Get server IP
SERVER_IP=$(curl -s http://ipv4.icanhazip.com/ 2>/dev/null || curl -s http://ifconfig.me/ 2>/dev/null || echo "YOUR_SERVER_IP")

# Final status report
echo ""
echo "🎉 TeleBot Installation Complete!"
echo "=================================================="

if [ "$APP_RUNNING" = true ]; then
    echo "✅ TeleBot Application: RUNNING"
    echo "   🌐 Direct Access: http://$SERVER_IP:8000"
    if [ "$DOMAIN" != "localhost" ]; then
        echo "   🌐 Domain Access: https://$DOMAIN (after SSL setup)"
    fi
    echo "   👤 Admin Login: admin@telebot.com / admin123"
else
    echo "❌ TeleBot Application: FAILED"
    echo "🔍 Checking logs..."
    $DOCKER_COMPOSE logs app | tail -20
fi

if [ "$NPM_RUNNING" = true ]; then
    echo "✅ Nginx Proxy Manager: RUNNING"
    echo "   🔧 Admin Panel: http://$SERVER_IP:81"
    echo "   🔑 Default Login: admin@example.com / changeme"
else
    echo "❌ Nginx Proxy Manager: FAILED"
    echo "🔍 Checking logs..."
    $DOCKER_COMPOSE logs npm | tail -10
fi

echo ""
echo "📋 Next Steps:"
echo "1. 🔐 Change Nginx Proxy Manager password (first login)"
echo "2. 🌐 Set up SSL certificate in Nginx Proxy Manager:"
echo "   - Add Proxy Host: $DOMAIN -> http://$SERVER_IP:8000"
echo "   - Request SSL Certificate (use your real email)"
echo "3. 🤖 Set up your Telegram bot webhook (if needed)"
echo "4. 💳 Test Stripe payments in your application"
echo ""
echo "🆘 Troubleshooting:"
echo "   📋 View logs: cd /opt/telebot && $DOCKER_COMPOSE logs"
echo "   🔄 Restart: cd /opt/telebot && $DOCKER_COMPOSE restart"
echo "   🛑 Stop: cd /opt/telebot && $DOCKER_COMPOSE down"
echo "   🚀 Start: cd /opt/telebot && $DOCKER_COMPOSE up -d"
echo ""

# Create convenient aliases
echo "🔧 Creating convenient management commands..."
cat > /usr/local/bin/telebot << 'EOF'
#!/bin/bash
cd /opt/telebot
case "$1" in
    logs)
        docker compose logs -f
        ;;
    restart)
        docker compose restart
        ;;
    stop)
        docker compose down
        ;;
    start)
        docker compose up -d
        ;;
    status)
        docker compose ps
        ;;
    update)
        git pull origin master
        docker compose up --build -d
        ;;
    *)
        echo "TeleBot Management Commands:"
        echo "  telebot logs    - View logs"
        echo "  telebot restart - Restart services"
        echo "  telebot stop    - Stop services"
        echo "  telebot start   - Start services"
        echo "  telebot status  - Show status"
        echo "  telebot update  - Update and rebuild"
        ;;
esac
EOF

chmod +x /usr/local/bin/telebot
echo "✅ TeleBot management commands installed!"
echo "   Use 'telebot' command for easy management"

echo ""
echo "🎊 Installation completed! Your TeleBot is ready to use!"
