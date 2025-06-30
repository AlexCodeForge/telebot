@extends('layout')

@section('title', 'Welcome to Video Store')

@section('content')
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center">
                <div class="card shadow-lg">
                    <div class="card-body p-5">
                        <i class="fas fa-play-circle fa-5x text-primary mb-4"></i>
                        <h1 class="mb-4">Welcome to Video Store</h1>
                        <p class="lead text-muted mb-4">
                            Discover premium videos delivered instantly to your Telegram
                        </p>

                        <div class="row text-center mb-4">
                            <div class="col-md-4">
                                <i class="fas fa-lightning-bolt fa-2x text-warning mb-2"></i>
                                <h6>Instant Delivery</h6>
                                <small class="text-muted">Get your videos immediately</small>
                            </div>
                            <div class="col-md-4">
                                <i class="fas fa-shield-alt fa-2x text-success mb-2"></i>
                                <h6>Secure Payment</h6>
                                <small class="text-muted">Protected by Stripe</small>
                            </div>
                            <div class="col-md-4">
                                <i class="fas fa-hd-video fa-2x text-info mb-2"></i>
                                <h6>High Quality</h6>
                                <small class="text-muted">Premium content</small>
                            </div>
                        </div>

                        <a href="{{ route('videos.index') }}" class="btn btn-primary btn-lg">
                            <i class="fas fa-shopping-cart"></i> Browse Videos
                        </a>
                    </div>
                </div>
                </div>
        </div>
    </div>
@endsection
