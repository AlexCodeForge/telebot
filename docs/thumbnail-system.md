# Thumbnail & Blur System Documentation

## Overview

This system allows admins to upload thumbnail images for videos and control how they're displayed to customers using blur effects and preview functionality.

## Features

### 1. **Thumbnail Management**

-   **Upload Images**: Admins can upload JPG, PNG, or GIF images (max 2MB)
-   **External URLs**: Support for external image URLs
-   **Storage**: Images stored in `storage/app/public/thumbnails/`
-   **Auto-cleanup**: Thumbnails automatically deleted when videos are removed

### 2. **Blur System**

-   **Blurred Thumbnails**: Show blurred versions to customers until purchase
-   **Adjustable Intensity**: Blur intensity from 1-20px (configurable per video)
-   **Toggle Control**: Admin can enable/disable blur per video

### 3. **Preview System**

-   **Hover Preview**: On video listings, thumbnails unblur on mouse hover
-   **Click Preview**: On video detail pages, click to toggle preview
-   **Auto-hide**: Previews automatically return to blurred after 3 seconds
-   **Admin Control**: Allow/disallow preview per video

## Database Changes

### New Fields Added to `videos` table:

```sql
- thumbnail_path (string, nullable) - Local uploaded thumbnail filename
- thumbnail_url (string, nullable) - External URL (if any)
- show_blurred_thumbnail (boolean, default: true) - Show blurred version to customers
- blur_intensity (integer, default: 10) - Blur intensity (1-20px)
- allow_preview (boolean, default: false) - Allow customers to see unblurred preview
```

## Admin Panel Features

### Enhanced Edit Video Modal:

-   **Two-column layout** with video details and thumbnail settings
-   **Current thumbnail preview** with remove option
-   **Upload new thumbnail** with live preview
-   **External URL option** for remote images
-   **Blur controls** with intensity slider
-   **Preview toggle** to allow/disallow customer previews

### Videos Table:

-   **Thumbnail column** showing mini preview and blur status
-   **Status badges** indicating if thumbnails are "Blurred" or "Clear"

## Customer Experience

### Video Listings (`/videos`):

-   **Blurred thumbnails** with overlay message
-   **Hover preview** (if enabled) temporarily shows unblurred version
-   **Lock icon overlay** indicating premium content

### Video Detail Pages (`/videos/{id}`):

-   **Large blurred thumbnail** with descriptive overlay
-   **Click to preview** (if enabled) toggles blur on/off
-   **Auto-hide** returns to blurred state after 3 seconds

## Model Methods Added

### `Video` Model:

```php
// Get thumbnail URL (uploaded or external)
getThumbnailUrl(): ?string

// Get blur CSS style
getBlurredThumbnailStyle(): string

// Check if video has thumbnail
hasThumbnail(): bool

// Check if should show blurred
shouldShowBlurred(): bool
```

## File Upload Handling

### Security:

-   **File validation**: Only image files allowed (jpeg, png, jpg, gif)
-   **Size limit**: 2MB maximum
-   **Unique naming**: Timestamped filenames prevent conflicts

### Storage:

-   **Location**: `storage/app/public/thumbnails/`
-   **Public access**: Via Laravel's storage link (`/storage/thumbnails/`)
-   **Cleanup**: Old thumbnails deleted when new ones uploaded

## Usage Examples

### Admin Workflow:

1. **Edit video** in admin panel
2. **Upload thumbnail** or enter external URL
3. **Configure blur settings** (intensity, preview options)
4. **Save changes** - thumbnail immediately available

### Customer Experience:

1. **Browse videos** - see blurred thumbnails with "locked" overlay
2. **Hover preview** (if enabled) - briefly see unblurred version
3. **Purchase video** - gain full access to unblurred content
4. **Click preview** on detail pages - toggle blur on/off

## Technical Implementation

### Frontend:

-   **Bootstrap 5** styling for responsive design
-   **JavaScript** for interactive preview functionality
-   **CSS filters** for blur effects
-   **File upload** with live preview

### Backend:

-   **Laravel Storage** for file management
-   **Form validation** for uploads and settings
-   **Model methods** for clean data access
-   **Migration** for database schema updates

## Configuration Options

### Per Video Settings:

-   **Show Blurred**: Enable/disable blur effect
-   **Blur Intensity**: 1-20px blur strength
-   **Allow Preview**: Enable/disable hover/click preview
-   **Thumbnail Source**: Upload file or external URL

### Global Defaults:

-   **Default blur**: 10px intensity
-   **Default behavior**: Blur enabled, preview disabled
-   **File limits**: 2MB max size, image files only

This system provides a professional, secure way to showcase video content while protecting premium material until purchase.
