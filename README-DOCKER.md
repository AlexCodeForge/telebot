# TeleBot Docker Deployment

This Docker setup provides a **100% guaranteed** way to deploy your Laravel TeleBot application with SSL certificates and domain configuration.

## 🚀 Quick Start

1. **Clone the repository:**

    ```bash
    git clone https://github.com/AlexCodeForge/telebot.git
    cd telebot
    ```

2. **Run the deployment script:**

    ```bash
    chmod +x deploy-telebot.sh
    ./deploy-telebot.sh "YOUR_TELEGRAM_TOKEN" "YOUR_STRIPE_PUBLIC" "YOUR_STRIPE_SECRET"
    ```

3. **Configure SSL and domain:**
    - Access Nginx Proxy Manager at `http://your-server-ip:81`
    - Login with: `admin@example.com` / `changeme`
    - Add a new Proxy Host for `alexcodeforge.com` pointing to `telebot-app:80`
    - Enable SSL with Let's Encrypt

## 📦 What's Included

-   **Laravel TeleBot Application** (port 8000)
-   **Nginx Proxy Manager** (ports 80, 443, 81)
-   **SQLite Database** (persistent storage)
-   **SSL Certificates** (automatic via Let's Encrypt)
-   **Production Configuration** (optimized for performance)

## 🔧 Configuration

The deployment script requires these credentials:

-   **Domain:** alexcodeforge.com
-   **Telegram Bot Token:** YOUR_TELEGRAM_BOT_TOKEN
-   **Stripe Public Key:** YOUR_STRIPE_PUBLIC_KEY
-   **Stripe Secret Key:** YOUR_STRIPE_SECRET_KEY

## 🌐 SSL Certificate Setup

1. **Access Nginx Proxy Manager:**

    ```
    URL: http://your-server-ip:81
    Email: admin@example.com
    Password: changeme
    ```

2. **Change Default Password:**

    - Go to Users → Admin User
    - Update email and password immediately

3. **Add Proxy Host:**

    - Domain Names: `alexcodeforge.com`
    - Forward Hostname/IP: `telebot-app`
    - Forward Port: `80`
    - Enable "Block Common Exploits"

4. **Enable SSL:**
    - Go to SSL tab
    - Select "Request a new SSL Certificate"
    - Enable "Force SSL" and "HTTP/2 Support"
    - Add your email for Let's Encrypt notifications

## 🤖 Telegram Webhook Setup

Once your domain has SSL enabled:

1. **Set the webhook URL:**

    ```
    https://alexcodeforge.com/api/telegram/webhook
    ```

2. **Test the webhook:**
    - Send a message to your bot
    - Check logs: `docker-compose logs -f telebot`

## 👤 Admin Access

-   **URL:** `https://alexcodeforge.com`
-   **Email:** `admin@admin.com`
-   **Password:** `password`

## 🛠️ Management Commands

```bash
# View all logs
docker-compose logs -f

# View specific service logs
docker-compose logs -f telebot
docker-compose logs -f nginx-proxy-manager

# Restart services
docker-compose restart

# Stop everything
docker-compose down

# Update and rebuild
git pull
docker-compose up --build -d

# Access Laravel container
docker-compose exec telebot bash

# Run Laravel commands
docker-compose exec telebot php artisan migrate
docker-compose exec telebot php artisan db:seed
```

## 🔍 Troubleshooting

### Container Issues

```bash
# Check container status
docker-compose ps

# Check container health
docker-compose logs telebot

# Rebuild containers
docker-compose down
docker-compose up --build -d
```

### SSL Issues

-   Ensure domain DNS points to your server
-   Check port 80/443 are accessible from internet
-   Verify Let's Encrypt rate limits haven't been hit

### Database Issues

```bash
# Reset database
docker-compose exec telebot php artisan migrate:fresh --seed
```

## 📊 File Structure

```
telebot/
├── docker/
│   ├── nginx.conf          # Nginx configuration
│   ├── supervisord.conf    # Process manager
│   └── entrypoint.sh       # Container startup script
├── Dockerfile              # Container definition
├── docker-compose.yml      # Multi-container setup
└── deploy-telebot.sh       # One-command deployment
```

## 🔒 Security Features

-   ✅ **Secure credential handling** - No secrets in repository
-   ✅ **SSL/TLS encryption** - Automatic HTTPS certificates
-   ✅ **Container isolation** - Isolated application environment
-   ✅ **Security headers** - Nginx security configuration
-   ✅ **File permissions** - Proper container permissions
-   ✅ **Network isolation** - Docker network segmentation

## 🚀 Performance Features

-   ✅ **PHP-FPM** - Optimized PHP processing
-   ✅ **Nginx** - High-performance web server
-   ✅ **Asset compilation** - Pre-built frontend assets
-   ✅ **Configuration caching** - Laravel optimization
-   ✅ **SQLite** - Fast, embedded database

This Docker solution eliminates all OS-specific issues and provides a consistent, reproducible deployment environment.
