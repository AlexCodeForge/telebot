# Vercel Blob Storage Setup

This document explains how to set up Vercel Blob storage for thumbnail uploads in your Laravel Telegram bot application.

## What is Vercel Blob?

Vercel Blob is a serverless file storage solution that's perfect for storing images, videos, and other files in serverless applications. It's **free** for up to 1GB of storage and bandwidth.

## Why Use Vercel Blob?

When deploying Laravel on Vercel, the filesystem is **read-only** and **ephemeral**. This means:
- You can't write files to the local filesystem
- Any files you do write are lost when the function restarts
- Traditional Laravel file uploads don't work

Vercel Blob solves this by providing persistent cloud storage that's:
- ✅ **Free** (up to 1GB)
- ✅ **Fast** (global CDN)
- ✅ **Serverless-friendly**
- ✅ **Automatically backed up**

## Setup Instructions

### 1. Create a Vercel Blob Store

1. Go to your [Vercel Dashboard](https://vercel.com/dashboard)
2. Navigate to your project
3. Go to the **Storage** tab
4. Click **Create Database** → **Blob**
5. Enter a name for your store (e.g., `telebot-thumbnails`)
6. Click **Create**

### 2. Get Your Blob Token

1. After creating the store, click on it
2. Go to the **Settings** tab
3. Copy the **BLOB_READ_WRITE_TOKEN** value

### 3. Add Token to Vercel Environment Variables

1. In your Vercel project dashboard
2. Go to **Settings** → **Environment Variables**
3. Add a new variable:
   - **Name**: `BLOB_READ_WRITE_TOKEN`
   - **Value**: Your token from step 2
   - **Environments**: Production, Preview, Development

### 4. Deploy Your Application

Once the environment variable is set, deploy your application:

```bash
# If using Vercel CLI
vercel --prod

# Or push to your connected Git repository
git add .
git commit -m "Add Vercel Blob storage for thumbnails"
git push origin main
```

## How It Works

### Thumbnail Upload Flow

1. **Admin uploads thumbnail** via the admin panel
2. **File is processed** by Laravel (validation, etc.)
3. **File is uploaded** directly to Vercel Blob storage
4. **Public URL is returned** and saved to database
5. **Thumbnail displays** using the Vercel Blob URL

### Database Schema

The application uses these fields to store thumbnail information:

```sql
-- Local file path (legacy/fallback)
thumbnail_path VARCHAR(255) NULL

-- External URL (user-provided)
thumbnail_url TEXT NULL  

-- Vercel Blob URL (new/preferred)
thumbnail_blob_url TEXT NULL
```

### Priority Order

The application checks for thumbnails in this order:

1. **Vercel Blob URL** (highest priority)
2. **Local file path** (fallback for existing uploads)
3. **External URL** (user-provided)

## Testing

After setup, you can test thumbnail uploads:

1. Log into the admin panel
2. Edit any video
3. Upload a thumbnail image
4. Check that the thumbnail displays correctly
5. The image should be served from a `blob.vercel-storage.com` URL

## Troubleshooting

### "Blob token not found" Error

- Check that `BLOB_READ_WRITE_TOKEN` is set in Vercel environment variables
- Redeploy your application after adding the variable

### "Failed to upload thumbnail" Error

- Check your Vercel Blob store limits (1GB free)
- Verify the token has read/write permissions
- Check Vercel function logs for detailed errors

### Old Thumbnails Not Displaying

- Existing thumbnails will continue to work via external URLs
- New uploads will automatically use Vercel Blob
- Old local uploads may need to be re-uploaded

## Cost Information

- **Free Tier**: 1GB storage, 1GB bandwidth per month
- **Pro Tier**: $0.15/GB storage, $0.40/GB bandwidth
- Perfect for small to medium projects

## Security

- Blob URLs are public by default (suitable for thumbnails)
- Tokens should be kept secure in environment variables
- No additional authentication needed for public images

## Alternative: External URLs

If you prefer not to use Vercel Blob, you can still:
- Use external image hosting (Imgur, Cloudinary, etc.)
- Provide direct URLs to thumbnails
- The application supports both approaches 
