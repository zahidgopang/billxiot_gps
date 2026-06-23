<x-admin.form-section
    :title="__('app.forms.basic_information')"
    icon="fas fa-building"
    :description="__('app.forms.client_basic_hint')"
>
    <x-admin.form-col>
        <label class="admin-label" for="client-name">{{ __('app.forms.name') }}</label>
        <input type="text" name="name" id="client-name" class="form-control form-control-sm" value="{{ old('name', $client->name ?? '') }}" required>
        @error('name') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="client-slug">{{ __('app.forms.slug') }}</label>
        <input type="text" name="slug" id="client-slug" class="form-control form-control-sm admin-ltr" dir="ltr" value="{{ old('slug', $client->slug ?? '') }}">
        <p class="admin-hint">{{ __('app.forms.slug_hint') }}</p>
        @error('slug') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="client-status">{{ __('app.common.status') }}</label>
        <select name="status" id="client-status" class="form-select form-select-sm" data-search="false">
            @php $st = old('status', $client->status ?? 'active'); @endphp
            <option value="active" @selected($st === 'active')>{{ __('app.common.active') }}</option>
            <option value="inactive" @selected($st === 'inactive')>{{ __('app.common.inactive') }}</option>
        </select>
        @error('status') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>
</x-admin.form-section>

<x-admin.form-section
    :title="__('app.forms.map_access')"
    icon="fas fa-map-location-dot"
    :description="__('app.forms.map_access_hint')"
>
    <x-admin.form-col :full="true">
        @php $canTrack = old('can_track_maps', $client->can_track_maps ?? false); @endphp
        <div class="form-check form-switch">
            <input type="hidden" name="can_track_maps" value="0">
            <input class="form-check-input" type="checkbox" name="can_track_maps" id="client-can-track-maps" value="1"
                   @checked($canTrack)>
            <label class="form-check-label admin-label mb-0" for="client-can-track-maps">
                {{ __('app.forms.can_track_maps_label') }}
            </label>
        </div>
        <p class="admin-hint mt-2">{{ __('app.forms.can_track_maps_hint') }}</p>
        @error('can_track_maps') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>
</x-admin.form-section>

@if(isset($admins, $client) && $client?->exists && auth()->user()->isSuperAdmin())
    <x-admin.form-section
        :title="__('app.forms.permissions')"
        icon="fas fa-user-shield"
        :description="__('app.forms.permissions_hint')"
    >
        <x-admin.form-col :full="true">
            <label class="admin-label" for="client-admin-ids">{{ __('app.forms.assigned_admins') }}</label>
            <select name="admin_ids[]" id="client-admin-ids" class="form-select form-select-sm" multiple data-search="true">
                @php $assigned = $client->adminScopes()->pluck('admin_user_id')->all(); @endphp
                @foreach($admins as $admin)
                    <option value="{{ $admin->id }}" @selected(in_array($admin->id, $assigned, true))>
                        {{ $admin->name }} ({{ $admin->email }}) — {{ $admin->role }}
                    </option>
                @endforeach
            </select>
            <p class="admin-hint">{{ __('app.forms.assigned_admins_hint') }}</p>
            @error('admin_ids') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
            @error('admin_ids.*') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
        </x-admin.form-col>
    </x-admin.form-section>
@endif
