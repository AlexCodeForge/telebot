# Product Requirements Document (PRD)

## 1. Introduction

This document outlines the requirements for a digital video sales platform that leverages Telegram for content delivery. The system will allow users to purchase videos through a web interface and receive them automatically via Telegram.

## 2. Objectives

-   Facilitate the efficient sale and delivery of digital video content
-   Minimize hosting and bandwidth costs by utilizing Telegram's cloud for videos
-   Provide a simple and automated user experience

## 3. Audience

-   **Buyers**: Users interested in acquiring video content
-   **Administrator/Seller**: Person or entity who uploads the content and manages sales

## 4. Key Features

### 4.1. Simple Web Page (Frontend)

-   **Video Catalog**: A page listing videos available for purchase, including title, description, and price
-   **Video Detail**: Each video will have a detail page with more information
-   **Purchase Button**: A clear button to initiate the purchase process (redirecting to Stripe Checkout)
-   **Responsive Design**: The page must be accessible and functional on mobile and desktop devices

### 4.2. Backend (Laravel)

-   **Content Management**:

    -   CRUD (Create, Read, Update, Delete) for videos (title, description, price, Telegram file ID)
    -   Association of a "Telegram file ID" with each video, which is the identifier of the video once uploaded to Telegram

-   **Stripe Integration (via Laravel Cashier)**:

    -   Utilize Laravel Cashier for simplified management of Stripe subscriptions and one-time charges
    -   Handling Stripe webhooks to detect successful purchases
    -   Recording purchase transactions

-   **Telegram Bot Integration**:
    -   Communication with the Telegram API to send videos
    -   Storage of the user's Telegram chat_id (obtained by interacting with the bot) for future deliveries

### 4.3. Telegram Bot

-   **Bot Initiation**: The user interacts with the bot (e.g., /start) to register their chat_id
-   **Purchase Detection**: Once the Laravel backend detects a successful purchase via Stripe, the bot is notified
-   **Automatic Video Delivery**: The bot will automatically send the corresponding video to the buyer using the stored "Telegram file ID" and the user's chat_id

### 4.4. Payment Integration (Stripe)

-   **Stripe Checkout**: Use Stripe Checkout, integrated with Laravel Cashier, to process payments securely and simply
-   **Stripe Webhooks**: Configuration of webhooks (managed by Cashier where applicable) to notify the backend about successful payment events

## 5. High-Level User Flow

1. User navigates to the web page
2. User selects a video and clicks "Buy"
3. User is redirected to Stripe Checkout (powered by Laravel Cashier) to complete the payment
4. User starts a conversation with the Telegram bot (e.g., by sending /start)
5. Stripe sends a checkout.session.completed webhook to the Laravel backend (handled by Cashier) after a successful payment
6. Backend (Laravel) verifies the purchase and the user's chat_id
7. Backend (Laravel) instructs the Telegram Bot to send the video to the buyer's chat_id, using the predefined "Telegram file ID"
8. User receives the video in Telegram

## 6. Technical Requirements and Platforms

-   **Web Framework**: Laravel
-   **Telegram Bot SDK**: `https://telegram-bot-sdk.com/` (for PHP)
-   **Payment Gateway**: Stripe (integrated with Laravel Cashier)
-   **Video Storage**: Telegram Cloud (videos will be manually uploaded to Telegram once to obtain the file_id, which will then be stored in the Laravel database)
-   **Database**: A Laravel-compatible database will be used (e.g., MySQL, PostgreSQL)

## 7. Success Metrics

-   Successful video purchases and deliveries
-   User satisfaction with the purchase and delivery process
-   Reduced hosting costs compared to traditional video hosting solutions
-   Admin efficiency in managing video content

## 8. Constraints and Limitations

-   Telegram file size limitations (currently 2GB per file)
-   Dependency on Telegram's availability and API
-   Stripe payment processing fees
-   Need for users to have or create a Telegram account

## 9. Future Considerations

-   Subscription-based access to video content
-   Multiple language support
-   Enhanced analytics for sales and user behavior
-   Mobile applications for improved user experience

## API Configuration

API keys should be configured in the `.env` file:

```env
STRIPE_KEY=pk_test_[your_stripe_public_key]
STRIPE_SECRET=sk_test_[your_stripe_secret_key]
TELEGRAM_BOT_TOKEN=[your_telegram_bot_token]
```

**Note**: Never commit actual API keys to version control. Use environment variables for all sensitive data.
