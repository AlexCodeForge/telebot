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
                        <small class="text-muted">Only videos from this user will be auto-captured. The bot will interact
                            normally with all other users but ignore their videos.</small>
                    </div>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeSyncUser()">
                        <i class="fas fa-trash"></i> Remove Sync User
                    </button>
                @else
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> <strong>No sync user configured.</strong><br>
                        You need to set a sync user to control which Telegram user's videos can be imported.
                    </div>
                    <form onsubmit="setSyncUser(event)" class="row g-3">
                        <div class="col-md-4">
                            <label for="sync-telegram-id" class="form-label">Telegram User ID</label>
                            <input type="text" class="form-control" id="sync-telegram-id" placeholder="e.g., 123456789"
                                required>
                        </div>
                        <div class="col-md-4">
                            <label for="sync-name" class="form-label">Display Name</label>
                            <input type="text" class="form-control" id="sync-name" placeholder="e.g., John Doe" required>
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
                            <strong>Disabled:</strong> Videos are not automatically captured (use manual import)
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
                    <div id="test-content" class="border p-3 bg-light" style="max-height: 400px; overflow-y: auto;">
                        <!-- Test results will be populated here -->
                    </div>
                </div>
            </div>
        </div>

        {{-- Manual Video Import - Only show when webhook is disabled --}}
        <div class="card mb-4" id="manual-import-section" style="display: none;">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-tools text-info"></i> Manual Video Import
                </h5>
            </div>
            <div class="card-body">
                @if (!$syncUserTelegramId)
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Please configure a sync user first before using manual
                        import.
                    </div>
                @else
                    <p class="text-muted">
                        <i class="fas fa-info-circle"></i> Manual import is only available when webhook is disabled.
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
                            <input type="number" id="manual-price" class="form-control" placeholder="Price"
                                value="4.99" step="0.01">
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-8">
                            <label for="manual-title" class="form-label">Video Title</label>
                            <input type="text" id="manual-title" class="form-control" placeholder="Video title"
                                value="Imported Video">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" class="btn btn-success w-100" onclick="manualImportVideo()">
                                <i class="fas fa-upload"></i> Import Video
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

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
                                            <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
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
                                            <code style="font-size: 10px;">{{ $video->telegram_file_id }}</code>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary"
                                                    onclick="editVideo({{ $video->id }}, '{{ addslashes($video->title) }}', '{{ addslashes($video->description) }}', {{ $video->price }})">
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
                @else
                    <div class="text-center py-5">
                        <i class="fas fa-video fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No videos found</h5>
                        <p class="text-muted">Configure sync user and enable webhook to auto-capture videos, or use manual
                            import.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Edit Video Modal --}}
    <div class="modal fade" id="editVideoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Video</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editVideoForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit-title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="edit-title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit-description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit-description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit-price" class="form-label">Price</label>
                            <input type="number" class="form-control" id="edit-price" name="price" step="0.01"
                                min="0" required>
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

            if (isWebhookActive) {
                manualImportSection.style.display = 'none';
            } else {
                manualImportSection.style.display = 'block';
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
            manualImportVideo();
        }

        // Manual import video
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

        // Edit video
        function editVideo(id, title, description, price) {
            document.getElementById('edit-title').value = title;
            document.getElementById('edit-description').value = description || '';
            document.getElementById('edit-price').value = price;
            document.getElementById('editVideoForm').action = `/admin/videos/${id}`;

            new bootstrap.Modal(document.getElementById('editVideoModal')).show();
        }

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
    </script>
@endsection
