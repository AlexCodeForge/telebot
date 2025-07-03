@extends('layout')

@section('title', 'Manage Videos')

@section('content')
    <div class="container-fluid">
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="d-flex justify-content-between align-items-center mb-6">
                            <h2 class="text-2xl font-bold mb-0">Telebot Admin Panel</h2>
                            <div class="btn-group" role="group">
                                <a href="{{ route('admin.videos.manage') }}"
                                    class="btn btn-outline-primary {{ request()->routeIs('admin.videos.*') ? 'active' : '' }}">
                                    <i class="fas fa-video me-1"></i>Videos
                                </a>
                                <a href="{{ route('admin.purchases.index') }}"
                                    class="btn btn-outline-success {{ request()->routeIs('admin.purchases.*') ? 'active' : '' }}">
                                    <i class="fas fa-shopping-cart me-1"></i>Purchases
                                </a>
                            </div>
                        </div>

                        <!-- Token Management Section -->
                        <div class="mb-6 border-b pb-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-blue-600">📋 API Configuration</h3>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#tokenModal">
                                    <i class="fas fa-cog"></i> Configure API Keys
                                </button>
                            </div>

                            <!-- Token Status Summary -->
                            <div class="row g-2">
                                <div class="col-md-3 col-sm-6">
                                    <div class="card h-100 {{ $telegramToken ? 'border-success' : 'border-danger' }}">
                                        <div class="card-body text-center py-3">
                                            <i class="fab fa-telegram-plane fa-lg {{ $telegramToken ? 'text-success' : 'text-danger' }} mb-1"></i>
                                            <h6 class="card-title mb-1 small">Telegram Bot</h6>
                                            <p class="card-text text-muted small mb-0">
                                                {{ $telegramToken ? 'Configured' : 'Not Configured' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="card h-100 {{ $stripeKey && $stripeSecret ? 'border-success' : 'border-danger' }}">
                                        <div class="card-body text-center py-3">
                                            <i class="fab fa-stripe fa-lg {{ $stripeKey && $stripeSecret ? 'text-success' : 'text-danger' }} mb-1"></i>
                                            <h6 class="card-title mb-1 small">Stripe Payments</h6>
                                            <p class="card-text text-muted small mb-0">
                                                {{ $stripeKey && $stripeSecret ? 'Configured' : 'Not Configured' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="card h-100 {{ $stripeWebhookSecret ? 'border-success' : 'border-warning' }}">
                                        <div class="card-body text-center py-3">
                                            <i class="fas fa-shield-alt fa-lg {{ $stripeWebhookSecret ? 'text-success' : 'text-warning' }} mb-1"></i>
                                            <h6 class="card-title mb-1 small">Webhook Security</h6>
                                            <p class="card-text text-muted small mb-0">
                                                {{ $stripeWebhookSecret ? 'Secured' : 'Basic Mode' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="card h-100 {{ $vercelBlobToken ? 'border-success' : 'border-danger' }}">
                                        <div class="card-body text-center py-3">
                                            <i class="fas fa-cloud-upload-alt fa-lg {{ $vercelBlobToken ? 'text-success' : 'text-danger' }} mb-1"></i>
                                            <h6 class="card-title mb-1 small">Vercel Blob Storage</h6>
                                            <p class="card-text text-muted small mb-0">
                                                {{ $vercelBlobToken ? 'Configured' : 'Not Configured' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        {{-- Success/Error Messages --}}
                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                {{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                {{ session('error') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        {{-- Sync User Configuration --}}
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-user-cog text-warning"></i> Sync User Configuration
                                </h5>
                            </div>
                            <div class="card-body">
                                @php
                                    $syncUserTelegramId = \App\Models\Setting::get('sync_user_telegram_id');
                                    $syncUserName = \App\Models\Setting::get('sync_user_name');
                                @endphp

                                @if ($syncUserTelegramId)
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle"></i> <strong>Sync User Configured:</strong><br>
                                        <strong>Name:</strong> {{ $syncUserName }}<br>
                                        <strong>Telegram ID:</strong> {{ $syncUserTelegramId }}<br>
                                        <small class="text-muted">Only videos from this user will be auto-captured. The bot
                                            will interact
                                            normally with all other users but ignore their videos.</small>
                                    </div>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="removeSyncUser()">
                                        <i class="fas fa-trash"></i> Remove Sync User
                                    </button>
                                @else
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i> <strong>No sync user
                                            configured.</strong><br>
                                        You need to set a sync user to control which Telegram user's videos can be imported.
                                    </div>
                                    <form onsubmit="setSyncUser(event)" class="row g-3">
                                        <div class="col-md-4">
                                            <label for="sync-telegram-id" class="form-label">Telegram User ID</label>
                                            <input type="text" class="form-control" id="sync-telegram-id"
                                                placeholder="e.g., 123456789" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="sync-name" class="form-label">Display Name</label>
                                            <input type="text" class="form-control" id="sync-name"
                                                placeholder="e.g., John Doe" required>
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-save"></i> Set Sync User
                                            </button>
                                        </div>
                </form>
                                @endif
            </div>
        </div>

                        {{-- Webhook Management --}}
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-wifi text-primary"></i> Webhook Management
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row align-items-center">
                <div class="col-md-8">
                                        <div class="d-flex align-items-center">
                                            <span><strong>Webhook Status:</strong></span>
                                            <span id="webhook-status" class="ms-2 badge bg-secondary">Checking...</span>
                    </div>
                                        <small class="text-muted">
                                            <strong>Active:</strong> Auto-capture videos when sent to bot<br>
                                            <strong>Disabled:</strong> Videos are not automatically captured (use manual
                                            import)
                                        </small>
                </div>
                <div class="col-md-4 text-end">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-outline-warning btn-sm"
                                                onclick="toggleWebhook('deactivate')" id="deactivate-webhook-btn">
                                                <i class="fas fa-stop"></i> Disable Webhook
                                            </button>
                                            <button type="button" class="btn btn-outline-success btn-sm"
                                                onclick="toggleWebhook('reactivate')" id="reactivate-webhook-btn">
                                                <i class="fas fa-play"></i> Enable Webhook
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Bot Testing --}}
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-stethoscope text-success"></i> Bot Testing & Diagnostics
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">
                                    Test the bot connection and view recent message history for debugging purposes.
                                </p>
                                <button type="button" class="btn btn-success" onclick="testTelegramConnection()">
                                    <i class="fas fa-search"></i> Test Bot Connection & View Messages
                                </button>

                                {{-- Test Results Display --}}
                                <div id="test-results" class="mt-4" style="display: none;">
                                    <h6>Bot Connection Test Results:</h6>
                                    <div id="test-content" class="border p-3 bg-light"
                                        style="max-height: 400px; overflow-y: auto;">
                                        <!-- Test results will be populated here -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- HIDDEN FOR NOW: Manual Video Import - Only show when webhook is disabled --}}
                        {{--
                        <div class="card mb-4" id="manual-import-section" style="display: none;">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-tools text-info"></i> Manual Video Import
                                </h5>
                            </div>
                            <div class="card-body">
                                @if (!$syncUserTelegramId)
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i> Please configure a sync user first
                                        before using manual
                                        import.
                                    </div>
                                @else
                                    <p class="text-muted">
                                        <i class="fas fa-info-circle"></i> Manual import is only available when webhook is
                                        disabled.
                                        Use "Test Bot Connection" above to find video file IDs, then import them here.
                                    </p>

                                    <div class="row">
                                        <div class="col-md-8">
                                            <label for="manual-file-id" class="form-label">Video File ID</label>
                                            <input type="text" id="manual-file-id" class="form-control"
                                                placeholder="Paste file ID from bot test results">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="manual-price" class="form-label">Price ($)</label>
                                            <input type="number" id="manual-price" class="form-control"
                                                placeholder="Price" value="4.99" step="0.01">
                </div>
            </div>

                                    <div class="row mt-3">
                                        <div class="col-md-8">
                                            <label for="manual-title" class="form-label">Video Title</label>
                                            <input type="text" id="manual-title" class="form-control"
                                                placeholder="Video title" value="Imported Video">
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end">
                                            <button type="button" class="btn btn-success w-100"
                                                onclick="quickImport('${fileId}', '${(msg.caption || 'Imported Video').replace(/'/g, "\\'")}')">
                                                <i class="fas fa-upload"></i> Import Video
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        --}}

            {{-- Videos Table --}}
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-video"></i> Videos ({{ count($videos) }})
                                </h5>
                            </div>
                            <div class="card-body">
                                @if (count($videos) > 0)
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                                                    <th>Title</th>
                                                    <th>Description</th>
                            <th>Price</th>
                                                    <th>Thumbnail</th>
                                                    <th>File ID</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                                                @foreach ($videos as $video)
                            <tr>
                                <td>
                                                            <strong>{{ $video->title }}</strong><br>
                                                            <small class="text-muted">Created:
                                                                {{ $video->created_at->format('M j, Y H:i') }}</small>
                                </td>
                                <td>
                                                            <div
                                                                style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                                                {{ $video->description ?? 'No description' }}
                                    </div>
                                </td>
                                <td>
                                    @if ($video->price > 0)
                                                                <span
                                                                    class="badge bg-success">${{ number_format($video->price, 2) }}</span>
                                                            @else
                                                                <span class="badge bg-warning">Free</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if ($video->hasThumbnail())
                                                                <div class="d-flex align-items-center">
                                                                    <img src="{{ $video->getThumbnailUrl() }}"
                                                                        alt="Thumbnail"
                                                                        style="width: 40px; height: 30px; object-fit: cover;"
                                                                        class="rounded me-2">
                                                                    @if ($video->shouldShowBlurred())
                                                                        <span class="badge bg-warning">Blurred</span>
                                                                    @else
                                                                        <span class="badge bg-success">Clear</span>
                                                                    @endif
                                                                </div>
                                    @else
                                                                <span class="text-muted">No thumbnail</span>
                                    @endif
                                </td>
                                                        <td>
                                                            <code
                                                                style="font-size: 10px;">{{ $video->telegram_file_id }}</code>
                                                        </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                                                <button type="button" class="btn btn-outline-primary"
                                                                    onclick="editVideo({{ $video->id }}, '{{ addslashes($video->title) }}', '{{ addslashes($video->description) }}', {{ $video->price }}, '{{ $video->getThumbnailUrl() }}', '{{ $video->thumbnail_url }}', {{ $video->show_blurred_thumbnail ? 'true' : 'false' }}, {{ $video->blur_intensity }}, {{ $video->allow_preview ? 'true' : 'false' }})">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                @if ($syncUserTelegramId)
                                                                    <button type="button" class="btn btn-outline-success"
                                                                        onclick="testVideo({{ $video->id }})">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                        @endif
                                                                <button type="button" class="btn btn-outline-danger"
                                                                    onclick="deleteVideo({{ $video->id }})">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                    </div>
                                </td>
                            </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Pagination -->
                                    @if ($videos->hasPages())
                                        <div class="d-flex justify-content-center mt-4">
                                            {{ $videos->links() }}
                                        </div>
                                    @endif
                                @else
                                    <div class="text-center py-5">
                                        <i class="fas fa-video fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No videos found</h5>
                                        <p class="text-muted">Configure sync user and enable webhook to auto-capture
                                            videos, or use manual
                                            import.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Token Management Modal --}}
    <div class="modal fade" id="tokenModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">API Configuration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="tokenForm">
                        <!-- Help Guide -->
                        <div class="alert alert-info mb-4">
                            <h6 class="fw-bold mb-2">🔑 Where to get your API keys:</h6>
                            <ul class="mb-0 small">
                                <li><strong>Telegram Bot Token:</strong> Create a bot with @BotFather on Telegram, it will
                                    give you a token like "123456789:ABCdefGHIjklMNOpqrsTUVwxyz"</li>
                                <li><strong>Stripe Keys:</strong> Get from <a href="https://dashboard.stripe.com/apikeys"
                                        target="_blank" class="text-decoration-none">Stripe Dashboard → API Keys</a> (use
                                    test keys for testing, live keys for production)</li>
                                <li><strong>Stripe Webhook Secret:</strong> Get from <a
                                        href="https://dashboard.stripe.com/webhooks" target="_blank"
                                        class="text-decoration-none">Stripe Dashboard → Webhooks</a> (optional but
                                    recommended for security)</li>
                                <li><strong>Vercel Blob Token:</strong> Get from <a href="https://vercel.com/dashboard"
                                        target="_blank" class="text-decoration-none">Vercel Dashboard → Storage → Blob</a>
                                    (required for thumbnail uploads on serverless deployments)</li>
                            </ul>
                        </div>

                        <!-- Telegram Bot Token -->
                        <div class="mb-3">
                            <label for="modal_telegram_token" class="form-label">
                                <i class="fab fa-telegram-plane text-primary"></i> Telegram Bot Token
                            </label>
                            <input type="password" class="form-control" id="modal_telegram_token"
                                placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz"
                                value="{{ $telegramToken ? str_repeat('*', 30) . substr($telegramToken, -10) : '' }}">
                            <div class="form-text">Get this from @BotFather on Telegram</div>
                        </div>

                        <!-- Stripe Publishable Key -->
                        <div class="mb-3">
                            <label for="modal_stripe_key" class="form-label">
                                <i class="fab fa-stripe text-success"></i> Stripe Publishable Key
                            </label>
                            <input type="text" class="form-control" id="modal_stripe_key"
                                placeholder="pk_test_... or pk_live_..."
                                value="{{ $stripeKey ? substr($stripeKey, 0, 15) . str_repeat('*', 20) : '' }}">
                            <div class="form-text">Starts with pk_test_ (testing) or pk_live_ (production)</div>
                        </div>

                        <!-- Stripe Secret Key -->
                        <div class="mb-3">
                            <label for="modal_stripe_secret" class="form-label">
                                <i class="fab fa-stripe text-success"></i> Stripe Secret Key
                            </label>
                            <input type="password" class="form-control" id="modal_stripe_secret"
                                placeholder="sk_test_... or sk_live_..."
                                value="{{ $stripeSecret ? substr($stripeSecret, 0, 10) . str_repeat('*', 30) : '' }}">
                            <div class="form-text">Starts with sk_test_ (testing) or sk_live_ (production)</div>
                        </div>

                        <!-- Stripe Webhook Secret -->
                        <div class="mb-3">
                            <label for="modal_stripe_webhook_secret" class="form-label">
                                <i class="fas fa-shield-alt text-warning"></i> Stripe Webhook Secret <span
                                    class="text-muted">(Optional)</span>
                            </label>
                            <input type="password" class="form-control" id="modal_stripe_webhook_secret"
                                placeholder="whsec_..."
                                value="{{ $stripeWebhookSecret ? str_repeat('*', 30) . substr($stripeWebhookSecret, -10) : '' }}">
                            <div class="form-text">For secure webhook processing (recommended for production)</div>
                        </div>

                        <!-- Vercel Blob Storage Token -->
                        <div class="mb-3">
                            <label for="modal_vercel_blob_token" class="form-label">
                                <i class="fas fa-cloud-upload-alt text-info"></i> Vercel Blob Storage Token
                            </label>
                            <input type="password" class="form-control" id="modal_vercel_blob_token"
                                placeholder="vercel_blob_rw_..."
                                value="{{ $vercelBlobToken ? str_repeat('*', 40) . substr($vercelBlobToken, -10) : '' }}">
                            <div class="form-text">Required for thumbnail uploads on Vercel (serverless deployment)</div>
            </div>
        </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveAllTokens()">
                        <i class="fas fa-save"></i> Save All Keys
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit Video Modal --}}
    <div class="modal fade" id="editVideoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Video</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editVideoForm" onsubmit="updateVideo(event)" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row">
                            <!-- Basic Video Details -->
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3"><i class="fas fa-info-circle text-primary"></i> Video Details
                                </h6>
                                <div class="mb-3">
                                    <label for="edit-title" class="form-label">Title</label>
                                    <input type="text" class="form-control" id="edit-title" name="title" required>
                                </div>
                                <div class="mb-3">
                                    <label for="edit-description" class="form-label">Description</label>
                                    <textarea class="form-control" id="edit-description" name="description" rows="3"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="edit-price" class="form-label">Price ($)</label>
                                    <input type="number" class="form-control" id="edit-price" name="price"
                                        step="0.01" min="0" required>
                                </div>
                            </div>

                            <!-- Thumbnail Management -->
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3"><i class="fas fa-image text-success"></i> Thumbnail Settings</h6>

                                <!-- Current Thumbnail Preview -->
                                <div id="current-thumbnail-preview" class="mb-3" style="display: none;">
                                    <label class="form-label">Current Thumbnail</label>
                                    <div class="border rounded p-2">
                                        <img id="current-thumbnail-img" src="" alt="Current thumbnail"
                                            class="img-fluid rounded" style="max-height: 120px;">
                                        <button type="button" class="btn btn-sm btn-outline-danger mt-2"
                                            onclick="removeThumbnail()">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </div>
                                </div>

                                <!-- Upload New Thumbnail -->
                                <div class="mb-3">
                                    <label for="edit-thumbnail" class="form-label">Upload Thumbnail</label>
                                    <input type="file" class="form-control" id="edit-thumbnail" name="thumbnail"
                                        accept="image/*" onchange="previewThumbnail(this)">
                                    <div class="form-text">Upload JPG, PNG, or GIF image (max 2MB)</div>
                                </div>

                                <!-- External Thumbnail URL -->
                                <div class="mb-3">
                                    <label for="edit-thumbnail-url" class="form-label">Or External URL</label>
                                    <input type="url" class="form-control" id="edit-thumbnail-url"
                                        name="thumbnail_url" placeholder="https://example.com/image.jpg">
                                </div>

                                <!-- Thumbnail Preview (New Upload) -->
                                <div id="new-thumbnail-preview" class="mb-3" style="display: none;">
                                    <label class="form-label">New Thumbnail Preview</label>
                                    <div class="border rounded p-2">
                                        <img id="new-thumbnail-img" src="" alt="New thumbnail"
                                            class="img-fluid rounded" style="max-height: 120px;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Blur Settings -->
                        <div class="row">
                            <div class="col-12">
                                <h6 class="fw-bold mb-3"><i class="fas fa-eye text-warning"></i> Customer Display Settings
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="edit-show-blurred"
                                            name="show_blurred" value="1" onchange="toggleCustomerDisplaySettings()">
                                        <label class="form-check-label" for="edit-show-blurred">
                                            Show Blurred Thumbnail to Customers
                                        </label>
                                    </div>
                                    <div class="form-text">When enabled, customers see a blurred version until purchase
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3" id="blur-intensity-container">
                                    <label for="edit-blur-intensity" class="form-label">Blur Intensity</label>
                                    <input type="range" class="form-range" id="edit-blur-intensity"
                                        name="blur_intensity" min="1" max="20" value="10">
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">Light</small>
                                        <small class="text-muted">Heavy</small>
                                    </div>
                                    <div class="form-text">Intensity: <span id="blur-intensity-display">10</span>px</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3" id="allow-preview-container">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="edit-allow-preview"
                                            name="allow_preview" value="1">
                                        <label class="form-check-label" for="edit-allow-preview">
                                            Allow Unblurred Preview
                                        </label>
                                    </div>
                                    <div class="form-text">Allow customers to see unblurred version on hover/click</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        let isWebhookActive = false;

        // Page load initialization
        document.addEventListener('DOMContentLoaded', function() {
            checkWebhookStatus();
        });

        // Check webhook status and update UI accordingly
        function checkWebhookStatus() {
            fetch('{{ route('admin.videos.webhook-status') }}')
                .then(response => response.json())
                .then(data => {
                    const statusBadge = document.getElementById('webhook-status');
                    if (data.success) {
                        isWebhookActive = data.webhook_info.url && data.webhook_info.url.length > 0;
                        statusBadge.textContent = isWebhookActive ? 'Active' : 'Disabled';
                        statusBadge.className = isWebhookActive ? 'ms-2 badge bg-success' : 'ms-2 badge bg-warning';

                        // Update manual import section visibility
                        updateManualImportVisibility();
                    } else {
                        statusBadge.textContent = 'Error';
                        statusBadge.className = 'ms-2 badge bg-danger';
                    }
                })
                .catch(error => {
                    const statusBadge = document.getElementById('webhook-status');
                    statusBadge.textContent = 'Error';
                    statusBadge.className = 'ms-2 badge bg-danger';
                    console.error('Failed to check webhook status:', error);
                });
        }

        // Update manual import section based on webhook status
        function updateManualImportVisibility() {
            const manualImportSection = document.getElementById('manual-import-section');

            if (manualImportSection) {
                if (isWebhookActive) {
                    manualImportSection.style.display = 'none';
                } else {
                    manualImportSection.style.display = 'block';
                }
            }
        }

        // Toggle webhook
        function toggleWebhook(action) {
            const url = action === 'reactivate' ?
                '{{ route('admin.videos.reactivate-webhook') }}' :
                '{{ route('admin.videos.deactivate-webhook') }}';

            fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);
                        setTimeout(checkWebhookStatus, 1000);
                    } else {
                        showAlert('danger', data.error || 'Operation failed');
                    }
                })
                .catch(error => {
                    showAlert('danger', 'Network error occurred');
                    console.error('Webhook toggle failed:', error);
                });
        }

        // Set sync user
        function setSyncUser(event) {
            event.preventDefault();
            const telegramId = document.getElementById('sync-telegram-id').value.trim();
            const name = document.getElementById('sync-name').value.trim();

            if (!telegramId || !name) {
                showAlert('warning', 'Please fill in both fields');
                return;
            }

            fetch('{{ route('admin.videos.set-sync-user') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        telegram_id: telegramId,
                        name: name
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', 'Sync user configured successfully!');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showAlert('danger', data.error || 'Failed to set sync user');
                    }
                })
                .catch(error => {
                    showAlert('danger', 'Network error occurred');
                    console.error('Set sync user failed:', error);
                });
        }

        // Remove sync user
        function removeSyncUser() {
            if (!confirm('Are you sure you want to remove the sync user configuration?')) {
                return;
            }

            fetch('{{ route('admin.videos.remove-sync-user') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', 'Sync user removed successfully!');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showAlert('danger', data.error || 'Failed to remove sync user');
                    }
                })
                .catch(error => {
                    showAlert('danger', 'Network error occurred');
                    console.error('Remove sync user failed:', error);
                });
        }

        // Test Telegram connection
        function testTelegramConnection() {
            const testResults = document.getElementById('test-results');
            const testContent = document.getElementById('test-content');

            testContent.innerHTML =
                '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Testing bot connection...</div>';
            testResults.style.display = 'block';

            fetch('{{ route('admin.videos.test-connection') }}')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '<div class="mb-3">';
                        html += '<h6>Bot Information:</h6>';
                        html += `<p><strong>Name:</strong> ${data.data.bot_info.first_name || 'Unknown'}<br>`;
                        html += `<strong>Username:</strong> @${data.data.bot_info.username || 'Unknown'}<br>`;
                        html += `<strong>ID:</strong> ${data.data.bot_info.id || 'Unknown'}</p>`;

                        html += '<h6>Webhook Status:</h6>';
                        html += `<p><strong>Active:</strong> ${data.data.webhook_active ? 'Yes' : 'No'}<br>`;
                        html +=
                            `<strong>Can use getUpdates:</strong> ${data.data.can_use_getupdates ? 'Yes' : 'No'}</p>`;

                        if (data.data.message_analysis && data.data.message_analysis.length > 0) {
                            html += '<h6>Recent Messages with Video File IDs:</h6>';
                            html += '<div class="table-responsive">';
                            html += '<table class="table table-sm table-bordered">';
                            html +=
                                '<thead><tr><th>From</th><th>Date</th><th>Video File ID</th><th>Caption</th><th>Action</th></tr></thead><tbody>';

                            data.data.message_analysis.forEach(msg => {
                                if (msg.has_video || msg.video_file_id || msg.document_file_id) {
                                    const fileId = msg.video_file_id || msg.document_file_id;
                                    html += '<tr class="table-success">';
                                    html +=
                                        `<td>${msg.from_first_name || 'Unknown'} (@${msg.from_username || 'no username'})<br><small>ID: ${msg.from_id}</small></td>`;
                                    html += `<td><small>${msg.date}</small></td>`;
                                    html += `<td><code style="font-size: 10px;">${fileId}</code></td>`;
                                    html += `<td>${msg.caption || msg.text || '-'}</td>`;
                                    if (!isWebhookActive) {
                                        html +=
                                            `<td><button class="btn btn-success btn-xs" onclick="quickImport('${fileId}', '${(msg.caption || 'Imported Video').replace(/'/g, "\\'")}')">Import</button></td>`;
                                    } else {
                                        html += '<td><span class="text-muted">Webhook active</span></td>';
                                    }
                                    html += '</tr>';
                                }
                            });

                            html += '</tbody></table></div>';
                            html +=
                                `<p><strong>Summary:</strong> Found ${data.data.video_messages_found} video messages out of ${data.data.total_messages_found} total messages.</p>`;
                        } else if (data.data.message) {
                            html += `<div class="alert alert-warning">${data.data.message}</div>`;
                        } else {
                            html +=
                                '<div class="alert alert-info">No recent video messages found in conversation history.</div>';
                        }

                        html += '</div>';
                        testContent.innerHTML = html;
                    } else {
                        testContent.innerHTML = `<div class="alert alert-danger">Error: ${data.error}</div>`;
                    }
                })
                .catch(error => {
                    testContent.innerHTML = '<div class="alert alert-danger">Network error occurred during test</div>';
                    console.error('Test connection failed:', error);
                });
        }

        // Quick import from test results
        function quickImport(fileId, title) {
            if (isWebhookActive) {
                showAlert('warning', 'Cannot import manually while webhook is active. Disable webhook first.');
                return;
            }

            document.getElementById('manual-file-id').value = fileId;
            document.getElementById('manual-title').value = title;
            // manualImportVideo(); // Commented out - manual import feature hidden
        }

        // HIDDEN FOR NOW: Manual import video function
        /*
        function manualImportVideo() {
            if (isWebhookActive) {
                showAlert('warning', 'Manual import is disabled while webhook is active.');
                return;
            }

            const fileId = document.getElementById('manual-file-id').value.trim();
            const title = document.getElementById('manual-title').value.trim();
            const price = document.getElementById('manual-price').value;

            if (!fileId) {
                showAlert('warning', 'Please enter a file ID');
                return;
            }

            fetch('{{ route('admin.videos.manual-import') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        file_id: fileId,
                        title: title || 'Imported Video',
                        price: parseFloat(price) || 4.99
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);
                        // Clear form
                        document.getElementById('manual-file-id').value = '';
                        document.getElementById('manual-title').value = 'Imported Video';
                        // Reload page to show new video
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showAlert('danger', data.error || 'Import failed');
                    }
                })
                .catch(error => {
                    showAlert('danger', 'Network error occurred');
                    console.error('Manual import failed:', error);
                });
        }
        */

        // Test video
        function testVideo(id) {
            fetch(`/admin/videos/${id}/test`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', 'Test video sent successfully!');
                    } else {
                        showAlert('danger', data.error || 'Failed to send test video');
                    }
                })
                .catch(error => {
                    showAlert('danger', 'Network error occurred');
                    console.error('Test video failed:', error);
                });
        }

        // Delete video
        function deleteVideo(id) {
            if (!confirm('Are you sure you want to delete this video?')) {
                return;
            }

            fetch(`/admin/videos/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', 'Video deleted successfully!');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showAlert('danger', data.error || 'Failed to delete video');
                    }
                })
                .catch(error => {
                    showAlert('danger', 'Network error occurred');
                    console.error('Delete video failed:', error);
                });
        }

        // Token Management Functions
        function saveAllTokens() {
            const telegramToken = document.getElementById('modal_telegram_token').value.trim();
            const stripeKey = document.getElementById('modal_stripe_key').value.trim();
            const stripeSecret = document.getElementById('modal_stripe_secret').value.trim();
            const stripeWebhookSecret = document.getElementById('modal_stripe_webhook_secret').value.trim();
            const vercelBlobToken = document.getElementById('modal_vercel_blob_token').value.trim();

            // Validate required fields
            if (!telegramToken && !stripeKey && !stripeSecret && !stripeWebhookSecret && !vercelBlobToken) {
                showAlert('warning', 'Please enter at least one token to save');
                return;
            }

            const tokens = {};
            if (telegramToken && !telegramToken.includes('*')) tokens.telegram_token = telegramToken;
            if (stripeKey && !stripeKey.includes('*')) tokens.stripe_key = stripeKey;
            if (stripeSecret && !stripeSecret.includes('*')) tokens.stripe_secret = stripeSecret;
            if (stripeWebhookSecret && !stripeWebhookSecret.includes('*')) tokens.stripe_webhook_secret =
                stripeWebhookSecret;
            if (vercelBlobToken && !vercelBlobToken.includes('*')) tokens.vercel_blob_token = vercelBlobToken;

            fetch('{{ route('admin.tokens.save-all') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(tokens)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', 'API tokens saved successfully!');
                        setTimeout(() => {
                            bootstrap.Modal.getInstance(document.getElementById('tokenModal')).hide();
                            window.location.reload();
                        }, 1500);
                    } else {
                        showAlert('danger', data.error || 'Failed to save tokens');
                    }
                })
                .catch(error => {
                    showAlert('danger', 'Network error occurred');
                    console.error('Save tokens failed:', error);
                });
        }

        // Edit Video Functions
        function editVideo(id, title, description, price, thumbnailPath, thumbnailUrl, showBlurred, blurIntensity,
            allowPreview) {
            // Set basic video fields
            document.getElementById('edit-title').value = title;
            document.getElementById('edit-description').value = description;
            document.getElementById('edit-price').value = price;

            // Set thumbnail fields
            document.getElementById('edit-thumbnail-url').value = thumbnailUrl || '';

            // Set blur settings - convert string 'true'/'false' to boolean
            const isBlurredEnabled = showBlurred === true || showBlurred === 'true';
            const isPreviewEnabled = allowPreview === true || allowPreview === 'true';

            document.getElementById('edit-show-blurred').checked = isBlurredEnabled;
            document.getElementById('edit-blur-intensity').value = blurIntensity || 10;
            document.getElementById('blur-intensity-display').textContent = blurIntensity || 10;
            document.getElementById('edit-allow-preview').checked = isPreviewEnabled;

            // Toggle display settings based on blur checkbox
            toggleCustomerDisplaySettings();

            // Show current thumbnail if exists
            const currentThumbnailPreview = document.getElementById('current-thumbnail-preview');
            const currentThumbnailImg = document.getElementById('current-thumbnail-img');

            if (thumbnailPath) {
                currentThumbnailImg.src = thumbnailPath;
                currentThumbnailPreview.style.display = 'block';
            } else {
                currentThumbnailPreview.style.display = 'none';
            }

            // Reset upload preview
            document.getElementById('new-thumbnail-preview').style.display = 'none';
            document.getElementById('edit-thumbnail').value = '';

            // Store the video ID for submission
            document.getElementById('editVideoForm').setAttribute('data-video-id', id);

            const modal = new bootstrap.Modal(document.getElementById('editVideoModal'));
            modal.show();
        }

        function updateVideo(event) {
            event.preventDefault();

            const form = event.target;
            const videoId = form.getAttribute('data-video-id');

            // Show loading state
            const submitButton = form.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitButton.disabled = true;

            async function performUpdate() {
                try {
                    // Collect form data manually (NO FormData to avoid serverless issues)
                    const formData = {
                        title: form.querySelector('[name="title"]').value,
                        description: form.querySelector('[name="description"]').value,
                        price: form.querySelector('[name="price"]').value,
                        thumbnail_url: form.querySelector('[name="thumbnail_url"]').value || '',
                        thumbnail_blob_url: form.querySelector('[name="thumbnail_blob_url"]').value || '',
                        blur_intensity: form.querySelector('[name="blur_intensity"]').value || 10,
                        show_blurred: form.querySelector('[name="show_blurred"]').checked ? 1 : 0,
                        allow_preview: form.querySelector('[name="allow_preview"]').checked ? 1 : 0,
                        _method: 'PUT',
                        _token: '{{ csrf_token() }}'
                    };

                    const thumbnailFile = form.querySelector('[name="thumbnail"]').files[0];

                    // Handle thumbnail upload to Vercel Blob if a file is selected
                    if (thumbnailFile && thumbnailFile.size > 0) {
                        console.log('Uploading thumbnail to Vercel Blob...');

                        // Upload directly to Vercel Blob
                        const uploadResponse = await fetch('/admin/videos/direct-upload', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'X-Filename': thumbnailFile.name,
                                'X-Content-Type': thumbnailFile.type || 'image/jpeg'
                            },
                            body: thumbnailFile
                        });

                        const uploadResult = await uploadResponse.json();

                        if (!uploadResult.success) {
                            throw new Error('Thumbnail upload failed: ' + uploadResult.error);
                        }

                        console.log('Thumbnail uploaded successfully:', uploadResult.blob_url);

                        // Add the blob URL to our data
                        formData.thumbnail_blob_url = uploadResult.blob_url;
                    }

                    // Debug: Log what we're sending
                    console.log('Form data being sent:', formData);

                    // Submit as JSON instead of FormData to avoid serverless middleware issues
                    const response = await fetch(`/admin/videos/${videoId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(formData)
                    });

                    // Check if response is JSON
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        const data = await response.json();
                        console.log('Response received:', data);

                        if (data.success) {
                            showAlert('success', 'Video updated successfully!');
                            setTimeout(() => {
                                bootstrap.Modal.getInstance(document.getElementById('editVideoModal')).hide();
                                window.location.reload();
                            }, 1500);
                        } else {
                            showAlert('danger', data.message || data.error || 'Failed to update video');
                        }
                    } else {
                        // If not JSON, it might be an error page
                        const text = await response.text();
                        console.error('Non-JSON response:', text);
                        throw new Error('Server returned non-JSON response');
                    }
                } catch (error) {
                    showAlert('danger', 'Update failed: ' + error.message);
                    console.error('Update video failed:', error);
                } finally {
                    // Restore button state
                    submitButton.innerHTML = originalText;
                    submitButton.disabled = false;
                }
            }

            performUpdate();
        }

        // Thumbnail management functions
        function previewThumbnail(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('new-thumbnail-img').src = e.target.result;
                    document.getElementById('new-thumbnail-preview').style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removeThumbnail() {
            document.getElementById('current-thumbnail-preview').style.display = 'none';
            // You could add an AJAX call here to actually remove the thumbnail from the server
        }

        // Blur intensity slider update
        document.addEventListener('DOMContentLoaded', function() {
            const blurSlider = document.getElementById('edit-blur-intensity');
            const blurDisplay = document.getElementById('blur-intensity-display');

            if (blurSlider && blurDisplay) {
                blurSlider.addEventListener('input', function() {
                    blurDisplay.textContent = this.value;
                });
            }
        });

        // Show alert
        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

            document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector('.container-fluid')
                .firstChild);

            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        // Toggle customer display settings
        function toggleCustomerDisplaySettings() {
            const showBlurred = document.getElementById('edit-show-blurred').checked;
            const blurIntensityContainer = document.getElementById('blur-intensity-container');
            const allowPreviewContainer = document.getElementById('allow-preview-container');
            const blurIntensitySlider = document.getElementById('edit-blur-intensity');
            const allowPreviewCheckbox = document.getElementById('edit-allow-preview');

            if (showBlurred) {
                // Enable blur settings
                blurIntensityContainer.style.display = 'block';
                allowPreviewContainer.style.display = 'block';
                blurIntensityContainer.style.opacity = '1';
                allowPreviewContainer.style.opacity = '1';
                blurIntensitySlider.disabled = false;
                allowPreviewCheckbox.disabled = false;
            } else {
                // Disable but show blur settings with reduced opacity
                blurIntensityContainer.style.display = 'block';
                allowPreviewContainer.style.display = 'block';
                blurIntensityContainer.style.opacity = '0.5';
                allowPreviewContainer.style.opacity = '0.5';
                blurIntensitySlider.disabled = true;
                allowPreviewCheckbox.disabled = true;
                // Also uncheck the allow preview when blur is disabled
                allowPreviewCheckbox.checked = false;
            }
        }
    </script>
@endsection
