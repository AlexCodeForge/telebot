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

        {{-- Bulk Actions --}}
        <form method="POST" action="{{ route('admin.videos.bulk-action') }}" id="bulkForm">
            @csrf
            <div class="row mb-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <select name="action" class="form-select" required>
                            <option value="">Bulk Actions...</option>
                            <option value="set_price">Set Price</option>
                            <option value="make_free">Make Free</option>
                            <option value="delete">Delete Selected</option>
                        </select>
                        <input type="number" name="bulk_price" step="0.01" min="0" placeholder="Price"
                            class="form-control" style="display:none;" id="bulkPrice">
                        <button type="submit" class="btn btn-primary">Apply</button>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <span class="small text-muted">{{ $videos->count() }} of {{ $videos->total() }} videos</span>
                </div>
            </div>

            {{-- Videos Table --}}
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>Video</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($videos as $video)
                            <tr>
                                <td>
                                    <input type="checkbox" name="video_ids[]" value="{{ $video->id }}"
                                        class="video-checkbox">
                                </td>
                                <td>
                                    <div>
                                        <strong>{{ $video->title ?: 'Video #' . $video->id }}</strong>
                                        @if ($video->description)
                                            <br><small class="text-muted">{{ Str::limit($video->description, 60) }}</small>
                                        @endif
                                        <br><small class="text-muted">Added
                                            {{ $video->created_at->diffForHumans() }}</small>
                                    </div>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.videos.update', $video) }}"
                                        class="d-inline" style="max-width: 200px;">
                                        @csrf
                                        @method('PUT')
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">$</span>
                                            <input type="number" name="price" step="0.01" min="0"
                                                value="{{ $video->price }}" class="form-control">
                                            <button type="submit" class="btn btn-outline-primary btn-sm">Save</button>
                                        </div>
                                        <input type="hidden" name="title"
                                            value="{{ $video->title ?: 'Video #' . $video->id }}">
                                    </form>
                                    {{-- Quick Price Buttons --}}
                                    <div class="mt-1">
                                        @foreach ([4.99, 9.99, 19.99] as $price)
                                            <button onclick="setPrice({{ $video->id }}, {{ $price }})"
                                                class="btn btn-outline-secondary btn-xs me-1"
                                                style="font-size: 0.7rem; padding: 1px 4px;">${{ $price }}</button>
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
                                    <div class="btn-group btn-group-sm">
                                        @if ($video->telegram_file_id)
                                            <form method="POST" action="{{ route('admin.videos.test', $video) }}"
                                                class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-info btn-sm"
                                                    title="Test Send">
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
                                <td colspan="5" class="text-center py-4">
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
        </form>

        {{-- Pagination --}}
        @if ($videos->hasPages())
            <div class="d-flex justify-content-center">
                {{ $videos->links() }}
            </div>
        @endif
    </div>

    <script>
        // Select all functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.video-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
        });

        // Show price input when set_price is selected
        document.querySelector('select[name="action"]').addEventListener('change', function() {
            const priceInput = document.getElementById('bulkPrice');
            if (this.value === 'set_price') {
                priceInput.style.display = 'block';
                priceInput.required = true;
            } else {
                priceInput.style.display = 'none';
                priceInput.required = false;
            }
        });

        // Quick price setting
        function setPrice(videoId, price) {
            const form = document.querySelector(`form[action*="videos/${videoId}"] input[name="price"]`);
            if (form) {
                form.value = price;
                form.closest('form').submit();
            }
        }
    </script>
@endsection
