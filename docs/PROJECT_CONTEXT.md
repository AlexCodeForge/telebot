# Telebot Video Store - Project Context & Development Guide

## 📖 Project Overview
Telebot is a Laravel-based video store platform that combines e-commerce functionality with Telegram bot integration for secure video delivery. Customers purchase videos through a web interface using Stripe payments and receive them automatically via a Telegram bot after verification.

---

## 🏗️ System Architecture

### **Core Components**
- **Laravel 11** backend with Blade templating
- **Stripe** payment processing integration
- **Telegram Bot** for video delivery
- **MySQL** database with comprehensive purchase tracking
- **Bootstrap 5** + Custom CSS for responsive UI
- **Windows-compatible** file handling system

### **Key Models & Relationships**
```
User ←→ Purchase ←→ Video
   ↓
Purchase has:
- purchase_uuid (security)
- telegram_username (linking)
- telegram_user_id (verification)
- verification_status (pending → verified)
- delivery_status (pending → delivered/failed)
```

---

## 🔒 Security Architecture

### **Purchase Security**
- **UUID-based URLs**: All purchase pages use UUIDs instead of incremental IDs to prevent enumeration attacks
- **Telegram Verification**: Two-step process linking purchases to verified Telegram users
- **Username Edit Protection**: Comprehensive logging and validation for username changes

### **Access Control**
- **Admin Panel**: Full purchase management with filtering, search, and manual controls
- **Customer Access**: Secure purchase viewing with auto-refresh and edit capabilities
- **Bot Security**: Rate limiting removed but comprehensive audit logging implemented

---

## 💳 Payment & Purchase Flow

### **1. Customer Purchase Process**
```
1. Browse videos → Select video → Payment form (with Telegram username)
2. Stripe checkout → Success page with secure UUID URL
3. Customer receives instructions to contact Telegram bot
4. Customer sends /start to bot → Automatic verification & delivery
```

### **2. Verification & Delivery System**
```
Telegram Username → Telegram User ID → Video Delivery
        ↓                    ↓              ↓
   (Customer Input)    (Bot Verification)  (Automated)
```

### **3. Admin Oversight**
- Real-time purchase monitoring with statistics dashboard
- Manual verification and delivery controls
- Comprehensive search and filtering capabilities
- Username edit functionality for typo corrections

---

## 🤖 Telegram Bot Features

### **Customer Commands**
- `/start` - Verify and link recent pending purchases
- `/mypurchases` - Show ALL verified videos with download IDs
- `/getvideo <id>` - Download specific video by ID
- `/help` - Complete command reference

### **Bot Security Features**
- **Single Recent Purchase**: `/start` only verifies the most recent pending purchase to prevent exploitation
- **Unlimited Access**: No artificial limits on `/mypurchases` - users see all their paid content
- **Comprehensive Logging**: All bot interactions logged for security monitoring
- **User ID Verification**: Links Telegram usernames to user IDs for secure delivery

---

## 🎨 Thumbnail & Preview System

### **Admin Features**
- **Dual Upload Options**: Local file uploads OR external URL linking
- **Blur Control System**: Configurable blur intensity (1-20px) with admin controls
- **Preview Management**: Toggle switches for blur enable/disable and preview permissions
- **File Handling**: Windows-compatible native PHP file operations (due to Laravel Storage issues)

### **Customer Experience**
- **Interactive Previews**: Hover-to-preview with 3-second auto-hide tease effect
- **Click-to-Toggle**: Full blur/unblur control on individual video pages
- **Responsive Design**: Thumbnails adapt to different screen sizes
- **Smart Fallbacks**: Graceful handling when thumbnails are unavailable

---

## 🔧 Technical Implementation Details

### **Database Schema Highlights**
```sql
-- Videos table with comprehensive thumbnail support
videos: id, title, file_id, price, thumbnail_path, thumbnail_url, 
        show_blurred_thumbnail, blur_intensity, allow_preview

-- Purchases with security & verification
purchases: id, purchase_uuid, video_id, telegram_username, telegram_user_id,
           verification_status, delivery_status, amount, purchase_status

-- Users with admin capabilities
users: id, email, telegram_username, telegram_chat_id, is_admin
```

### **File Storage Strategy**
- **Thumbnails**: Stored in `storage/app/public/thumbnails/` using timestamp+video_id naming
- **Cleanup**: Automatic old thumbnail deletion when new ones are uploaded
- **Compatibility**: Native PHP `move_uploaded_file()` and `unlink()` for Windows compatibility

