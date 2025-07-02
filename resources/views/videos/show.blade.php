@extends('layout')

@section('title', $video->title)

@section('content')
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-lg">
                    @if ($video->hasThumbnail())
                        <div class="position-relative" style="height: 300px;">
                            <img src="{{ $video->getThumbnailUrl() }}" class="card-img-top" alt="Video thumbnail"
                                style="height: 300px; object-fit: cover; {{ $video->shouldShowBlurred() ? $video->getBlurredThumbnailStyle() : '' }}{{ $video->allow_preview ? ' cursor: pointer;' : '' }}"
                                @if ($video->allow_preview) onclick="toggleThumbnailBlur(this, {{ $video->blur_intensity }})"
                                    title="Click to preview" @endif>
                            @if ($video->shouldShowBlurred())
                                <div class="position-absolute top-50 start-50 translate-middle">
                                    <div class="text-center text-white bg-dark bg-opacity-75 px-4 py-3 rounded">
                                        <i class="fas fa-lock fa-3x mb-3"></i>
                                        <div class="h6">Thumbnail Preview</div>
                                        <div class="small">
                                            @if ($video->allow_preview)
                                                Click to preview •
                                            @endif
                                            Purchase to see full video
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="card-body p-5">
                        <!-- Video Info -->
                        <div class="text-center mb-4">
                            @if (!$video->hasThumbnail())
                                <i class="fas fa-play-circle fa-4x text-primary mb-3"></i>
                            @endif
                            <h2>{{ $video->title }}</h2>
                            @if ($video->description)
                                <p class="text-muted lead">{{ $video->description }}</p>
                            @endif

                            @if ($video->duration)
                                <div class="mb-3">
                                    <span class="badge bg-info fs-6">
                                        <i class="fas fa-clock"></i> Duration: {{ gmdate('i:s', $video->duration) }}
                                    </span>
                                </div>
                            @endif

                            <div class="price-display mb-4">
                                @if ($video->isFree())
                                    <span class="h1 text-success">FREE</span>
                                @else
                                    <span class="h1 text-primary">${{ number_format($video->price, 2) }}</span>
                                @endif
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="text-center mb-4">
                            @if ($video->telegram_file_id)
                                @if ($video->isFree())
                                    <div class="alert alert-success">
                                        <h5><i class="fas fa-gift"></i> This video is FREE!</h5>
                                        <p class="mb-2">🤖 <strong>Get instant access via our Telegram bot:</strong></p>
                                        <ol class="mb-3">
                                            <li>Start a conversation with our bot: <a href="https://t.me/videotestpowerbot" target="_blank" class="btn btn-sm btn-primary">@videotestpowerbot</a></li>
                                            <li>Send the command: <code>/getvideo {{ $video->id }}</code></li>
                                            <li>Get your free video instantly!</li>
                                        </ol>
                                        <p class="mb-0"><small class="text-muted">No purchase required - available to all users!</small></p>
                                    </div>
                                    <div class="text-center">
                                        <a href="https://t.me/videotestpowerbot" target="_blank" class="btn btn-success btn-lg mb-3">
                                            <i class="fab fa-telegram"></i> Get Free Video Now
                                        </a>
                                        <br>
                                        <a href="{{ route('videos.index') }}" class="btn btn-outline-primary">
                                            <i class="fas fa-arrow-left"></i> Back to Store
                                        </a>
                                    </div>
                                @else
                                    <a href="{{ route('payment.form', $video) }}" class="btn btn-success btn-lg mb-3">
                                        <i class="fas fa-shopping-cart"></i> Purchase Now
                                    </a>
                                    <br>
                                    <a href="{{ route('videos.index') }}" class="btn btn-outline-primary">
                                        <i class="fas fa-arrow-left"></i> Back to Store
                                    </a>
                                @endif
                            @else
                                <div class="alert alert-warning">
                                    <i class="fas fa-hourglass-half"></i> This video is being prepared and will be available
                                    soon.
                                </div>
                                <a href="{{ route('videos.index') }}" class="btn btn-outline-primary">
                                    <i class="fas fa-arrow-left"></i> Back to Store
                                </a>
                            @endif
                        </div>

                        <!-- Features -->
                        <div class="row text-center">
                            <div class="col-md-4">
                                <i class="fas fa-lightning-bolt fa-2x text-warning mb-2"></i>
                                <h6>Instant Delivery</h6>
                                <small class="text-muted">Delivered to your Telegram immediately</small>
                            </div>
                            <div class="col-md-4">
                                <i class="fas fa-shield-alt fa-2x text-success mb-2"></i>
                                <h6>Secure Payment</h6>
                                <small class="text-muted">Protected by Stripe</small>
                            </div>
                            <div class="col-md-4">
                                <i class="fas fa-hd-video fa-2x text-info mb-2"></i>
                                <h6>High Quality</h6>
                                <small class="text-muted">Premium video content</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        let previewActive = false;

        function toggleThumbnailBlur(img, blurIntensity) {
            if (previewActive) {
                // Return to blurred state
                img.style.filter = `blur(${blurIntensity}px)`;
                previewActive = false;
                img.title = "Click to preview";
            } else {
                // Show unblurred preview
                img.style.filter = 'none';
                previewActive = true;
                img.title = "Click to hide preview";

                // Auto-hide after 3 seconds
                setTimeout(() => {
                    if (previewActive) {
                        img.style.filter = `blur(${blurIntensity}px)`;
                        previewActive = false;
                        img.title = "Click to preview";
                    }
                }, 3000);
            }
        }
    </script>
@endsection
