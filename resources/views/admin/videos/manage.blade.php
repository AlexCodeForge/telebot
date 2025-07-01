@extends('layout')

@section('title', 'Manage Videos')

@section('content')
    <div class="container-fluid">
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

        {{-- Sync User & Webhook Management --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-sync text-primary"></i> Sync & Webhook Management
                </h5>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        @if ($syncUserTelegramId)
                            <div class="d-flex align-items-center">
                                <i class="fas fa-user-check text-success me-2"></i>
                                <span><strong>Sync User:</strong> {{ $syncUserName }} (ID: {{ $syncUserTelegramId }})</span>
                            </div>
                        @else
                            <div class="d-flex align-items-center">
                                <i class="fas fa-user-times text-warning me-2"></i>
                                <span class="text-muted">No sync user configured</span>
                            </div>
                        @endif
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal"
                                data-bs-target="#syncUserModal">
                                <i class="fas fa-cog"></i> Configure Sync User
                            </button>

                            @if ($syncUserTelegramId)
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="syncVideos()"
                                    id="sync-btn">
                                    <i class="fas fa-sync"></i> Sync Videos
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                <hr>

                {{-- Webhook Status --}}
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-wifi text-info me-2"></i>
                            <span><strong>Webhook Status:</strong></span>
                            <span id="webhook-status" class="ms-2 badge bg-secondary">Checking...</span>
                        </div>
                        <small class="text-muted">
                            Webhooks must be deactivated to use sync functionality (getUpdates)
                        </small>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-warning btn-sm"
                                onclick="toggleWebhook('deactivate')" id="deactivate-webhook-btn">
                                <i class="fas fa-stop"></i> Deactivate Webhook
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm"
                                onclick="toggleWebhook('reactivate')" id="reactivate-webhook-btn">
                                <i class="fas fa-play"></i> Reactivate Webhook
                            </button>
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="testTelegramConnection()"
                                id="test-telegram-btn">
                                <i class="fas fa-stethoscope"></i> Test Connection
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Sync Help Info --}}
                <div class="alert alert-warning mt-3 mb-0">
                    <h6><i class="fas fa-exclamation-triangle"></i> IMPORTANT: How Video Sync Works:</h6>
                    <ul class="mb-2 small">
                        <li><strong>Step 1:</strong> Make sure the Telegram user ID is correct in sync settings</li>
                        <li><strong>Step 2:</strong> Deactivate webhook if it's active (webhooks block getUpdates)</li>
                        <li><strong>Step 3:</strong> Send NEW videos to your bot (old videos are already consumed)</li>
                        <li><strong>Step 4:</strong> Click "Sync Videos" to import recent videos</li>
                    </ul>
                    <div class="small">
                        <strong>⚠️ Critical:</strong> getUpdates only returns <em>unprocessed</em> messages. If your webhook
                        was active when videos were sent, they're already consumed and won't appear in sync.
                        <br><strong>Solution:</strong> Send NEW videos AFTER deactivating webhook, or try the "Reset
                        Updates" button below.
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="resetUpdatesOffset()">
                            <i class="fas fa-redo"></i> Reset Updates (Try to recover old messages)
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stats --}}
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5>Total Videos</h5>
                        <h2>{{ $stats['total'] }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5>Ready for Sale</h5>
                        <h2>{{ $stats['ready'] }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5>Need Pricing</h5>
                        <h2>{{ $stats['pending'] }}</h2>
                    </div>
                </div>
            </div>
        </div>

        {{-- Conversation History Import Section --}}
        @if ($syncUserTelegramId)
            <div class="card mb-4 border-info">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-comments"></i> Import from Conversation History</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-success">
                        <strong>Quick Import:</strong> Import ALL videos from {{ $syncUserName ?: 'the sync user' }} found
                        in recent messages with default price $4.99.
                    </div>

                    <button type="button" class="btn btn-success" onclick="importAllVideos()">
                        <i class="fas fa-download"></i> Import All Videos
                    </button>
                </div>
            </div>
        @endif

        {{-- Search & Filters --}}
        <div class="row mb-3">
            <div class="col-md-6">
                <form method="GET" class="d-flex">
                    <input type="text" name="search" class="form-control" placeholder="Search videos..."
                        value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary ms-2">Search</button>
                </form>
            </div>
            <div class="col-md-6 text-end">
                <a href="?status=pending" class="btn btn-warning btn-sm">Need Pricing ({{ $stats['pending'] }})</a>
                <a href="?status=ready" class="btn btn-success btn-sm">Ready ({{ $stats['ready'] }})</a>
                <a href="?" class="btn btn-outline-secondary btn-sm">All</a>
            </div>
        </div>

        {{-- Videos Table --}}
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Video Details</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($videos as $video)
                        <tr>
                            <td>
                                <div>
                                    <div class="d-flex align-items-start">
                                        @if ($video->thumbnail_url)
                                            <img src="{{ $video->thumbnail_url }}" alt="Video thumbnail"
                                                class="me-3 rounded"
                                                style="width: 60px; height: 45px; object-fit: cover;">
                                        @else
                                            <div class="me-3 d-flex align-items-center justify-content-center bg-light rounded"
                                                style="width: 60px; height: 45px; min-width: 60px;">
                                                <i class="fas fa-video text-muted"></i>
                                            </div>
                                        @endif
                                        <div class="flex-grow-1">
                                            <strong>{{ $video->title ?: 'Video #' . $video->id }}</strong>
                                            @if ($video->description)
                                                <br><small
                                                    class="text-muted">{{ Str::limit($video->description, 80) }}</small>
                                            @endif
                                            <br><small class="text-muted">
                                                Added {{ $video->created_at->diffForHumans() }}
                                                @if ($video->duration)
                                                    • Duration: {{ gmdate('i:s', $video->duration) }}
                                                @endif
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="h5 {{ $video->price > 0 ? 'text-success' : 'text-warning' }}">
                                    ${{ number_format($video->price, 2) }}
                                </span>
                            </td>
                            <td>
                                @if ($video->price > 0)
                                    <span class="badge bg-success">Ready</span>
                                @else
                                    <span class="badge bg-warning">Needs Price</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal"
                                        data-bs-target="#editVideoModal" data-video-id="{{ $video->id }}"
                                        data-video-title="{{ $video->title }}"
                                        data-video-description="{{ $video->description }}"
                                        data-video-price="{{ $video->price }}" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    @if ($video->telegram_file_id)
                                        <form method="POST" action="{{ route('admin.videos.test', $video) }}"
                                            class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-info btn-sm" title="Test Send">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </form>
                                    @endif

                                    @if ($syncUserTelegramId && $video->telegram_file_id)
                                        <form method="POST"
                                            action="{{ route('admin.videos.send-to-sync-user', $video) }}"
                                            class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success btn-sm"
                                                title="Send to {{ $syncUserName }}"
                                                onclick="return confirm('Send this video to {{ $syncUserName }}?')">
                                                <i class="fas fa-share"></i>
                                            </button>
                                        </form>
                                    @endif

                                    @if ($video->telegram_file_id && !$video->thumbnail_url)
                                        <button type="button" class="btn btn-outline-warning btn-sm"
                                            onclick="generateThumbnail({{ $video->id }})" title="Generate Thumbnail"
                                            id="thumbnail-btn-{{ $video->id }}">
                                            <i class="fas fa-image"></i>
                                        </button>
                                    @endif

                                    <form method="POST" action="{{ route('admin.videos.destroy', $video) }}"
                                        class="d-inline" onsubmit="return confirm('Delete this video?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="fas fa-video fa-3x mb-3"></i>
                                    <h5>No videos found</h5>
                                    <p>Videos sent to your Telegram bot will appear here automatically.</p>
                                    @if ($syncUserTelegramId)
                                        <p>Or use the sync button above to import videos from {{ $syncUserName }}.</p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($videos->hasPages())
            <div class="d-flex justify-content-center">
                {{ $videos->links() }}
            </div>
        @endif
    </div>

    {{-- Edit Video Modal --}}
    <div class="modal fade" id="editVideoModal" tabindex="-1" aria-labelledby="editVideoModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="editVideoForm">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title" id="editVideoModalLabel">Edit Video</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="edit_title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_price" class="form-label">Price ($)</label>
                            <input type="number" class="form-control" id="edit_price" name="price" step="0.01"
                                min="0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quick Price Options:</label>
                            <div class="btn-group w-100" role="group">
                                <button type="button" class="btn btn-outline-secondary"
                                    onclick="setModalPrice(4.99)">$4.99</button>
                                <button type="button" class="btn btn-outline-secondary"
                                    onclick="setModalPrice(9.99)">$9.99</button>
                                <button type="button" class="btn btn-outline-secondary"
                                    onclick="setModalPrice(19.99)">$19.99</button>
                                <button type="button" class="btn btn-outline-secondary"
                                    onclick="setModalPrice(0)">Free</button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Sync User Configuration Modal --}}
    <div class="modal fade" id="syncUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Configure Sync User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('admin.videos.set-sync-user') }}">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="telegram_user_id" class="form-label">Telegram User ID <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="telegram_user_id" name="telegram_user_id"
                                value="{{ $syncUserTelegramId }}" placeholder="e.g., 123456789" required>
                            <div class="form-text">
                                The Telegram user ID of the person who sends videos to the bot.
                                You can get this from @userinfobot or from message logs.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="sync_user_name" class="form-label">Display Name (Optional)</label>
                            <input type="text" class="form-control" id="sync_user_name" name="name"
                                value="{{ $syncUserName }}" placeholder="e.g., John Doe">
                            <div class="form-text">
                                A friendly name to identify this user in the admin panel.
                            </div>
                        </div>

                        @if ($syncUserTelegramId)
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                Current sync user: <strong>{{ $syncUserName }}</strong> (ID: {{ $syncUserTelegramId }})
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        @if ($syncUserTelegramId)
                            <button type="button" class="btn btn-outline-danger" onclick="removeSyncUser()">
                                <i class="fas fa-trash"></i> Remove Sync User
                            </button>
                        @endif
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Conversation History Modal --}}
    <div class="modal fade" id="conversationModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Conversation History with {{ $syncUserName ?: 'Sync User' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="conversation-loading" class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Loading conversation history...</p>
                    </div>
                    <div id="conversation-content" style="display: none;">
                        <!-- Content will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Import Video Modal --}}
    <div class="modal fade" id="importVideoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Video</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="importVideoForm">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="import_title" class="form-label">Video Title <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="import_title" name="title" required>
                        </div>

                        <div class="mb-3">
                            <label for="import_price" class="form-label">Price ($) <span
                                    class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="import_price" name="price" step="0.01"
                                min="0" value="4.99" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Quick Price Options:</label>
                            <div class="btn-group w-100" role="group">
                                <button type="button" class="btn btn-outline-secondary"
                                    onclick="setImportPrice(4.99)">$4.99</button>
                                <button type="button" class="btn btn-outline-secondary"
                                    onclick="setImportPrice(9.99)">$9.99</button>
                                <button type="button" class="btn btn-outline-secondary"
                                    onclick="setImportPrice(19.99)">$19.99</button>
                                <button type="button" class="btn btn-outline-secondary"
                                    onclick="setImportPrice(0)">Free</button>
                            </div>
                        </div>

                        <!-- Hidden fields for video data -->
                        <input type="hidden" id="import_file_id" name="file_id">
                        <input type="hidden" id="import_file_unique_id" name="file_unique_id">
                        <input type="hidden" id="import_duration" name="duration">
                        <input type="hidden" id="import_width" name="width">
                        <input type="hidden" id="import_height" name="height">
                        <input type="hidden" id="import_file_size" name="file_size">
                        <input type="hidden" id="import_caption" name="caption">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus"></i> Import Video
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>



    <script>
        // Handle edit modal
        const editVideoModal = document.getElementById('editVideoModal');
        const editVideoForm = document.getElementById('editVideoForm');
        let currentVideoId = null;

        editVideoModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            currentVideoId = button.getAttribute('data-video-id');

            document.getElementById('edit_title').value = button.getAttribute('data-video-title') || '';
            document.getElementById('edit_description').value = button.getAttribute('data-video-description') || '';
            document.getElementById('edit_price').value = button.getAttribute('data-video-price') || '0';
        });

        editVideoForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');

            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';

            fetch(`/admin/videos/${currentVideoId}`, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close modal
                        bootstrap.Modal.getInstance(editVideoModal).hide();

                        // Show success message
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-success alert-dismissible fade show';
                        alertDiv.innerHTML = `
                        ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                        document.querySelector('.container-fluid').insertBefore(alertDiv, document
                            .querySelector('.container-fluid').firstChild);

                        // Reload page to show updated data
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        alert('Error updating video');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating video');
                })
                .finally(() => {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Save Changes';
                });
        });

        function setModalPrice(price) {
            document.getElementById('edit_price').value = price;
        }

        // Generate thumbnail function
        function generateThumbnail(videoId) {
            const button = document.getElementById(`thumbnail-btn-${videoId}`);
            const originalContent = button.innerHTML;

            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch(`/admin/videos/${videoId}/thumbnail`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-success alert-dismissible fade show';
                        alertDiv.innerHTML = `
                        Thumbnail generated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                        document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector(
                            '.container-fluid').firstChild);

                        // Reload page to show thumbnail
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        alert('Error generating thumbnail: ' + (data.error || 'Unknown error'));
                        button.disabled = false;
                        button.innerHTML = originalContent;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error generating thumbnail');
                    button.disabled = false;
                    button.innerHTML = originalContent;
                });
        }

        // Sync videos function
        function syncVideos() {
            const button = document.getElementById('sync-btn');
            const originalContent = button.innerHTML;

            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';

            fetch('/admin/videos/sync', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                })
                .then(response => {
                    if (response.ok) {
                        window.location.reload();
                    } else {
                        throw new Error('Sync failed');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error syncing videos');
                    button.disabled = false;
                    button.innerHTML = originalContent;
                });
        }

        // Remove sync user function
        function removeSyncUser() {
            if (!confirm('Are you sure you want to remove the sync user configuration?')) {
                return;
            }

            fetch('/admin/videos/sync-user', {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                })
                .then(response => {
                    if (response.ok) {
                        window.location.reload();
                    } else {
                        throw new Error('Failed to remove sync user');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error removing sync user');
                });
        }

        // Toggle webhook function
        function toggleWebhook(action) {
            const endpoint = action === 'deactivate' ? '/admin/videos/webhook/deactivate' :
                '/admin/videos/webhook/reactivate';
            const button = document.getElementById(`${action}-webhook-btn`);
            const originalContent = button.innerHTML;

            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                })
                .then(response => {
                    if (response.ok) {
                        setTimeout(checkWebhookStatus, 1000); // Check status after action
                        window.location.reload();
                    } else {
                        throw new Error(`Failed to ${action} webhook`);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert(`Error ${action}ing webhook`);
                    button.disabled = false;
                    button.innerHTML = originalContent;
                });
        }

        // Check webhook status function
        function checkWebhookStatus() {
            fetch('/admin/videos/webhook/status', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    const statusElement = document.getElementById('webhook-status');
                    if (data.success && data.webhook_info) {
                        const webhookInfo = data.webhook_info;
                        if (webhookInfo.url && webhookInfo.url.length > 0) {
                            statusElement.className = 'ms-2 badge bg-success';
                            statusElement.textContent = 'Active';
                        } else {
                            statusElement.className = 'ms-2 badge bg-warning';
                            statusElement.textContent = 'Inactive';
                        }
                    } else {
                        statusElement.className = 'ms-2 badge bg-danger';
                        statusElement.textContent = 'Error';
                    }
                })
                .catch(error => {
                    console.error('Error checking webhook status:', error);
                    const statusElement = document.getElementById('webhook-status');
                    statusElement.className = 'ms-2 badge bg-danger';
                    statusElement.textContent = 'Error';
                });
        }

        // Test Telegram connection function
        function testTelegramConnection() {
            const button = document.getElementById('test-telegram-btn');
            const originalContent = button.innerHTML;

            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';

            fetch('/admin/videos/test-telegram', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Create a detailed modal with test results
                        const results = data.data;
                        const modal = document.createElement('div');
                        modal.className = 'modal fade';
                        modal.id = 'telegramTestModal';
                        modal.innerHTML = `
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Telegram Connection Test Results</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <h6>Bot Information:</h6>
                                            <pre class="bg-light p-2 small">${JSON.stringify(results.bot_info, null, 2)}</pre>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Webhook Status:</h6>
                                            <pre class="bg-light p-2 small">${JSON.stringify(results.webhook_info, null, 2)}</pre>
                                            <div class="mt-2">
                                                <span class="badge ${results.webhook_active ? 'bg-success' : 'bg-warning'}">
                                                    ${results.webhook_active ? 'Webhook Active' : 'Webhook Inactive'}
                                                </span>
                                                <span class="badge ${results.can_use_getupdates ? 'bg-success' : 'bg-danger'}">
                                                    ${results.can_use_getupdates ? 'Can Use getUpdates' : 'Cannot Use getUpdates'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <h6>Message Analysis Summary:</h6>
                                        <div class="alert alert-info">
                                            <strong>Total Messages Found:</strong> ${results.total_messages_found || 0}<br>
                                            <strong>Video Messages Found:</strong> ${results.video_messages_found || 0}
                                        </div>
                                    </div>

                                    ${results.message_analysis && results.message_analysis.length > 0 ? `
                                                                                                        <div class="mb-3">
                                                                                                            <h6>Detailed Message Analysis:</h6>
                                                                                                            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                                                                                                <table class="table table-sm table-bordered">
                                                                                                                    <thead>
                                                                                                                        <tr>
                                                                                                                            <th>Update ID</th>
                                                                                                                            <th>Chat ID</th>
                                                                                                                            <th>From ID</th>
                                                                                                                            <th>From Name</th>
                                                                                                                            <th>Has Video</th>
                                                                                                                            <th>Date</th>
                                                                                                                            <th>Content</th>
                                                                                                                        </tr>
                                                                                                                    </thead>
                                                                                                                    <tbody>
                                                                                                                        ${results.message_analysis.map(msg => `
                                                        <tr class="${msg.has_video ? 'table-success' : ''}">
                                                            <td>${msg.update_id}</td>
                                                            <td>${msg.chat_id}</td>
                                                            <td>${msg.from_id}</td>
                                                            <td>${msg.from_first_name} (@${msg.from_username})</td>
                                                            <td>${msg.has_video ? '✅ VIDEO' : msg.has_photo ? '📷 Photo' : msg.has_document ? '📄 Document' : '💬 Text'}</td>
                                                            <td>${msg.date}</td>
                                                            <td>${msg.text || msg.caption || (msg.has_video ? `Video (${msg.video_duration}s)` : 'N/A')}</td>
                                                        </tr>
                                                    `).join('')}
                                                                                                                    </tbody>
                                                                                                                </table>
                                                                                                            </div>
                                                                                                        </div>
                                                                                                        ` : ''}

                                    <div class="mt-3">
                                        <h6>Raw API Response:</h6>
                                        <pre class="bg-light p-2 small" style="max-height: 200px; overflow-y: auto;">${JSON.stringify(results.recent_updates, null, 2)}</pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                        document.body.appendChild(modal);
                        const bootstrapModal = new bootstrap.Modal(modal);
                        bootstrapModal.show();

                        // Remove modal when closed
                        modal.addEventListener('hidden.bs.modal', () => {
                            document.body.removeChild(modal);
                        });
                    } else {
                        alert('Telegram connection test failed: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error testing Telegram connection');
                })
                .finally(() => {
                    button.disabled = false;
                    button.innerHTML = originalContent;
                });
        }

        // Reset updates offset function
        function resetUpdatesOffset() {
            if (!confirm(
                    'This will attempt to reset the getUpdates offset to try to recover older messages. This might not always work due to Telegram API limitations. Continue?'
                )) {
                return;
            }

            const button = event.target;
            const originalContent = button.innerHTML;

            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting...';

            fetch('/admin/videos/reset-updates', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(
                            `Reset attempt completed. Found ${data.reset_updates_count || 0} updates. You can now try syncing again.`
                        );
                    } else {
                        alert('Reset failed: ' + (data.message || data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error resetting updates offset');
                })
                .finally(() => {
                    button.disabled = false;
                    button.innerHTML = originalContent;
                });
        }

        // Set add price function for manual import
        function setAddPrice(price) {
            document.getElementById('add_price').value = price;
        }

        // Search for video function
        function searchForVideo() {
            const searchText = document.getElementById('search_text').value.trim();
            if (!searchText) {
                alert('Please enter some text to search for');
                return;
            }

            const button = event.target;
            const originalContent = button.innerHTML;

            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...';

            fetch('/admin/videos/find-file-id', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        message_text: searchText
                    })
                })
                .then(response => response.json())
                .then(data => {
                    const resultsDiv = document.getElementById('search-results');
                    const contentDiv = document.getElementById('results-content');

                    if (data.success && data.found_videos && data.found_videos.length > 0) {
                        let resultsHtml = '<div class="alert alert-success">Found ' + data.found_videos.length +
                            ' matching video(s):</div>';

                        data.found_videos.forEach((video, index) => {
                            resultsHtml += `
                            <div class="card mb-2">
                                <div class="card-body">
                                    <h6>Video ${index + 1}</h6>
                                    <p><strong>Caption:</strong> ${video.caption}</p>
                                    <p><strong>Duration:</strong> ${video.duration} seconds</p>
                                    <p><strong>File Size:</strong> ${video.file_size} bytes</p>
                                    <p><strong>Date:</strong> ${video.date}</p>
                                    <div class="mb-2">
                                        <strong>File ID:</strong>
                                        <code class="bg-light p-1 d-block mt-1" style="font-size: 0.8em; word-break: break-all;">
                                            ${video.file_id}
                                        </code>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-success"
                                            onclick="copyFileId('${video.file_id}')">
                                        <i class="fas fa-copy"></i> Copy File ID
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            onclick="useFileId('${video.file_id}', '${video.caption.replace(/'/g, "\\'")}')">
                                        <i class="fas fa-plus"></i> Use This Video
                                    </button>
                                </div>
                            </div>
                        `;
                        });

                        contentDiv.innerHTML = resultsHtml;
                    } else {
                        contentDiv.innerHTML = '<div class="alert alert-warning">' + (data.message ||
                            'No videos found with that text') + '</div>';
                    }

                    resultsDiv.style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('results-content').innerHTML =
                        '<div class="alert alert-danger">Error searching for videos</div>';
                    document.getElementById('search-results').style.display = 'block';
                })
                .finally(() => {
                    button.disabled = false;
                    button.innerHTML = originalContent;
                });
        }

        // Copy file ID to clipboard
        function copyFileId(fileId) {
            navigator.clipboard.writeText(fileId).then(() => {
                // Show temporary success message
                const button = event.target;
                const originalContent = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i> Copied!';
                button.className = 'btn btn-sm btn-success';

                setTimeout(() => {
                    button.innerHTML = originalContent;
                    button.className = 'btn btn-sm btn-outline-success';
                }, 2000);
            }).catch(err => {
                alert('Failed to copy to clipboard. Please select and copy manually.');
            });
        }

        // Use this file ID - close find modal and open add modal with file ID pre-filled
        function useFileId(fileId, caption) {
            // Close find modal
            const findModal = bootstrap.Modal.getInstance(document.getElementById('findFileIdModal'));
            findModal.hide();

            // Open add modal with pre-filled data
            setTimeout(() => {
                document.getElementById('file_id').value = fileId;
                document.getElementById('add_title').value = caption || 'Imported Video';

                const addModal = new bootstrap.Modal(document.getElementById('addVideoModal'));
                addModal.show();
            }, 500);
        }

        // Simple import all videos function
        function importAllVideos() {
            const button = event.target;
            const originalText = button.innerHTML;

            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';

            fetch('/admin/videos/import-known-videos', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-success alert-dismissible fade show';
                        alertDiv.innerHTML = `
                        <strong>Success!</strong> ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                        document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector(
                            '.container-fluid').firstChild);

                        // Reload page to show new videos
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        alert('Error: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error importing videos');
                })
                .finally(() => {
                    button.disabled = false;
                    button.innerHTML = originalText;
                });
        }

        // Check webhook status on page load
        document.addEventListener('DOMContentLoaded', function() {
            checkWebhookStatus();
        });
    </script>
@endsection
