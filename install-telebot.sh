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

# Detect OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    VERSION=$VERSION_ID
else
    echo "❌ Cannot detect OS. This script supports Ubuntu/Debian only."
    exit 1
fi

echo "🔍 Detected OS: $OS $VERSION"

# Update system packages
echo "📦 Updating system packages..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y

# Install essential tools first
echo "🛠️ Installing essential tools..."
apt-get install -y curl wget apt-transport-https ca-certificates gnupg lsb-release software-properties-common

# Function to install Docker via snap (fallback method)
install_docker_snap() {
    echo "🐳 Installing Docker via snap (fallback method)..."
    apt-get install -y snapd
    snap install docker
    systemctl start snap.docker.dockerd
    systemctl enable snap.docker.dockerd
    ln -sf /snap/bin/docker /usr/local/bin/docker

    # Wait for Docker to be ready
    sleep 10
    if docker --version; then
        echo "✅ Docker installed via snap!"
        return 0
    else
        return 1
    fi
}

# Function to install Docker via convenience script
install_docker_convenience() {
    echo "🐳 Installing Docker via convenience script..."
    curl -fsSL https://get.docker.com -o get-docker.sh
    sh get-docker.sh
    rm get-docker.sh

    systemctl start docker
    systemctl enable docker

    if docker --version; then
        echo "✅ Docker installed via convenience script!"
        return 0
    else
        return 1
    fi
}

# Function to install Docker the standard way
install_docker_standard() {
    echo "🐳 Installing Docker (standard method)..."

    # Remove old Docker versions
    apt-get remove -y docker docker-engine docker.io containerd runc 2>/dev/null || true

    # Create keyrings directory
    mkdir -p /etc/apt/keyrings

    # Try to add Docker's GPG key (with fallback)
    if ! curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg 2>/dev/null; then
        echo "⚠️  Failed to add GPG key via curl, trying wget..."
        if ! wget -qO- https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg 2>/dev/null; then
            echo "❌ Failed to add Docker GPG key"
            return 1
        fi
    fi

    chmod a+r /etc/apt/keyrings/docker.gpg

    # Add Docker repository
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null

    # Update package index
    apt-get update -y

    # Install Docker
    if apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin; then
        systemctl start docker
        systemctl enable docker

        if docker --version; then
            echo "✅ Docker installed (standard method)!"
            return 0
        fi
    fi

    return 1
}

# Try to install Docker (multiple methods)
if command -v docker &> /dev/null && docker --version &> /dev/null; then
    echo "✅ Docker is already installed"
    # Try to start it if it's not running
    if ! docker info > /dev/null 2>&1; then
        echo "🔧 Starting Docker service..."
        systemctl start docker || service docker start || true
        sleep 5
    fi
else
    echo "🔍 Docker not found. Attempting installation..."

    # Try standard method first
    if ! install_docker_standard; then
        echo "⚠️  Standard installation failed, trying convenience script..."
        if ! install_docker_convenience; then
            echo "⚠️  Convenience script failed, trying snap..."
            if ! install_docker_snap; then
                echo "❌ All Docker installation methods failed."
                echo "🔧 Please install Docker manually and run this script again."
                exit 1
            fi
        fi
    fi
fi

# Final Docker verification
if ! docker --version; then
    echo "❌ Docker installation verification failed"
    exit 1
fi

# Try to start Docker if it's not running
if ! docker info > /dev/null 2>&1; then
    echo "🔧 Docker not responding, attempting to start..."
    systemctl start docker 2>/dev/null || service docker start 2>/dev/null || snap start docker 2>/dev/null || true
    sleep 10

    if ! docker info > /dev/null 2>&1; then
        echo "❌ Docker failed to start. Please check system logs:"
        echo "   systemctl status docker"
        echo "   journalctl -u docker"
        exit 1
    fi
fi

echo "✅ Docker is running!"

