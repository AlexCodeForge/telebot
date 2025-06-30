# Alpine Linux VPS Installation Guide for Telebot

This guide provides step-by-step instructions for installing the Telebot project on an Alpine Linux VPS using the automated installation script.

## Prerequisites

-   Fresh Alpine Linux x64 VPS
-   Root access to the server
-   Internet connection
-   Domain name (optional but recommended)

## Installation Steps

### 1. Connect to Your VPS

```bash
ssh root@your-server-ip
```

### 2. Download the Installation Script

```bash
wget https://raw.githubusercontent.com/AlexCodeForge/telebot/master/install-alpine.sh
chmod +x install-alpine.sh
```

### 3. Configure the Script (Optional but Recommended)

Edit the script to customize your installation:

```bash
nano install-alpine.sh
```

**Important Variables to Update:**

-   `DOMAIN="your-domain.com"` - Replace with your actual domain
-   API keys are already configured in the script, but you can modify them if needed

### 4. Run the Installation Script

```bash
./install-alpine.sh
```

The script will:

-   Update the Alpine Linux system
-   Install all required packages (PHP 8.2, MySQL, Nginx, Node.js, Docker)
-   Configure PHP-FPM and Nginx
-   Install and configure Nginx Proxy Manager
-   Clone the Telebot project from GitHub
-   Set up the Laravel environment with your API keys
-   Install all dependencies
-   Run database migrations
-   Start all services

### 5. Post-Installation Configuration

#### A. Access Nginx Proxy Manager

1. Open your browser and go to: `http://your-server-ip:81`
2. Login with default credentials:
    - Email: `admin@example.com`
    - Password: `changeme`
3. Change the default password immediately
4. Configure SSL certificates and domain routing

#### B. DNS Configuration

Point your domain to your server's IP address:

```
A record: your-domain.com → your-server-ip
A record: www.your-domain.com → your-server-ip
```

#### C. Webhook Configuration

Set up Stripe webhooks in your Stripe dashboard:

1. Go to Stripe Dashboard → Webhooks
2. Add endpoint: `https://your-domain.com/stripe/webhook`
3. Select events: `checkout.session.completed`
4. Copy the webhook secret and update your `.env` file

## What Gets Installed

### System Components

-   **PHP 8.2** with all required extensions
-   **MySQL/MariaDB** for database
-   **Nginx** web server
-   **Node.js & NPM** for asset compilation
-   **Docker & Docker Compose** for containerized services
-   **Composer** for PHP dependency management

### Services

-   **Nginx Proxy Manager** (Port 81) - SSL certificates and reverse proxy
-   **Laravel Application** - Your Telebot project
-   **MySQL Database** - Application data storage

### API Integrations

-   **Telegram Bot** - Configured with your bot token
-   **Stripe Payment** - Configured with your API keys

## File Locations

-   **Project Directory**: `/var/www/telebot`
-   **Nginx Configuration**: `/etc/nginx/conf.d/telebot.conf`
-   **Log File**: `/var/log/telebot-install.log`
-   **Nginx Proxy Manager**: `/opt/nginx-proxy-manager`

## Database Credentials

The script generates secure random passwords for your database. These will be displayed at the end of the installation and also saved in the log file.

**Save these credentials securely:**

-   Database Name: `telebot`
-   Database User: `telebot`
-   Database Password: (auto-generated)
-   MySQL Root Password: (auto-generated)

## Testing the Installation

1. **Check Services Status:**

    ```bash
    rc-service nginx status
    rc-service php-fpm82 status
    rc-service mariadb status
    docker ps
    ```

2. **Test Laravel:**

    ```bash
    cd /var/www/telebot
    php artisan --version
    php artisan migrate:status
    ```

3. **Access Your Site:**
    - Main site: `http://your-domain.com`
    - Nginx Proxy Manager: `http://your-server-ip:81`

## Troubleshooting

### Check Installation Logs

```bash
tail -f /var/log/telebot-install.log
```

### Restart Services

```bash
rc-service nginx restart
rc-service php-fpm82 restart
rc-service mariadb restart
```

### Check Laravel Logs

```bash
tail -f /var/www/telebot/storage/logs/laravel.log
```

### Verify Database Connection

```bash
cd /var/www/telebot
php artisan tinker
# In tinker: DB::connection()->getPdo();
```

## Security Considerations

1. **Change Default Passwords** - Especially for Nginx Proxy Manager
2. **Configure SSL** - Use Nginx Proxy Manager to set up Let's Encrypt
3. **Firewall Setup** - Only allow necessary ports (80, 443, 22, 81)
4. **Regular Updates** - Keep your system and dependencies updated

## Support

If you encounter any issues:

1. Check the installation log: `/var/log/telebot-install.log`
2. Verify all services are running
3. Check Laravel logs for application-specific errors
4. Ensure your domain DNS is properly configured

## Manual Cleanup (If Needed)

If you need to start over:

```bash
# Stop services
rc-service nginx stop
rc-service php-fpm82 stop
rc-service mariadb stop
docker-compose -f /opt/nginx-proxy-manager/docker-compose.yml down

# Remove project
rm -rf /var/www/telebot

# Remove nginx config
rm -f /etc/nginx/conf.d/telebot.conf

# Drop database (optional)
mysql -u root -p -e "DROP DATABASE telebot; DROP USER 'telebot'@'localhost';"
```

---

**Note**: This installation script is designed for production use but includes development-friendly configurations. Always review and adjust settings based on your specific security and performance requirements.
