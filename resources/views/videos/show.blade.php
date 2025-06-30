@extends('layout')

@section('title', $video->title)

@section('content')
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-lg">
                    <div class="card-body p-5">
                        <!-- Video Info -->
                        <div class="text-center mb-4">
                            <i class="fas fa-play-circle fa-4x text-primary mb-3"></i>
                            <h2>{{ $video->title }}</h2>
                            @if ($video->description)
                                <p class="text-muted lead">{{ $video->description }}</p>
                            @endif

                            <div class="price-display mb-4">
                                @if ($video->isFree())
                                    <span class="h1 text-success">FREE</span>
                                @else
                                    <span class="h1 text-primary">${{ $video->formatted_price }}</span>
                                @endif
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="text-center mb-4">
                            @if ($video->telegram_file_id)
                                @if ($video->isFree())
                                    <div class="alert alert-info">
                                        <i class="fas fa-gift"></i> This video is free! Contact us to get access.
                                    </div>
                                    <a href="{{ route('videos.index') }}" class="btn btn-outline-primary">
                                        <i class="fas fa-arrow-left"></i> Back to Store
                                    </a>
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
