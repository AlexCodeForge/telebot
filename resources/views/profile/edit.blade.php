@extends('layout')

@section('title', 'Profile Settings')

@section('content')
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="fas fa-user-cog"></i> Profile Settings
            </h1>
        </div>
    </div>

    <div class="row">
        <!-- Profile Information -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user"></i> Profile Information
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Update your account's profile information and email address.</p>

                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        @method('PATCH')

                        <!-- Name -->
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input id="name" type="text" class="form-control @error('name') is-invalid @enderror"
                                name="name" value="{{ old('name', $user->name) }}" required autofocus>
                            @error('name')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                                name="email" value="{{ old('email', $user->email) }}" required>
                            @error('email')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail())
                                <div class="mt-2">
                                    <p class="text-muted small">
                                        Your email address is unverified.
                                        <button form="send-verification" class="btn btn-link p-0 text-decoration-underline">
                                            Click here to re-send the verification email.
                                        </button>
                                    </p>

                                    @if (session('status') === 'verification-link-sent')
                                        <p class="text-success small">
                                            A new verification link has been sent to your email address.
                                        </p>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save
                            </button>

                            @if (session('status') === 'profile-updated')
                                <span class="text-success small align-self-center">
                                    <i class="fas fa-check"></i> Saved.
                                </span>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Update Password -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-lock"></i> Update Password
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Ensure your account is using a long, random password to stay secure.</p>

                    <form method="POST" action="{{ route('password.update') }}">
                        @csrf
                        @method('PUT')

                        <!-- Current Password -->
                        <div class="mb-3">
                            <label for="update_password_current_password" class="form-label">Current Password</label>
                            <input id="update_password_current_password" type="password"
                                class="form-control @error('current_password', 'updatePassword') is-invalid @enderror"
                                name="current_password" autocomplete="current-password">
                            @error('current_password', 'updatePassword')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <!-- New Password -->
                        <div class="mb-3">
                            <label for="update_password_password" class="form-label">New Password</label>
                            <input id="update_password_password" type="password"
                                class="form-control @error('password', 'updatePassword') is-invalid @enderror"
                                name="password" autocomplete="new-password">
                            @error('password', 'updatePassword')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <!-- Confirm Password -->
                        <div class="mb-3">
                            <label for="update_password_password_confirmation" class="form-label">Confirm Password</label>
                            <input id="update_password_password_confirmation" type="password"
                                class="form-control @error('password_confirmation', 'updatePassword') is-invalid @enderror"
                                name="password_confirmation" autocomplete="new-password">
                            @error('password_confirmation', 'updatePassword')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save
                            </button>

                            @if (session('status') === 'password-updated')
                                <span class="text-success small align-self-center">
                                    <i class="fas fa-check"></i> Saved.
                                </span>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Email Verification Form (Hidden) -->
    @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail())
        <form id="send-verification" method="POST" action="{{ route('verification.send') }}" style="display: none;">
            @csrf
        </form>
    @endif

@endsection
