@extends('frontend.layout')

@section('title', __('app.public_account_deletion.title') . ' — ' . __('app.brand'))
@section('description', __('app.public_account_deletion.subtitle'))

@section('content')
<section class="relative min-h-[70vh] py-16">
    <div class="absolute inset-0 bg-gradient-to-br from-slate-50 via-rose-50 to-orange-50 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950"></div>
    <div class="relative max-w-xl mx-auto px-4 sm:px-6">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                {{ __('app.public_account_deletion.title') }}
            </h1>
            <p class="text-slate-600 dark:text-slate-300">
                {{ __('app.public_account_deletion.subtitle') }}
            </p>
        </div>

        @if(session('status'))
            <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xl border border-slate-200 dark:border-slate-700 p-6 sm:p-8">
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">
                {{ __('app.public_account_deletion.notice') }}
            </p>

            <form method="POST" action="{{ route('account.deletion-request.submit') }}" autocomplete="off">
                @csrf
                <input type="hidden" name="form_started_at" value="{{ now()->timestamp }}">
                <div class="hidden" aria-hidden="true">
                    <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                    <label>URL<input type="text" name="url" tabindex="-1" autocomplete="off"></label>
                    <label>Honeypot<input type="text" name="honeypot" tabindex="-1" autocomplete="off"></label>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-1" for="delName">{{ __('app.public_account_deletion.name') }}</label>
                    <input id="delName" type="text" name="name" value="{{ old('name') }}" required
                           class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2.5">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-1" for="delUsername">{{ __('app.public_account_deletion.username') }}</label>
                    <input id="delUsername" type="text" name="username" value="{{ old('username') }}"
                           class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2.5"
                           placeholder="{{ __('app.public_account_deletion.username_hint') }}">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-1" for="delEmail">{{ __('app.public_account_deletion.email') }}</label>
                    <input id="delEmail" type="email" name="email" value="{{ old('email') }}" required
                           class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2.5">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-1" for="delPhone">{{ __('app.public_account_deletion.phone') }}</label>
                    <input id="delPhone" type="tel" name="phone" value="{{ old('phone') }}" required
                           class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2.5">
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-semibold mb-1" for="delReason">{{ __('app.public_account_deletion.reason') }}</label>
                    <textarea id="delReason" name="reason" rows="4"
                              class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2.5"
                              placeholder="{{ __('app.public_account_deletion.reason_hint') }}">{{ old('reason') }}</textarea>
                </div>

                <button type="submit"
                        class="w-full rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-semibold py-3 transition">
                    {{ __('app.public_account_deletion.submit') }}
                </button>
            </form>
        </div>
    </div>
</section>
@endsection
