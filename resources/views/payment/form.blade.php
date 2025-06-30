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
                    <form action="{{ route('payment.process', $video) }}" method="POST">
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
                            <button type="submit" class="btn btn-success btn-lg">
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
