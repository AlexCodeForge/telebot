<div class="row">
    <div class="col-md-6">
        <h6><i class="fas fa-receipt me-2"></i>Purchase Information</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>UUID:</strong></td>
                <td><code style="font-size: 11px;">{{ $purchase->purchase_uuid }}</code></td>
            </tr>
            <tr>
                <td><strong>Date:</strong></td>
                <td>{{ $purchase->created_at->format('M d, Y H:i:s') }}</td>
            </tr>
            <tr>
                <td><strong>Amount:</strong></td>
                <td><span class="h6 text-success">{{ $purchase->formatted_amount }}</span></td>
            </tr>
            <tr>
                <td><strong>Currency:</strong></td>
                <td>{{ strtoupper($purchase->currency) }}</td>
            </tr>
            <tr>
                <td><strong>Purchase Status:</strong></td>
                <td>
                    <span class="badge bg-{{ $purchase->purchase_status === 'completed' ? 'success' : 'warning' }}">
                        {{ ucfirst($purchase->purchase_status) }}
                    </span>
                </td>
            </tr>
            <tr>
                <td><strong>Verification:</strong></td>
                <td>
                    @if ($purchase->verification_status === 'pending')
                        <span class="badge bg-warning">Pending</span>
                    @elseif($purchase->verification_status === 'verified')
                        <span class="badge bg-success">Verified</span>
                    @else
                        <span class="badge bg-danger">Invalid</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="col-md-6">
        <h6><i class="fas fa-user me-2"></i>Customer Information</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Telegram Username:</strong></td>
                <td>@{{ $purchase - > telegram_username }}</td>
            </tr>
            @if ($purchase->telegram_user_id)
                <tr>
                    <td><strong>Telegram User ID:</strong></td>
                    <td>{{ $purchase->telegram_user_id }}</td>
                </tr>
            @endif
            @if ($purchase->customer_email)
                <tr>
                    <td><strong>Email:</strong></td>
                    <td>{{ $purchase->customer_email }}</td>
                </tr>
            @endif
            @if ($purchase->user)
                <tr>
                    <td><strong>Account Name:</strong></td>
                    <td>{{ $purchase->user->name }}</td>
                </tr>
            @endif
        </table>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-6">
        <h6><i class="fas fa-video me-2"></i>Video Information</h6>
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">{{ $purchase->video->title }}</h6>
                @if ($purchase->video->description)
                    <p class="card-text">{{ Str::limit($purchase->video->description, 100) }}</p>
                @endif
                <p class="card-text">
                    <small class="text-muted">Video ID: {{ $purchase->video->id }}</small><br>
                    <small class="text-muted">Price: ${{ number_format($purchase->video->price, 2) }}</small>
                </p>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <h6><i class="fas fa-truck me-2"></i>Delivery Status</h6>
        <div class="card">
            <div class="card-body">
                <div class="mb-2">
                    @if ($purchase->delivery_status === 'pending')
                        <span class="badge bg-info">Pending</span>
                    @elseif($purchase->delivery_status === 'delivered')
                        <span class="badge bg-success">Delivered</span>
                    @elseif($purchase->delivery_status === 'failed')
                        <span class="badge bg-danger">Failed</span>
                    @else
                        <span class="badge bg-warning">Retrying</span>
                    @endif
                </div>

                @if ($purchase->delivered_at)
                    <p class="small text-success">
                        <i class="fas fa-check-circle me-1"></i>
                        Delivered: {{ $purchase->delivered_at->format('M d, Y H:i:s') }}
                    </p>
                @endif

                @if ($purchase->delivery_attempts > 0)
                    <p class="small text-muted">
                        Delivery attempts: {{ $purchase->delivery_attempts }}
                    </p>
                @endif

                @if ($purchase->delivery_notes)
                    <p class="small">
                        <strong>Notes:</strong> {{ $purchase->delivery_notes }}
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>

@if ($purchase->stripe_metadata)
    <div class="row mt-3">
        <div class="col-12">
            <h6><i class="fab fa-stripe me-2"></i>Stripe Information</h6>
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Session ID:</strong><br>
                            <code style="font-size: 10px;">{{ $purchase->stripe_session_id }}</code>
                        </div>
                        @if ($purchase->stripe_payment_intent_id)
                            <div class="col-md-4">
                                <strong>Payment Intent:</strong><br>
                                <code style="font-size: 10px;">{{ $purchase->stripe_payment_intent_id }}</code>
                            </div>
                        @endif
                        @if ($purchase->stripe_customer_id)
                            <div class="col-md-4">
                                <strong>Customer ID:</strong><br>
                                <code style="font-size: 10px;">{{ $purchase->stripe_customer_id }}</code>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

<!-- Action Buttons -->
<div class="row mt-4">
    <div class="col-12">
        <div class="d-flex gap-2">
            @if ($purchase->verification_status === 'pending')
                <button type="button" class="btn btn-success btn-sm" onclick="verifyPurchase('{{ $purchase->id }}')">
                    <i class="fas fa-check me-1"></i>Verify Purchase
                </button>
            @endif

            @if ($purchase->delivery_status === 'failed' && $purchase->canRetryDelivery())
                <button type="button" class="btn btn-warning btn-sm" onclick="retryDelivery('{{ $purchase->id }}')">
                    <i class="fas fa-redo me-1"></i>Retry Delivery
                </button>
            @endif

            @if ($purchase->delivery_status !== 'delivered')
                <button type="button" class="btn btn-primary btn-sm" onclick="markDelivered('{{ $purchase->id }}')">
                    <i class="fas fa-truck me-1"></i>Mark as Delivered
                </button>
            @endif

            <a href="{{ route('purchase.view', $purchase->purchase_uuid) }}" target="_blank"
                class="btn btn-outline-info btn-sm">
                <i class="fas fa-external-link-alt me-1"></i>Customer View
            </a>
        </div>
    </div>
</div>
