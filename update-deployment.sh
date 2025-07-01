#!/bin/bash

# Update TeleBot deployment with admin user migration
echo "🔄 Updating TeleBot deployment with admin user migration..."

# Check if required parameters are provided
if [ $# -lt 3 ]; then
    echo "❌ Usage: $0 <telegram_token> <stripe_public_key> <stripe_secret_key> [domain]"
    echo "📝 Example: $0 'YOUR_TELEGRAM_TOKEN' 'pk_test_...' 'sk_test_...' 'alexcodeforge.com'"
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

# Function to install Docker
install_docker() {
    echo "🐳 Installing Docker..."

    # Update package index
    apt-get update

    # Install prerequisites
    apt-get install -y ca-certificates curl gnupg lsb-release

    # Add Docker's official GPG key
    mkdir -p /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg

    # Set up the repository
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null

    # Update package index again
    apt-get update

    # Install Docker Engine
    apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

    # Start and enable Docker
    systemctl start docker
    systemctl enable docker

    # Add current user to docker group (if not root)
    if [ "$EUID" -ne 0 ]; then
        usermod -aG docker $USER
        echo "⚠️  Please log out and back in for Docker group changes to take effect"
    fi

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
        echo "❌ Failed to start Docker. Trying to reinstall..."
        install_docker
    fi
else
    echo "✅ Docker is already installed and running"
fi

# Detect docker-compose command
if command -v docker-compose &> /dev/null; then
    DOCKER_COMPOSE="docker-compose"
elif command -v docker &> /dev/null && docker compose version &> /dev/null; then
    DOCKER_COMPOSE="docker compose"
else
    echo "❌ Neither 'docker-compose' nor 'docker compose' found. Please install Docker Compose."
    exit 1
fi

echo "📦 Using Docker Compose command: $DOCKER_COMPOSE"

# Install git if not present
if ! command -v git &> /dev/null; then
    echo "📦 Installing git..."
    apt-get update
    apt-get install -y git
fi

# Clone or update repository
REPO_DIR="/opt/telebot"
if [ -d "$REPO_DIR" ]; then
    echo "📁 Updating existing repository..."
    cd "$REPO_DIR"
    git pull origin master
else
    echo "📁 Cloning repository..."
    git clone https://github.com/AlexCodeForge/telebot.git "$REPO_DIR"
    cd "$REPO_DIR"
fi

# Make sure we're in the right directory
cd "$REPO_DIR"
echo "📂 Working in directory: $(pwd)"

# Create .env file with the provided credentials
echo "📝 Creating .env file with your credentials..."
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

# Stop and remove existing containers
echo "🛑 Stopping existing containers..."
$DOCKER_COMPOSE down --remove-orphans || true

# Remove existing images to force rebuild
echo "🗑️ Removing old images..."
docker rmi telebot-app:latest || true

# Rebuild and start
echo "🔨 Rebuilding and starting containers..."
$DOCKER_COMPOSE up --build -d

# Wait for services to be ready
echo "⏳ Waiting for services to start..."
sleep 30

# Check if services are running
if docker ps | grep -q "telebot-app"; then
    echo "✅ TeleBot app is running!"
    echo "🌐 Access your app at: http://207.148.24.73:8000"
    echo "🌐 Access via domain: https://$DOMAIN (after SSL setup)"
    echo "👤 Admin login: admin@telebot.com / admin123"
else
    echo "❌ TeleBot app failed to start. Checking logs..."
    $DOCKER_COMPOSE logs app
fi

echo "🔧 Nginx Proxy Manager admin: http://207.148.24.73:81"
echo "   Default credentials: admin@example.com / changeme"
echo ""
echo "📋 Next steps:"
echo "   1. Set up SSL in Nginx Proxy Manager (port 81)"
echo "   2. Use a real email address for Let's Encrypt"
echo "   3. Access your site at https://$DOMAIN"
