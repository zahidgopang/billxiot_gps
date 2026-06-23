@extends('admin.layouts.app')
@section('title', __('app.admin.nav.my_profile'))
@section('page-title', __('app.admin.nav.my_profile'))
@section('page-icon', 'user-circle')

@section('content')
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header fw-semibold">
                    <i class="fas fa-user me-2 text-primary"></i>{{ __('app.forms.basic_information') }}
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">{{ __('app.profile.update_info_hint') }}</p>

                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        @method('patch')

                        <div class="mb-3">
                            <label class="admin-label" for="profile-name">{{ __('app.forms.name') }}</label>
                            <input type="text" name="name" id="profile-name" class="form-control form-control-sm"
                                   value="{{ old('name', $user->name) }}" required autocomplete="name">
                            @error('name') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="admin-label" for="profile-email">{{ __('app.forms.email') }}</label>
                            <input type="email" name="email" id="profile-email" class="form-control form-control-sm admin-ltr" dir="ltr"
                                   value="{{ old('email', $user->email) }}" required autocomplete="username">
                            @error('email') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
                        </div>

                        @if($user->phone)
                            <p class="small text-muted mb-3">
                                <strong>{{ __('app.forms.phone') }}:</strong> {{ $user->phone }}
                            </p>
                        @endif

                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-save me-1"></i>{{ __('app.common.save') }}
                        </button>

                        @if(session('status') === 'profile-updated')
                            <span class="text-success small ms-2">{{ __('app.profile.saved') }}</span>
                        @endif
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header fw-semibold">
                    <i class="fas fa-key me-2 text-primary"></i>{{ __('app.profile.change_password') }}
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">{{ __('app.profile.change_password_hint') }}</p>

                    <form method="POST" action="{{ route('password.update') }}">
                        @csrf
                        @method('put')

                        <div class="mb-3">
                            <label class="admin-label" for="current_password">{{ __('app.profile.current_password') }}</label>
                            <input type="password" name="current_password" id="current_password"
                                   class="form-control form-control-sm" required autocomplete="current-password">
                            @error('current_password', 'updatePassword')
                                <p class="admin-field__error text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="admin-label" for="password">{{ __('app.profile.new_password') }}</label>
                            <input type="password" name="password" id="password"
                                   class="form-control form-control-sm" required autocomplete="new-password">
                            @error('password', 'updatePassword')
                                <p class="admin-field__error text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="admin-label" for="password_confirmation">{{ __('app.profile.confirm_password') }}</label>
                            <input type="password" name="password_confirmation" id="password_confirmation"
                                   class="form-control form-control-sm" required autocomplete="new-password">
                            @error('password_confirmation', 'updatePassword')
                                <p class="admin-field__error text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-lock me-1"></i>{{ __('app.profile.update_password') }}
                        </button>

                        @if(session('status') === 'password-updated')
                            <span class="text-success small ms-2">{{ __('app.profile.password_updated') }}</span>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
