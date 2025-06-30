@extends('layout')

@section('title', 'Payment Cancelled')

@section('content')
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h4 class="mb-0"><i class="fas fa-times-circle"></i> Payment Cancelled</h4>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-4">
                            <i class="fas fa-times-circle text-warning" style="font-size: 4rem;"></i>
                        </div>

                        <h5 class="mb-3">Your payment was cancelled</h5>

                        <div class="alert alert-info">
                            <h6><i class="fas fa-video"></i> {{ $video->title }}</h6>
                            <p class="mb-0">{{ $video->description }}</p>
                            <p class="mt-2 mb-0"><strong>Price: ${{ number_format($video->price, 2) }}</strong></p>
                        </div>

                        <p class="text-muted mb-4">
                            No charges have been made to your account. You can try purchasing again or browse other videos.
                        </p>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                            <a href="{{ route('payment.form', $video) }}" class="btn btn-success">
                                <i class="fas fa-credit-card"></i> Try Again
                            </a>
                            <a href="{{ route('videos.index') }}" class="btn btn-outline-primary">
                                <i class="fas fa-arrow-left"></i> Back to Videos
                            </a>
                        </div>
                    </div>
                    <div class="card-footer text-muted text-center">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            Need help? Contact our support team for assistance.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
