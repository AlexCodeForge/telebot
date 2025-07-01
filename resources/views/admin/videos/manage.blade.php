@extends('layout')

@section('title', 'Manage Videos')

@section('content')
    <div class="container-fluid">
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
                        <th>Thumbnail</th>
                        <th>Video Details</th>
                        <th>Metadata</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($videos as $video)
                        <tr>
                            <td style="width: 120px;">
                                @if($video->getThumbnailUrl())
                                    <img src="{{ $video->getThumbnailUrl() }}"
                                         alt="Thumbnail"
                                         class="img-thumbnail"
                                         style="width: 100px; height: 56px; object-fit: cover;">
                                @else
                                    <div class="d-flex align-items-center justify-content-center bg-light"
                                         style="width: 100px; height: 56px;">
                                        <i class="fas fa-video text-muted"></i>
                                    </div>
                                    @if($video->telegram_file_id)
                                        <div class="mt-1">
                                            <form method="POST" action="{{ route('admin.videos.generate-thumbnail', $video) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-primary btn-xs" title="Generate Thumbnail">
                                                    <i class="fas fa-image"></i>
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                @endif

                                @if(!$video->getThumbnailUrl())
                                    <span class="badge bg-warning text-dark" style="font-size: 0.6rem;">No Thumbnail</span>
                                @endif
                            </td>
                            <td>
                                <div>
                                    <strong>{{ $video->title ?: 'Video #' . $video->id }}</strong>
                                    @if ($video->description)
                                        <br><small class="text-muted">{{ Str::limit($video->description, 60) }}</small>
                                    @endif
                                    <br><small class="text-muted">Added {{ $video->created_at->diffForHumans() }}</small>
                                </div>
                            </td>
                            <td>
                                @if($video->duration)
                                    <div><i class="fas fa-clock"></i> {{ gmdate("H:i:s", $video->duration) }}</div>
                                @endif
                                @if($video->file_size)
                                    <div><i class="fas fa-hdd"></i> {{ number_format($video->file_size / 1024 / 1024, 1) }} MB</div>
                                @endif
                                @if($video->width && $video->height)
                                    <div><i class="fas fa-expand-arrows-alt"></i> {{ $video->width }}x{{ $video->height }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="me-2">${{ number_format($video->price, 2) }}</span>
                                    <button onclick="editVideo({{ $video->id }}, '{{ addslashes($video->title) }}', '{{ addslashes($video->description) }}', {{ $video->price }})"
                                            class="btn btn-outline-primary btn-sm" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                                {{-- Quick Price Buttons --}}
                                <div class="mt-1">
                                    @foreach ([4.99, 9.99, 19.99, 0] as $price)
                                        <button onclick="quickPrice({{ $video->id }}, {{ $price }})"
                                                class="btn btn-outline-secondary btn-xs me-1"
                                                style="font-size: 0.65rem; padding: 1px 3px;">
                                            @if($price == 0) Free @else ${{ $price }} @endif
                                        </button>
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                @if ($video->price > 0)
                                    <span class="badge bg-success">Ready</span>
                                @else
                                    <span class="badge bg-warning">Needs Price</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group-vertical btn-group-sm">
                                    @if ($video->telegram_file_id)
                                        <form method="POST" action="{{ route('admin.videos.test', $video) }}" class="d-inline mb-1">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-info btn-sm" title="Test Send">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </form>
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
                            <td colspan="6" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="fas fa-video fa-3x mb-3"></i>
                                    <h5>No videos found</h5>
                                    <p>Videos sent to your Telegram bot will appear here automatically.</p>
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

        {{-- Sync Configuration --}}
        @if(isset($syncDisplayInfo))
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-sync-alt me-2"></i>Sync Configuration
                    </h5>
                    <span class="badge bg-success">Active</span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Current Sync Target</h6>
                            <p class="mb-1"><strong>{{ $syncDisplayInfo['name'] }}</strong></p>
                            <p class="text-muted mb-0">Telegram ID: {{ $syncDisplayInfo['telegram_id'] }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Sync Method</h6>
                            <p class="mb-1">
                                @if($syncMethod === 'webhook')
                                    <span class="badge bg-info">Webhook-based (automatic capture)</span>
                                @else
                                    <span class="badge bg-primary">getUpdates (polling, default)</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" onclick="openSyncUserModal()" class="btn btn-outline-primary me-2">
                            <i class="fas fa-user-cog me-1"></i> Set Sync User
                        </button>
                        <button type="button" onclick="openSyncMethodModal()" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-exchange-alt me-1"></i> Sync Method
                        </button>
                        <form method="POST" action="{{ route('admin.videos.sync') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-sync me-1"></i> Sync Videos
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Edit Video Modal --}}
    <div class="modal fade" id="editVideoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Video</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editVideoForm">
                        <input type="hidden" id="editVideoId">
                        <div class="mb-3">
                            <label for="editTitle" class="form-label">Title</label>
                            <input type="text" class="form-control" id="editTitle" required>
                        </div>
                        <div class="mb-3">
                            <label for="editDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="editDescription" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="editPrice" class="form-label">Price ($)</label>
                            <input type="number" class="form-control" id="editPrice" step="0.01" min="0" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveVideo()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Sync User Modal --}}
    <div class="modal fade" id="syncUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Set Sync User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('admin.videos.set-sync-user') }}">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="telegramId" class="form-label">Telegram User ID</label>
                            <input type="number" class="form-control" name="telegram_id" id="telegramId" required
                                   placeholder="Enter the Telegram User ID">
                            <div class="form-text">
                                To find a Telegram ID: Forward a message from the user to @userinfobot
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Set Sync User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Sync Method Modal --}}
    <div class="modal fade" id="syncMethodModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Configure Sync Method</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('admin.videos.set-sync-method') }}">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="sync_method" value="getupdates"
                                       {{ $syncMethod === 'getupdates' ? 'checked' : '' }} id="methodGetUpdates">
                                <label class="form-check-label" for="methodGetUpdates">
                                    <strong>getUpdates (Polling) - Default</strong><br>
                                    <small class="text-muted">
                                        • Works in all environments (local, hosting with/without SSL)<br>
                                        • Temporarily removes webhook during sync<br>
                                        • Only syncs recent messages (last ~100 updates)<br>
                                        • Safe and reliable for most use cases
                                    </small>
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="sync_method" value="webhook"
                                       {{ $syncMethod === 'webhook' ? 'checked' : '' }} id="methodWebhook">
                                <label class="form-check-label" for="methodWebhook">
                                    <strong>Webhook-based (Automatic)</strong><br>
                                    <small class="text-muted">
                                        • Videos automatically captured when sent to bot<br>
                                        • No conflicts with existing webhooks<br>
                                        • Requires videos to be sent directly to the bot<br>
                                        • Better for production with active webhook setup
                                    </small>
                                </label>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Recommendation:</strong> Use getUpdates for development/testing and webhook for production environments.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Method</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function editVideo(id, title, description, price) {
            document.getElementById('editVideoId').value = id;
            document.getElementById('editTitle').value = title;
            document.getElementById('editDescription').value = description;
            document.getElementById('editPrice').value = price;

            const modal = new bootstrap.Modal(document.getElementById('editVideoModal'));
            modal.show();
        }

        function saveVideo() {
            const id = document.getElementById('editVideoId').value;
            const title = document.getElementById('editTitle').value;
            const description = document.getElementById('editDescription').value;
            const price = document.getElementById('editPrice').value;

            fetch(`/admin/videos/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    title: title,
                    description: description,
                    price: parseFloat(price)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error updating video');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating video');
            });
        }

        function quickPrice(videoId, price) {
            fetch(`/admin/videos/${videoId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    title: document.querySelector(`tr:has(button[onclick*="${videoId}"]) strong`).textContent,
                    description: '',
                    price: price
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error updating price');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating price');
            });
        }

        function openSyncUserModal() {
            const modal = new bootstrap.Modal(document.getElementById('syncUserModal'));
            modal.show();
        }

        function openSyncMethodModal() {
            const modal = new bootstrap.Modal(document.getElementById('syncMethodModal'));
            modal.show();
        }
    </script>
@endsection
