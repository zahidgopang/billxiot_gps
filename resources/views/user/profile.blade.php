@extends('user.layout_user')

@section('title', 'My Profile — BillX GPS')

@push('styles')
    <style>
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: 600;
            margin-inline-end: 1.5rem;
            box-shadow: 0 8px 25px rgba(25, 118, 210, 0.3);
            overflow: hidden;
            flex-shrink: 0;
        }

        .profile-avatar img,
        .profile-avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .avatar-upload-box {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            border: 1px dashed rgba(25, 118, 210, 0.25);
            border-radius: 14px;
            background: rgba(25, 118, 210, 0.03);
        }

        .avatar-upload-preview {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.75rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .avatar-upload-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info h4 {
            color: var(--text-primary);
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .profile-info p {
            color: var(--text-secondary);
            margin-bottom: 0;
        }

        .profile-card {
            background: #fff;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .profile-card:hover {
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section h5 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid rgba(25, 118, 210, 0.1);
            display: flex;
            align-items: center;
        }

        .form-section h5 i {
            margin-inline-end: 0.75rem;
            color: var(--primary-blue);
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .form-control-premium {
            background: #fff;
            border: 2px solid rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .form-control-premium:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
            background: #fff;
        }

        .form-control-premium:disabled {
            background-color: #f8f9fa;
            opacity: 0.7;
            cursor: not-allowed;
        }

        .info-row {
            display: flex;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            width: 180px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .info-value {
            flex: 1;
            color: var(--text-secondary);
        }

        .info-value .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
        }

        .activity-card {
            background: #fff;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .activity-card:hover {
            border-color: rgba(25, 118, 210, 0.2);
            transform: translateY(-1px);
        }

        .activity-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(25, 118, 210, 0.1);
            color: var(--primary-blue);
            font-size: 1.25rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-avatar {
                margin-inline-end: 0;
                margin-bottom: 1rem;
            }

            .profile-card {
                padding: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .profile-card {
                padding: 1.25rem;
            }
        }
    </style>
@endpush

@section('content')

    <div class="container dashboard-container">

        <!-- Header with Profile Info -->
        <div class="profile-header">
            @include('partials.user-avatar', [
                'user' => $user,
                'size' => 80,
                'class' => 'profile-avatar',
                'style' => 'margin-inline-end: 1.5rem;',
            ])
            <div class="profile-info">
                <h4>{{ $user->name }}</h4>
                <p class="mb-2">{{ $user->email }}</p>
                @php
                    $roleLabel = match($user->role ?? 'user') {
                        'admin' => ['Admin', 'bg-danger'],
                        default => ['Fleet User', 'bg-primary'],
                    };
                @endphp
                <span class="badge {{ $roleLabel[1] }} me-1">
                    <i class="fas fa-user me-1"></i>{{ $roleLabel[0] }}
                </span>
                @if($user->email_verified_at)
                    <span class="badge bg-success">
                        <i class="fas fa-check-circle me-1"></i> Verified
                    </span>
                @else
                    <span class="badge bg-warning text-dark">
                        <i class="fas fa-envelope me-1"></i> Verify email
                    </span>
                @endif
            </div>
        </div>

        <!-- Profile Update Form -->
        <div class="profile-card mb-4">
            <!-- Success Message -->
            @if(session('success'))
                <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            <!-- Error Message -->
            @if($errors->any())
                <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <div>
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Personal Information -->
            <div class="form-section">
                <h5><i class="fas fa-user-edit"></i> Personal Information</h5>
                <form method="POST" action="{{ route('user.profile.update') }}" id="profileForm" enctype="multipart/form-data">
                    @csrf
                    <!-- Removed @method('PUT') since route only accepts POST -->

                    <div class="avatar-upload-box">
                        <div class="avatar-upload-preview" id="avatarPreview">
                            @if ($user->avatarUrl())
                                <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}">
                            @else
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            @endif
                        </div>
                        <div class="flex-grow-1">
                            <label class="form-label mb-1">Profile picture</label>
                            <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/webp,image/gif"
                                   class="form-control form-control-premium">
                            <small class="text-muted d-block mt-1">JPG, PNG, WebP or GIF. Max 2 MB. Visible in web and mobile app.</small>
                            @if ($user->avatarUrl())
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="remove_avatar" value="1" id="removeAvatar">
                                    <label class="form-check-label" for="removeAvatar">Remove current photo</label>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" value="{{ old('name', $user->name) }}"
                                   class="form-control form-control-premium @error('name') is-invalid @enderror">
                            @error('name')
                            <div class="invalid-feedback d-flex align-items-center">
                                <i class="fas fa-exclamation-circle me-2"></i> {{ $message }}
                            </div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" value="{{ old('email', $user->email) }}"
                                   class="form-control form-control-premium @error('email') is-invalid @enderror">
                            @error('email')
                            <div class="invalid-feedback d-flex align-items-center">
                                <i class="fas fa-exclamation-circle me-2"></i> {{ $message }}
                            </div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" value="{{ old('phone', $user->phone ?? '') }}"
                                   class="form-control form-control-premium @error('phone') is-invalid @enderror"
                                   placeholder="+1 234 567 8900">
                            @error('phone')
                            <div class="invalid-feedback d-flex align-items-center">
                                <i class="fas fa-exclamation-circle me-2"></i> {{ $message }}
                            </div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Country code</label>
                            <input type="text" name="country_code" value="{{ old('country_code', $user->country_code ?? '') }}"
                                   class="form-control form-control-premium" placeholder="+92" maxlength="5">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-outline-premium" id="cancelEdit" style="display: none;">
                            <i class="fas fa-times me-2"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-premium">
                            <i class="fas fa-save me-2"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Form validation and enhancement
            const profileForm = document.getElementById('profileForm');
            const cancelEditBtn = document.getElementById('cancelEdit');

            // Track form changes
            let originalData = {};
            const formInputs = profileForm.querySelectorAll('input, select');

            formInputs.forEach(input => {
                originalData[input.name] = input.value;

                input.addEventListener('input', function() {
                    const currentData = {};
                    formInputs.forEach(inp => {
                        currentData[inp.name] = inp.value;
                    });

                    // Show/hide cancel button based on changes
                    const hasChanges = JSON.stringify(currentData) !== JSON.stringify(originalData);
                    cancelEditBtn.style.display = hasChanges ? 'block' : 'none';
                });
            });

            // Cancel edit functionality
            cancelEditBtn.addEventListener('click', function() {
                formInputs.forEach(input => {
                    if (originalData[input.name] !== undefined) {
                        input.value = originalData[input.name];
                    }
                });
                this.style.display = 'none';
            });

            // Form submission handling
            profileForm.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;

                // Show loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
                submitBtn.disabled = true;

                // Don't prevent form submission - let it submit normally
                // The form will handle the submission
            });

            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Phone number formatting
            const phoneInput = document.querySelector('input[name="phone"]');
            if (phoneInput) {
                phoneInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 0) {
                        value = '+' + value;
                    }
                    e.target.value = value;
                });
            }

            const avatarInput = document.getElementById('avatarInput');
            const avatarPreview = document.getElementById('avatarPreview');
            if (avatarInput && avatarPreview) {
                avatarInput.addEventListener('change', function () {
                    const file = this.files && this.files[0];
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = function (event) {
                        avatarPreview.innerHTML = '<img src="' + event.target.result + '" alt="Preview">';
                    };
                    reader.readAsDataURL(file);
                    const removeCheckbox = document.getElementById('removeAvatar');
                    if (removeCheckbox) removeCheckbox.checked = false;
                });
            }
        });
    </script>
@endpush
