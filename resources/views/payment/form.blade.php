@extends('layout')

@section('title', 'Purchase ' . $video->title)

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-shopping-cart"></i> Purchase Video</h4>
                </div>
                <div class="card-body">
                    <!-- Video Details -->
                    <div class="card bg-light mb-4">
                        <div class="card-body">
                            <h5 class="card-title">{{ $video->title }}</h5>
                            <p class="card-text text-muted">{{ $video->description }}</p>
                            <h3 class="text-success mb-0">
                                <i class="fas fa-dollar-sign"></i>{{ number_format($video->price, 2) }}
                            </h3>
                        </div>
                    </div>

                    <!-- How It Works -->
                    <div class="alert alert-info">
                        <h6 class="alert-heading"><i class="fas fa-info-circle"></i> How it works:</h6>
                        <ol class="mb-0">
                            <li><strong>Enter your Telegram username below</strong></li>
                            <li><strong>Complete your payment with Stripe</strong></li>
                            <li><strong>Start a chat with our bot and type /start to get your video!</strong></li>
                        </ol>
                    </div>

                    <!-- Payment Form -->
                    <form id="paymentForm">
                        @csrf

                        <div class="mb-3">
                            <label for="telegram_username" class="form-label">
                                <i class="fab fa-telegram"></i> Telegram Username *
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">@</span>
                                <input type="text" id="telegram_username" name="telegram_username"
                                    class="form-control @error('telegram_username') is-invalid @enderror"
                                    placeholder="your_username" value="{{ old('telegram_username') }}" required>
                            </div>
                            <div class="form-text">
                                Your Telegram username (without the @). This is how we'll deliver your video!
                            </div>
                            @error('telegram_username')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        @if ($errors->has('payment'))
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> {{ $errors->first('payment') }}
                            </div>
                        @endif

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg" id="paymentButton">
                                <i class="fas fa-credit-card"></i> Proceed to Payment
                                (${{ number_format($video->price, 2) }})
                            </button>
                        </div>
                    </form>

                    <!-- Bot Info -->
                    <div class="alert alert-success mt-4">
                        <h6 class="alert-heading"><i class="fab fa-telegram"></i> After payment:</h6>
                        <p class="mb-0">
                            Once your payment is complete, start a chat with our bot
                            <a href="https://t.me/videotestpowerbot" target="_blank" class="alert-link">
                                <strong>@videotestpowerbot</strong>
                            </a>
                            and type <strong>/start</strong> to activate your purchase and get your video!
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const button = document.getElementById('paymentButton');
        const originalText = button.innerHTML;
        const username = document.getElementById('telegram_username').value.trim();

        if (!username) {
            showAlert('error', 'Please enter your Telegram username');
            return;
        }

        // Show loading state
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
        button.disabled = true;

        // Clear any existing error messages
        clearExistingAlerts();

        fetch('/api/create-payment-intent', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({
                video_id: {{ $video->id }},
                telegram_username: username
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                // Handle duplicate purchase or other errors
                if (data.existing_purchase) {
                    showDuplicatePurchaseError(data.error, data.existing_purchase);
                } else {
                    showAlert('error', data.error);
                }

                button.innerHTML = originalText;
                button.disabled = false;
            } else if (data.session_url) {
                // Redirect to Stripe checkout
                window.location.href = data.session_url;
            } else {
                showAlert('error', 'Unexpected response from server');
                button.innerHTML = originalText;
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Payment error:', error);
            showAlert('error', 'Payment setup failed. Please try again.');
            button.innerHTML = originalText;
            button.disabled = false;
        });
    });

    function showAlert(type, message) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

        // Insert after the form
        const form = document.getElementById('paymentForm');
        form.insertAdjacentHTML('afterend', alertHtml);
    }

    function showDuplicatePurchaseError(message, purchaseInfo) {
        const alertHtml = `
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <h6><i class="fas fa-exclamation-triangle me-2"></i>Duplicate Purchase Detected</h6>
                <p class="mb-2">${message}</p>
                <hr>
                <small class="text-muted">
                    <strong>Purchase Date:</strong> ${purchaseInfo.purchase_date}<br>
                    <strong>Status:</strong> ${purchaseInfo.verification_status} / ${purchaseInfo.delivery_status}
                </small>
                ${purchaseInfo.purchase_uuid ? `
                    <div class="mt-2">
                        <a href="/purchase/${purchaseInfo.purchase_uuid}" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye me-1"></i>View Purchase Details
                        </a>
                    </div>
                ` : ''}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

        // Insert after the form
        const form = document.getElementById('paymentForm');
        form.insertAdjacentHTML('afterend', alertHtml);
    }

    function clearExistingAlerts() {
        document.querySelectorAll('.alert').forEach(alert => alert.remove());
    }
</script>
@endsection
