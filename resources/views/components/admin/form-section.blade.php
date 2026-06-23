@props([
    'title',
    'icon' => 'fas fa-layer-group',
    'description' => null,
])

<section class="admin-form-section">
    <header class="admin-form-section__header">
        <span class="admin-form-section__icon" aria-hidden="true">
            <i class="{{ $icon }}"></i>
        </span>
        <div>
            <h2 class="admin-form-section__title">{{ $title }}</h2>
            @if($description)
                <p class="admin-form-section__desc">{{ $description }}</p>
            @endif
        </div>
    </header>
    <div class="admin-form-section__body">
        <div class="row g-3">
            {{ $slot }}
        </div>
    </div>
</section>
