@extends('layout')

@section('title', 'Video Store')

@section('content')
    <div class="text-center mb-5">
        <h1><i class="fas fa-play-circle text-primary"></i> Video Store</h1>
        <p class="lead text-muted">Premium videos delivered instantly to your Telegram</p>
    </div>

    @if ($videos->count() > 0)
        <div class="row">
            @foreach ($videos as $video)
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title">{{ $video->title }}</h5>
                            <p class="card-text text-muted flex-grow-1">
                                {{ $video->description ?: 'High-quality video content' }}
                            </p>

                            <div class="mt-auto">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    @if ($video->isFree())
                                        <span class="h4 text-success mb-0">FREE</span>
                                    @else
                                        <span class="h4 text-primary mb-0">${{ $video->formatted_price }}</span>
                                    @endif

                                    @if ($video->telegram_file_id)
                                        <span class="badge bg-success">
                                            <i class="fas fa-check"></i> Ready
                                        </span>
                                    @else
                                        <span class="badge bg-warning">
                                            <i class="fas fa-clock"></i> Preparing
                                        </span>
                                    @endif
                                </div>

                                @if ($video->telegram_file_id)
                                    @if ($video->isFree())
                                        <a href="{{ route('videos.show', $video) }}" class="btn btn-success w-100">
                                            <i class="fas fa-download"></i> Get Free Video
                                        </a>
                                    @else
                                        <a href="{{ route('payment.form', $video) }}" class="btn btn-primary w-100">
                                            <i class="fas fa-shopping-cart"></i> Purchase Video
                                        </a>
                                    @endif
                                @else
                                    <button class="btn btn-secondary w-100" disabled>
                                        <i class="fas fa-hourglass-half"></i> Coming Soon
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($videos->hasPages())
            <div class="d-flex justify-content-center mt-4">
                {{ $videos->links() }}
            </div>
        @endif
    @else
        <div class="text-center py-5">
            <i class="fas fa-video fa-4x text-muted mb-3"></i>
            <h4 class="text-muted">No videos available yet</h4>
            <p class="text-muted">Check back soon for new video content!</p>
        </div>
    @endif
@endsection
