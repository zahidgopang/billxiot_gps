@extends('admin.layouts.app')

@section('title', 'Clients')
@section('page-title', 'Clients')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <form method="GET" class="admin-filter-bar d-flex flex-wrap gap-2 align-items-end">
            <div class="flex-grow-1" style="min-width: 12rem; max-width: 20rem;">
                <label class="form-label small mb-1" for="clients-filter-q">{{ __('app.common.search') }}</label>
                <input type="text" name="q" id="clients-filter-q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('app.common.search') }}…">
            </div>
            <div class="admin-filter-actions">
                <button type="submit" class="btn btn-sm btn-primary">{{ __('app.common.filter') }}</button>
                @if(request()->filled('q'))
                    <a href="{{ route('admin.clients.index') }}" class="btn btn-sm btn-outline-secondary" title="{{ __('app.common.clear') }}">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </a>
                @endif
            </div>
        </form>
        @can('permission', 'clients.manage')
            <a href="{{ route('admin.clients.create') }}" class="btn btn-sm btn-primary">New client</a>
        @endcan
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>Status</th>
                <th>{{ __('app.forms.map_access') }}</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($clients as $client)
                <tr>
                    <td>{{ $client->name }}</td>
                    <td><code>{{ $client->slug }}</code></td>
                    <td><span class="badge bg-{{ $client->status === 'active' ? 'success' : 'secondary' }}">{{ $client->status }}</span></td>
                    <td>
                        @if($client->allowsMapTracking())
                            <span class="badge bg-info text-dark">{{ __('app.forms.map_tracking_enabled') }}</span>
                        @else
                            <span class="badge bg-secondary">{{ __('app.forms.map_tracking_off') }}</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @can('manage-client', $client)
                            <a href="{{ route('admin.clients.edit', $client) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted text-center py-4">No clients yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $clients->links() }}
@endsection