### **Error Handling & Logging**
- Comprehensive error logging for all operations
- Graceful fallbacks for missing thumbnails or failed operations
- User-friendly error messages with technical details logged separately

---

## 🚀 Deployment & VPS Setup

### **VPS Deployment Command**
```bash
# Run this on your VPS to deploy the latest changes:
cd /path/to/telebot && git pull origin master && composer install --no-dev && php artisan migrate && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### **Production Configuration**
1. **Environment Setup**: Configure `.env` with production database, Stripe keys, and bot token
2. **Storage Linking**: Run `php artisan storage:link` for public thumbnail access
3. **File Permissions**: Ensure proper permissions for `storage/` and `bootstrap/cache/`
4. **Cron Jobs**: Set up Laravel scheduler for any background tasks
5. **HTTPS**: Required for Stripe webhooks and Telegram bot security

### **Monitoring & Maintenance**
- Monitor Laravel logs for bot interactions and purchase errors
- Regular database backups (includes purchase UUIDs and user data)
- Telegram webhook health monitoring
- Stripe dashboard monitoring for payment issues

---

## 🛠️ Development Workflow

### **Local Development Setup**
1. Clone repository and run `composer install`
2. Copy `.env.example` to `.env` and configure local database
3. Run `php artisan migrate` and `php artisan storage:link`
4. Configure Telegram bot webhook to point to local tunnel (ngrok)
5. Set up Stripe test keys for payment testing

### **Key Development Areas**

#### **Admin Panel Enhancements** (`resources/views/admin/`)
- Purchase management with advanced filtering
- Video management with thumbnail controls
- Bot testing and diagnostics tools

#### **Customer Interface** (`resources/views/videos/`, `resources/views/payment/`)
- Video browsing with thumbnail previews
- Secure purchase flow with UUID protection
- Username edit functionality for typo corrections

#### **Bot Integration** (`app/Http/Controllers/TelegramController.php`)
- Webhook message processing
- Command handling with security measures
- Video delivery automation

#### **Payment Processing** (`app/Http/Controllers/PaymentController.php`)
- Stripe integration with UUID generation
- Purchase verification and status tracking
- Username update functionality

---

## 🎯 Key Features Summary

### **✅ Implemented Features**
- **Secure Purchase System**: UUID-based URLs with comprehensive verification
- **Telegram Bot Integration**: Full command suite with unlimited video access
- **Thumbnail System**: Complete blur/preview functionality with admin controls
- **Admin Dashboard**: Purchase management with filtering, search, and manual controls
- **Username Edit**: Both customer and admin can correct typos for better UX
- **Payment Integration**: Stripe checkout with automatic delivery workflow
- **Windows Compatibility**: Resolved file handling issues for development environment

### **🔒 Security Features**
- Purchase URL enumeration prevention (UUID-based)
- Telegram user verification system
- Comprehensive audit logging
- Admin-controlled username corrections
- Secure bot command processing

### **📱 User Experience Features**
- Auto-refreshing purchase pages
- Interactive thumbnail previews
- Unlimited access to purchased content
- Clear purchase instructions and status updates
- Mobile-responsive design throughout

---

## 📝 Future Development Considerations

### **Potential Enhancements**
1. **Batch Operations**: Admin tools for bulk purchase management
2. **Analytics Dashboard**: Purchase trends and customer behavior insights
3. **Video Categories**: Organizational structure for larger video libraries
4. **Subscription System**: Recurring payment options for premium content
5. **Advanced Bot Features**: Video search, categories, recommendations

### **Technical Debt & Improvements**
1. **API Endpoints**: RESTful API for mobile app development
2. **Queue System**: Background processing for video delivery and notifications
3. **Cache Layer**: Redis integration for improved performance
4. **Testing Suite**: Comprehensive unit and feature tests
5. **Docker Support**: Containerization for easier deployment

---

## 📞 Support & Troubleshooting

### **Common Issues & Solutions**
1. **Thumbnail Upload Failures**: Check file permissions and storage configuration
2. **Bot Not Responding**: Verify webhook URL and token configuration
3. **Payment Issues**: Check Stripe configuration and webhook endpoints
4. **Username Edit Problems**: Verify CSRF tokens and route accessibility

### **Debugging Tools**
- Laravel logs: `storage/logs/laravel.log`
- Bot emulator: `/bot-test` route for local testing
- System status: `/system-status` route for health checks
- Admin diagnostics: Built into admin panel for connection testing

---

*Last updated: January 2025*
*Project Version: 1.0 - Production Ready* 
