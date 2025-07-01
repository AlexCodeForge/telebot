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
                            Webhook active = Auto-capture videos when sent to bot<br>
                            Webhook disabled = Can manually import via file IDs from conversation
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

        {{-- Test Connection & Manual Import --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-tools text-info"></i> Manual Video Import
                </h5>
            </div>
                    <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Step 1: Test Connection & Get File IDs</h6>
                        <p class="text-muted small">
                            View recent conversation history to find video file IDs for manual import
                        </p>
                        <button type="button" class="btn btn-info" onclick="testTelegramConnection()">
                            <i class="fas fa-search"></i> Test Connection & View File IDs
                        </button>
                    </div>
                    <div class="col-md-6">
                        <h6>Step 2: Manual Import</h6>
                        <div class="mb-2">
                            <input type="text" id="manual-file-id" class="form-control form-control-sm"
                                   placeholder="Paste file ID from test connection">
                        </div>
                        <div class="row">
                            <div class="col-7">
                                <input type="text" id="manual-title" class="form-control form-control-sm"
                                       placeholder="Video title" value="Imported Video">
                            </div>
                            <div class="col-5">
                                <input type="number" id="manual-price" class="form-control form-control-sm"
                                       placeholder="Price" value="4.99" step="0.01">
                            </div>
                        </div>
                        <button type="button" class="btn btn-success btn-sm mt-2" onclick="manualImportVideo()">
                            <i class="fas fa-upload"></i> Import Video
                        </button>
                    </div>
                </div>

                {{-- Test Results Display --}}
                <div id="test-results" class="mt-4" style="display: none;">
                    <h6>Connection Test Results:</h6>
                    <div id="test-content" class="border p-3 bg-light" style="max-height: 400px; overflow-y: auto;">
                        <!-- Test results will be populated here -->
                    </div>
                </div>
            </div>
        </div>

        {{-- Database Management --}}
        <div class="card mb-4">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-database"></i> Database Management
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <strong>Warning:</strong> This will permanently delete all videos from the database. This action cannot be undone.
                </div>
                <button type="button" class="btn btn-danger" onclick="clearAllVideos()">
                    <i class="fas fa-trash"></i> Clear All Videos
                </button>
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
                @if(count($videos) > 0)
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
                                @foreach($videos as $video)
                            <tr>
                                <td>
                                            <strong>{{ $video->title }}</strong><br>
                                            <small class="text-muted">Created: {{ $video->created_at->format('M j, Y H:i') }}</small>
                                </td>
                                <td>
                                            <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                                {{ $video->description ?? 'No description' }}
                                    </div>
                                </td>
                                <td>
                                            @if($video->price > 0)
                                                <span class="badge bg-success">${{ number_format($video->price, 2) }}</span>
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
                                                <button type="button" class="btn btn-outline-success"
                                                        onclick="testVideo({{ $video->id }})">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
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
                        <p class="text-muted">Use the tools above to import videos or enable webhook to auto-capture.</p>
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
                            <input type="number" class="form-control" id="edit-price" name="price" step="0.01" min="0" required>
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

@push('scripts')
    <script>
document.addEventListener('DOMContentLoaded', function() {
    checkWebhookStatus();
});

// Check webhook status
function checkWebhookStatus() {
    fetch('{{ route("videos.webhook-status") }}')
        .then(response => response.json())
        .then(data => {
            const statusBadge = document.getElementById('webhook-status');
            if (data.success) {
                const isActive = data.webhook_info.url && data.webhook_info.url.length > 0;
                statusBadge.textContent = isActive ? 'Active' : 'Disabled';
                statusBadge.className = `ms-2 badge ${isActive ? 'bg-success' : 'bg-warning'}`;
            } else {
                statusBadge.textContent = 'Error';
                statusBadge.className = 'ms-2 badge bg-danger';
            }
        })
        .catch(error => {
            document.getElementById('webhook-status').textContent = 'Error';
            console.error('Webhook status check failed:', error);
        });
}

// Toggle webhook
function toggleWebhook(action) {
    const url = action === 'activate' ?
        '{{ route("videos.reactivate-webhook") }}' :
        '{{ route("videos.deactivate-webhook") }}';

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

// Test Telegram connection
function testTelegramConnection() {
    const testResults = document.getElementById('test-results');
    const testContent = document.getElementById('test-content');

    testContent.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Testing connection...</div>';
    testResults.style.display = 'block';

    fetch('{{ route("videos.test-connection") }}')
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
                html += `<strong>Can use getUpdates:</strong> ${data.data.can_use_getupdates ? 'Yes' : 'No'}</p>`;

                if (data.data.message_analysis && data.data.message_analysis.length > 0) {
                    html += '<h6>Recent Messages with Video File IDs:</h6>';
                    html += '<div class="table-responsive">';
                    html += '<table class="table table-sm table-bordered">';
                    html += '<thead><tr><th>From</th><th>Date</th><th>Video File ID</th><th>Caption</th><th>Action</th></tr></thead><tbody>';

                    data.data.message_analysis.forEach(msg => {
                        if (msg.has_video || msg.video_file_id || msg.document_file_id) {
                            const fileId = msg.video_file_id || msg.document_file_id;
                            html += '<tr class="table-success">';
                            html += `<td>${msg.from_first_name || 'Unknown'} (@${msg.from_username || 'no username'})<br><small>ID: ${msg.from_id}</small></td>`;
                            html += `<td><small>${msg.date}</small></td>`;
                            html += `<td><code style="font-size: 10px;">${fileId}</code></td>`;
                            html += `<td>${msg.caption || msg.text || '-'}</td>`;
                            html += `<td><button class="btn btn-success btn-xs" onclick="quickImport('${fileId}', '${(msg.caption || 'Imported Video').replace(/'/g, "\\'")}')">Import</button></td>`;
                            html += '</tr>';
                        }
                    });

                    html += '</tbody></table></div>';
                    html += `<p><strong>Summary:</strong> Found ${data.data.video_messages_found} video messages out of ${data.data.total_messages_found} total messages.</p>`;
                } else if (data.data.message) {
                    html += `<div class="alert alert-warning">${data.data.message}</div>`;
                } else {
                    html += '<div class="alert alert-info">No recent video messages found in conversation history.</div>';
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
    document.getElementById('manual-file-id').value = fileId;
    document.getElementById('manual-title').value = title;
    manualImportVideo();
}

// Manual import video
function manualImportVideo() {
    const fileId = document.getElementById('manual-file-id').value.trim();
    const title = document.getElementById('manual-title').value.trim();
    const price = document.getElementById('manual-price').value;

    if (!fileId) {
        showAlert('warning', 'Please enter a file ID');
        return;
    }

    fetch('{{ route("videos.manual-import") }}', {
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

// Clear all videos
function clearAllVideos() {
    if (!confirm('Are you sure you want to delete ALL videos? This action cannot be undone!')) {
        return;
    }

    fetch('{{ route("videos.clear-all") }}', {
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
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showAlert('danger', data.error || 'Clear operation failed');
        }
    })
    .catch(error => {
        showAlert('danger', 'Network error occurred');
        console.error('Clear videos failed:', error);
    });
}

// Edit video
function editVideo(id, title, description, price) {
    document.getElementById('edit-title').value = title;
    document.getElementById('edit-description').value = description || '';
    document.getElementById('edit-price').value = price;
    document.getElementById('editVideoForm').action = `/videos/${id}`;

    new bootstrap.Modal(document.getElementById('editVideoModal')).show();
}

// Test video
function testVideo(id) {
    fetch(`/videos/${id}/test`, {
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

    fetch(`/videos/${id}`, {
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

    document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector('.container-fluid').firstChild);

    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
        }
    </script>
@endpush
