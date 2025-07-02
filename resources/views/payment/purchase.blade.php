@extends('layout')

@section('title', 'Purchase Successful')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-check-circle me-2"></i>
                            Purchase Successful!
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- Purchase Status -->
                        <div class="alert alert-success">
                            <h5 class="alert-heading">
                                <i class="fas fa-shopping-cart me-2"></i>
                                Payment Confirmed
                            </h5>
                            <p class="mb-0">Your payment has been successfully processed. Your purchase details are below.
                            </p>
                        </div>

                        <!-- Video Information -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="fas fa-video me-2"></i>
                                            Video Details
                                        </h6>
                                        <h5>{{ $purchase->video->title }}</h5>
                                        @if ($purchase->video->description)
                                            <p class="text-muted">{{ $purchase->video->description }}</p>
                                        @endif
                                        <p class="h4 text-success">{{ $purchase->formatted_amount }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="fas fa-receipt me-2"></i>
                                            Purchase Information
                                        </h6>
                                        <p><strong>Purchase ID:</strong> {{ $purchase->purchase_uuid }}</p>
                                        <p><strong>Date:</strong> {{ $purchase->created_at->format('M d, Y H:i:s') }}</p>
                                        <p><strong>Status:</strong>
                                            <span class="badge bg-success">{{ ucfirst($purchase->purchase_status) }}</span>
                                        </p>
                                        @if ($purchase->customer_email)
                                            <p><strong>Email:</strong> {{ $purchase->customer_email }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Delivery Status -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="fas fa-truck me-2"></i>
                                    Delivery Status
                                </h6>

                                @if ($purchase->verification_status === 'pending')
                                    <div class="alert alert-warning">
                                        <h6 class="alert-heading">
                                            <i class="fas fa-clock me-2"></i>
                                            Waiting for Telegram Verification
                                        </h6>
                                        <p class="mb-2">To receive your video, please follow these steps:</p>
                                        <ol>
                                            <li>Open Telegram and search for our bot</li>
                                            <li>Send the command <code>/start</code> to the bot</li>
                                            @if ($purchase->telegram_username)
                                                <li>Make sure your Telegram username is:
                                                    <strong><span>@</span><span id="telegram-username-display">{{ $purchase->telegram_username }}</span></strong>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="editTelegramUsername()">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                </li>
                                            @endif
                                        </ol>
                                        <small class="text-muted">
                                            Once you start the bot with the same username you used during purchase,
                                            your video will be automatically delivered to you. This page will automatically
                                            refresh when your video is delivered.
                                        </small>

                                        <!-- Bot Conversation Button -->
                                        <div class="mt-3 text-center">
                                            <a href="{{ $bot['url'] }}?start=getvideo_{{ $purchase->video_id }}" target="_blank" class="btn btn-success btn-lg">
                                                <i class="fab fa-telegram me-2"></i>Get Your Video Now
                                            </a>
                                            <p class="text-muted mt-2 mb-0">
                                                <small><i class="fas fa-info-circle me-1"></i>Click this button to get your video via `/getvideo {{ $purchase->video_id }}` command!</small>
                                            </p>
                                        </div>
                                    </div>
                                @elseif($purchase->verification_status === 'verified')
                                    @if ($purchase->delivery_status === 'delivered')
                                        <div class="alert alert-success">
                                            <h6 class="alert-heading">
                                                <i class="fas fa-check-circle me-2"></i>
                                                Video Delivered!
                                            </h6>
                                            <p class="mb-1">Your video has been successfully delivered to your Telegram
                                                account.</p>
                                            <small class="text-muted">Delivered on:
                                                {{ $purchase->delivered_at->format('M d, Y H:i:s') }}</small>

                                            <!-- Bot Access Button -->
                                            <div class="mt-3">
                                                <a href="{{ $bot['url'] }}" target="_blank" class="btn btn-success">
                                                    <i class="fab fa-telegram me-2"></i>Open Bot Chat
                                                </a>
                                                <p class="text-muted mt-2 mb-0">
                                                    <small><i class="fas fa-video me-1"></i>Use <code>/getvideo {{ $purchase->video_id }}</code> anytime to get your video again!</small>
                                                </p>
                                            </div>
                                        </div>
                                    @elseif($purchase->delivery_status === 'pending')
                                        <div class="alert alert-info">
                                            <h6 class="alert-heading">
                                                <i class="fas fa-spinner fa-spin me-2"></i>
                                                Preparing Delivery
                                            </h6>
                                            <p class="mb-0">Your video is being prepared for delivery. You'll receive it
                                                shortly on Telegram.</p>
                                        </div>
                                    @elseif($purchase->delivery_status === 'failed')
                                        <div class="alert alert-danger">
                                            <h6 class="alert-heading">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                Delivery Issue
                                            </h6>
                                            <p class="mb-1">There was an issue delivering your video. Our team has been
                                                notified.</p>
                                            @if ($purchase->delivery_notes)
                                                <small class="text-muted">{{ $purchase->delivery_notes }}</small>
                                            @endif
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="text-center">
                            <a href="{{ route('videos.index') }}" class="btn btn-primary">
                                <i class="fas fa-arrow-left me-2"></i>
                                Browse More Videos
                            </a>
                        </div>

                        <!-- Support Information -->
                        <div class="mt-4 text-center">
                            <small class="text-muted">
                                Need help? Contact our support team with your Purchase ID:
                                <strong>{{ $purchase->purchase_uuid }}</strong>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Telegram Username Modal -->
    <div class="modal fade" id="editUsernameModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Telegram Username</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editUsernameForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="new_telegram_username" class="form-label">Telegram Username</label>
                            <div class="input-group">
                                <span class="input-group-text">@</span>
                                <input type="text" class="form-control" id="new_telegram_username"
                                       name="telegram_username" value="{{ $purchase->telegram_username }}" required>
                            </div>
                            <div class="form-text">Enter your correct Telegram username (without the @ symbol)</div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Important:</strong> Make sure this matches exactly with your Telegram username.
                            You'll need to contact our bot with this username to receive your video.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Username</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        // Auto-refresh every 30 seconds if verification is pending or delivery is pending
        @if (
            $purchase->verification_status === 'pending' ||
                ($purchase->verification_status === 'verified' && $purchase->delivery_status === 'pending'))
            setInterval(function() {
                window.location.reload();
            }, 30000);
        @endif

        // Edit telegram username functionality
        function editTelegramUsername() {
            new bootstrap.Modal(document.getElementById('editUsernameModal')).show();
        }

        // Handle username update form submission
        document.getElementById('editUsernameForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;

            // Show loading state
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
            submitButton.disabled = true;

            fetch(`/purchase/{{ $purchase->purchase_uuid }}/update-username`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the display
                    document.getElementById('telegram-username-display').textContent = data.username;

                    // Show success message
                    showAlert('success', 'Telegram username updated successfully!');

                    // Close modal
                    bootstrap.Modal.getInstance(document.getElementById('editUsernameModal')).hide();
                } else {
                    showAlert('error', data.message || 'Failed to update username');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'An error occurred while updating the username');
            })
            .finally(() => {
                // Reset button state
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;
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