# Install Docker Compose if not available
if ! docker compose version &> /dev/null && ! command -v docker-compose &> /dev/null; then
    echo "📦 Installing Docker Compose..."

    # Try to install standalone docker-compose
    COMPOSE_VERSION=$(curl -s https://api.github.com/repos/docker/compose/releases/latest | grep -Po '"tag_name": "\K.*?(?=")')
    curl -L "https://github.com/docker/compose/releases/download/${COMPOSE_VERSION}/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
fi

# Determine Docker Compose command
if docker compose version &> /dev/null; then
    DOCKER_COMPOSE="docker compose"
    echo "📦 Using docker compose plugin"
elif command -v docker-compose &> /dev/null; then
    DOCKER_COMPOSE="docker-compose"
    echo "📦 Using standalone docker-compose"
else
    echo "❌ Docker Compose not available"
    exit 1
fi

# Install git if not present
if ! command -v git &> /dev/null; then
    echo "📦 Installing git..."
    apt-get install -y git
fi

# Install other useful tools
echo "🛠️ Installing additional tools..."
apt-get install -y unzip htop nano ufw

# Set up firewall
echo "🔥 Configuring firewall..."
ufw --force reset
ufw --force enable
ufw allow ssh
ufw allow 22/tcp
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

# Get server IP for proper URL configuration
SERVER_IP=$(curl -s http://ipv4.icanhazip.com/ 2>/dev/null || curl -s http://ifconfig.me/ 2>/dev/null || echo "localhost")

# Create database directory on host with proper permissions
echo "📁 Creating database directory on host..."
mkdir -p database storage
touch database/database.sqlite
chown -R 1000:1000 database storage
chmod 775 database
chmod 664 database/database.sqlite

# Generate Laravel application key on host
echo "🔑 Generating Laravel application key..."
APP_KEY="base64:$(openssl rand -base64 32)"
echo "✅ Generated APP_KEY: ${APP_KEY:0:20}..."

# Create .env file with the provided credentials
echo "📝 Creating environment configuration..."
cat > .env << EOF
APP_NAME="TeleBot Video Store"
APP_ENV=local
APP_KEY=$APP_KEY
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_URL=http://$SERVER_IP:8000

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
$DOCKER_COMPOSE down --remove-orphans 2>/dev/null || true

# Clean up old images and containers
echo "🗑️ Cleaning up Docker..."
docker container prune -f 2>/dev/null || true
docker image prune -f 2>/dev/null || true
docker volume prune -f 2>/dev/null || true

# Build and start containers
echo "🔨 Building and starting TeleBot containers..."
echo "   ⏳ This may take a few minutes on first run..."

# Build with no cache to ensure fresh build
if ! $DOCKER_COMPOSE build --no-cache; then
    echo "❌ Failed to build containers. Checking for issues..."
    df -h
    docker system df
    exit 1
fi

# Start containers
if ! $DOCKER_COMPOSE up -d; then
    echo "❌ Failed to start containers. Checking logs..."
    $DOCKER_COMPOSE logs
    exit 1
fi

# Wait for services to be ready
echo "⏳ Waiting for services to initialize..."
sleep 45

# Extended health check function
check_service() {
    local service_name="$1"
    local port="$2"
    local timeout=120
    local count=0

    echo "🔍 Checking $service_name on port $port..."
    while [ $count -lt $timeout ]; do
        if curl -s -o /dev/null -w "%{http_code}" http://localhost:$port | grep -E "^(200|302|404)$" > /dev/null; then
            echo "✅ $service_name is running!"
            return 0
        fi
        sleep 2
        count=$((count + 2))
    done
    echo "❌ $service_name failed to start"
    return 1
}

# Advanced debugging function
debug_app_failure() {
    echo "🔍 Performing advanced diagnostics..."

    echo "📋 Container status:"
    $DOCKER_COMPOSE ps

    echo "🔑 Checking APP_KEY:"
    $DOCKER_COMPOSE exec app env | grep APP_KEY || echo "❌ Could not check APP_KEY"

    echo "📁 Database file status:"
    $DOCKER_COMPOSE exec app ls -la /var/www/html/database/ || echo "❌ Could not check database"

    echo "🐛 Laravel error logs:"
    $DOCKER_COMPOSE exec app cat /var/www/html/storage/logs/laravel.log 2>/dev/null | tail -20 || echo "❌ No Laravel logs found"

    echo "🐛 PHP error logs:"
    $DOCKER_COMPOSE exec app cat /var/log/nginx/error.log 2>/dev/null | tail -10 || echo "❌ No PHP error logs found"

    echo "🧪 Direct PHP test:"
    $DOCKER_COMPOSE exec app php -r "
    echo 'PHP Version: ' . PHP_VERSION . \"\n\";
    if (getenv('APP_KEY')) {
        echo 'APP_KEY: Set (' . substr(getenv('APP_KEY'), 0, 20) . '...)\n';
    } else {
        echo 'APP_KEY: NOT SET!\n';
    }
    " 2>/dev/null || echo "❌ Could not run PHP test"
}

# Check if services are running
echo "🏥 Running health checks..."
APP_RUNNING=false
NPM_RUNNING=false

# Check TeleBot app
if docker ps | grep -q "telebot-app"; then
    echo "🔍 TeleBot container is running, checking application..."
    if check_service "TeleBot App" 8000; then
        APP_RUNNING=true
    else
        echo "🔍 TeleBot app not responding, running diagnostics..."
        debug_app_failure
    fi
else
    echo "❌ TeleBot app container not found"
    $DOCKER_COMPOSE ps
fi

# Check Nginx Proxy Manager
if docker ps | grep -q "npm"; then
    if check_service "Nginx Proxy Manager" 81; then
        NPM_RUNNING=true
    fi
else
    echo "❌ Nginx Proxy Manager container not found"
fi

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
    echo ""
    echo "🔍 Debugging Information:"
    echo "Container Status:"
    $DOCKER_COMPOSE ps
    echo ""
    echo "Recent App Logs:"
    $DOCKER_COMPOSE logs app | tail -50
    echo ""
    echo "🔧 Try manual restart:"
    echo "   cd /opt/telebot"
    echo "   docker compose restart app"
    echo "   docker compose logs app -f"
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
echo "   📋 View logs: cd /opt/telebot && $DOCKER_COMPOSE logs app -f"
echo "   🔄 Restart: cd /opt/telebot && $DOCKER_COMPOSE restart"
echo "   🛑 Stop: cd /opt/telebot && $DOCKER_COMPOSE down"
echo "   🚀 Start: cd /opt/telebot && $DOCKER_COMPOSE up -d"
echo ""

# Create convenient aliases
echo "🔧 Creating convenient management commands..."
cat > /usr/local/bin/telebot << 'EOF'
#!/bin/bash
cd /opt/telebot

# Detect Docker Compose command
if command -v docker-compose &> /dev/null; then
    DC="docker-compose"
elif docker compose version &> /dev/null; then
    DC="docker compose"
else
    echo "❌ Docker Compose not found"
    exit 1
fi

case "$1" in
    logs)
        $DC logs app -f
        ;;
    restart)
        $DC restart
        ;;
    stop)
        $DC down
        ;;
    start)
        $DC up -d
        ;;
    status)
        $DC ps
        ;;
    update)
        git pull origin master
        $DC build --no-cache
        $DC up -d
        ;;
    rebuild)
        $DC down
        $DC build --no-cache
        $DC up -d
        ;;
    *)
        echo "TeleBot Management Commands:"
        echo "  telebot logs    - View app logs"
        echo "  telebot restart - Restart services"
        echo "  telebot stop    - Stop services"
        echo "  telebot start   - Start services"
        echo "  telebot status  - Show status"
        echo "  telebot update  - Update and rebuild"
        echo "  telebot rebuild - Force rebuild"
        ;;
esac
EOF

chmod +x /usr/local/bin/telebot
echo "✅ TeleBot management commands installed!"
echo "   Use 'telebot' command for easy management"

echo ""
echo "🎊 Installation completed! Your TeleBot is ready to use!"
