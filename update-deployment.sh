#!/bin/bash

# Update TeleBot deployment with admin user migration
echo "🔄 Updating TeleBot deployment with admin user migration..."

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker first."
    exit 1
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
    echo "👤 Admin login: admin@telebot.com / admin123"
else
    echo "❌ TeleBot app failed to start. Checking logs..."
    $DOCKER_COMPOSE logs app
fi

echo "🔧 Nginx Proxy Manager admin: http://207.148.24.73:81"
echo "   Default credentials: admin@example.com / changeme"
