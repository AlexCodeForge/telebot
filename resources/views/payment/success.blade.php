@extends('layout')

@section('title', 'Payment Successful!')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="fas fa-check-circle"></i> Payment Successful!</h4>
                </div>
                <div class="card-body text-center">
                    <!-- Success Icon -->
                    <div class="mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                    </div>

                    <h5 class="mb-3">Thank you for your purchase!</h5>
                    <p class="text-muted mb-4">Your purchase of "{{ $video->title }}" has been confirmed.</p>

                    <!-- Next Steps -->
                    <div class="card border-primary mb-4">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="fas fa-rocket"></i> Next Steps:</h6>
                        </div>
                        <div class="card-body text-start">
                            <div class="row align-items-center mb-3">
                                <div class="col-1">
                                    <span class="badge bg-primary rounded-pill">1</span>
                                </div>
                                <div class="col-11">
                                    <strong>Start a chat with our bot</strong><br>
                                    <small class="text-muted">Click the button below to open Telegram</small>
                                </div>
                            </div>
                            <div class="row align-items-center mb-3">
                                <div class="col-1">
                                    <span class="badge bg-primary rounded-pill">2</span>
                                </div>
                                <div class="col-11">
                                    <strong>Type /start in the chat</strong><br>
                                    <small class="text-muted">This will activate your purchase and deliver your
                                        video</small>
                                </div>
                            </div>
                            <div class="row align-items-center">
                                <div class="col-1">
                                    <span class="badge bg-primary rounded-pill">3</span>
                                </div>
                                <div class="col-11">
                                    <strong>Enjoy unlimited access</strong><br>
                                    <small class="text-muted">Use <code>/getvideo {{ $video->id }}</code> anytime to get
                                        your video again</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bot Link -->
                    <div class="mb-4">
                        <a href="https://t.me/videotestpowerbot" target="_blank" class="btn btn-success btn-lg">
                            <i class="fab fa-telegram"></i> Start Chat with Bot
                        </a>
                    </div>

                    <!-- Helpful Commands -->
                    <div class="card bg-light">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-robot"></i> Helpful Bot Commands:</h6>
                        </div>
                        <div class="card-body text-start">
                            <ul class="list-unstyled mb-0">
                                <li><code>/start</code> - Activate your purchase and get videos</li>
                                <li><code>/mypurchases</code> - See all your purchased videos</li>
                                <li><code>/getvideo {{ $video->id }}</code> - Get this specific video</li>
                                <li><code>/help</code> - Get help and see all commands</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Home Link -->
                    <div class="mt-4">
                        <a href="{{ route('videos.index') }}" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Back to Video Store
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
