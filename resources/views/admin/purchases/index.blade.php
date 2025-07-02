@extends('layout')

@section('title', 'Purchase Management')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <i class="fas fa-shopping-cart me-2"></i>
                        Purchase Management
                    </h2>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Total Purchases</h6>
                                        <h3 class="mb-0">{{ $stats['total'] }}</h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-shopping-cart fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Total Revenue</h6>
                                        <h3 class="mb-0">${{ number_format($stats['total_revenue'], 2) }}</h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-dollar-sign fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Pending Verification</h6>
                                        <h3 class="mb-0">{{ $stats['pending_verification'] }}</h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Failed Deliveries</h6>
                                        <h3 class="mb-0">{{ $stats['failed_delivery'] }}</h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="{{ route('admin.purchases.index') }}" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search"
                                    value="{{ request('search') }}" placeholder="Username, email, or UUID">
                            </div>
                            <div class="col-md-2">
                                <label for="purchase_status" class="form-label">Purchase Status</label>
                                <select class="form-select" id="purchase_status" name="purchase_status">
                                    <option value="">All</option>
                                    <option value="completed"
                                        {{ request('purchase_status') === 'completed' ? 'selected' : '' }}>Completed
                                    </option>
                                    <option value="refunded"
                                        {{ request('purchase_status') === 'refunded' ? 'selected' : '' }}>Refunded</option>
                                    <option value="disputed"
                                        {{ request('purchase_status') === 'disputed' ? 'selected' : '' }}>Disputed</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="verification_status" class="form-label">Verification</label>
                                <select class="form-select" id="verification_status" name="verification_status">
                                    <option value="">All</option>
                                    <option value="pending"
                                        {{ request('verification_status') === 'pending' ? 'selected' : '' }}>Pending
                                    </option>
                                    <option value="verified"
                                        {{ request('verification_status') === 'verified' ? 'selected' : '' }}>Verified
                                    </option>
                                    <option value="invalid"
                                        {{ request('verification_status') === 'invalid' ? 'selected' : '' }}>Invalid
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="delivery_status" class="form-label">Delivery Status</label>
                                <select class="form-select" id="delivery_status" name="delivery_status">
                                    <option value="">All</option>
                                    <option value="pending"
                                        {{ request('delivery_status') === 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="delivered"
                                        {{ request('delivery_status') === 'delivered' ? 'selected' : '' }}>Delivered
                                    </option>
                                    <option value="failed" {{ request('delivery_status') === 'failed' ? 'selected' : '' }}>
                                        Failed</option>
                                    <option value="retrying"
                                        {{ request('delivery_status') === 'retrying' ? 'selected' : '' }}>Retrying</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Filter
                                    </button>
                                    <a href="{{ route('admin.purchases.index') }}" class="btn btn-outline-secondary">
                                        <i class="fas fa-undo me-1"></i>Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Purchases Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Purchase Info</th>
                                        <th>Customer</th>
                                        <th>Video</th>
                                        <th>Amount</th>
                                        <th>Verification</th>
                                        <th>Delivery</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($purchases as $purchase)
                                        <tr>
                                            <td>
                                                <div>
                                                    <small class="text-muted">UUID:</small><br>
                                                    <code style="font-size: 10px;">{{ $purchase->purchase_uuid }}</code>
                                                </div>
                                                <div class="mt-1">
                                                    <span
                                                        class="badge bg-{{ $purchase->purchase_status === 'completed' ? 'success' : 'warning' }}">
                                                        {{ ucfirst($purchase->purchase_status) }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong>@{{ $purchase - > telegram_username }}</strong>
                                                </div>
                                                @if ($purchase->customer_email)
                                                    <small class="text-muted">{{ $purchase->customer_email }}</small>
                                                @endif
                                                @if ($purchase->telegram_user_id)
                                                    <br><small class="text-success">ID:
                                                        {{ $purchase->telegram_user_id }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <div>
                                                    <strong>{{ $purchase->video->title }}</strong>
                                                </div>
                                                <small class="text-muted">ID: {{ $purchase->video->id }}</small>
                                            </td>
                                            <td>
                                                <span class="h6 text-success">{{ $purchase->formatted_amount }}</span>
                                            </td>
                                            <td>
                                                @if ($purchase->verification_status === 'pending')
                                                    <span class="badge bg-warning">Pending</span>
                                                @elseif($purchase->verification_status === 'verified')
                                                    <span class="badge bg-success">Verified</span>
                                                @else
                                                    <span class="badge bg-danger">Invalid</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($purchase->delivery_status === 'pending')
                                                    <span class="badge bg-info">Pending</span>
                                                @elseif($purchase->delivery_status === 'delivered')
                                                    <span class="badge bg-success">Delivered</span>
                                                    @if ($purchase->delivered_at)
                                                        <br><small
                                                            class="text-muted">{{ $purchase->delivered_at->format('M d, H:i') }}</small>
                                                    @endif
                                                @elseif($purchase->delivery_status === 'failed')
                                                    <span class="badge bg-danger">Failed</span>
                                                    @if ($purchase->delivery_attempts > 0)
                                                        <br><small class="text-muted">{{ $purchase->delivery_attempts }}
                                                            attempts</small>
                                                    @endif
                                                @else
                                                    <span class="badge bg-warning">Retrying</span>
                                                @endif
                                            </td>
                                            <td>
                                                <small>{{ $purchase->created_at->format('M d, Y') }}<br>{{ $purchase->created_at->format('H:i:s') }}</small>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <!-- View Details -->
                                                    <button type="button" class="btn btn-sm btn-outline-info"
                                                        onclick="viewPurchase('{{ $purchase->id }}')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>

                                                    <!-- Manual Actions -->
                                                    @if ($purchase->verification_status === 'pending')
                                                        <button type="button" class="btn btn-sm btn-outline-success"
                                                            onclick="verifyPurchase('{{ $purchase->id }}')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    @endif

                                                    @if ($purchase->delivery_status === 'failed' && $purchase->canRetryDelivery())
                                                        <button type="button" class="btn btn-sm btn-outline-warning"
                                                            onclick="retryDelivery('{{ $purchase->id }}')">
                                                            <i class="fas fa-redo"></i>
                                                        </button>
                                                    @endif

                                                    @if ($purchase->delivery_status !== 'delivered')
                                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                            onclick="markDelivered('{{ $purchase->id }}')">
                                                            <i class="fas fa-truck"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                                <br>No purchases found
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        @if ($purchases->hasPages())
                            <div class="d-flex justify-content-center mt-4">
                                {{ $purchases->appends(request()->query())->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Purchase Details Modal -->
    <div class="modal fade" id="purchaseDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Purchase Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="purchaseDetailsContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Verify Purchase Modal -->
    <div class="modal fade" id="verifyPurchaseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Verify Purchase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="verifyPurchaseForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="telegram_user_id" class="form-label">Telegram User ID</label>
                            <input type="text" class="form-control" id="telegram_user_id" name="telegram_user_id"
                                required>
                            <div class="form-text">Enter the customer's Telegram user ID to link this purchase.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Verify Purchase</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Mark Delivered Modal -->
    <div class="modal fade" id="markDeliveredModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Mark as Delivered</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="markDeliveredForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="delivery_notes" class="form-label">Delivery Notes (Optional)</label>
                            <textarea class="form-control" id="delivery_notes" name="delivery_notes" rows="3"></textarea>
                            <div class="form-text">Add any notes about the manual delivery.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Mark as Delivered</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        let currentPurchaseId = null;

        // View purchase details
        function viewPurchase(purchaseId) {
            fetch(`/admin/purchases/${purchaseId}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(html => {
                    document.getElementById('purchaseDetailsContent').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('purchaseDetailsModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('error', 'Failed to load purchase details: ' + error.message);
                });
        }

        // Verify purchase
        function verifyPurchase(purchaseId) {
            currentPurchaseId = purchaseId;
            new bootstrap.Modal(document.getElementById('verifyPurchaseModal')).show();
        }

        // Mark as delivered
        function markDelivered(purchaseId) {
            currentPurchaseId = purchaseId;
            new bootstrap.Modal(document.getElementById('markDeliveredModal')).show();
        }

        // Retry delivery
        function retryDelivery(purchaseId) {
            if (confirm('Are you sure you want to retry delivery for this purchase?')) {
                fetch(`/admin/purchases/${purchaseId}/retry-delivery`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                        },
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showAlert('success', data.message);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showAlert('error', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert('error', 'An error occurred');
                    });
            }
        }

        // Handle verify purchase form
        document.getElementById('verifyPurchaseForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch(`/admin/purchases/${currentPurchaseId}/verify`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);
                        bootstrap.Modal.getInstance(document.getElementById('verifyPurchaseModal')).hide();
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('error', 'An error occurred');
                });
        });

        // Handle mark delivered form
        document.getElementById('markDeliveredForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch(`/admin/purchases/${currentPurchaseId}/mark-delivered`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);
                        bootstrap.Modal.getInstance(document.getElementById('markDeliveredModal')).hide();
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('error', 'An error occurred');
                });
        });

        // Alert function
        function showAlert(type, message) {
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show position-fixed"
                 style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
            document.body.insertAdjacentHTML('beforeend', alertHtml);

            setTimeout(() => {
                const alert = document.querySelector('.alert');
                if (alert) alert.remove();
            }, 5000);
        }
    </script>
@endsection
