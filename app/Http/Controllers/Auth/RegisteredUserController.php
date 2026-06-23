<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BotProtectionService;
use App\Services\RecaptchaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'recaptchaEnabled' => RecaptchaService::isEnabled(),
            'recaptchaSiteKey' => config('captcha.sitekey'),
        ]);
    }

    public function store(Request $request, BotProtectionService $bots)
    {
        Log::info('Registration attempt started', [
            'email' => $request->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $guard = $bots->inspect($request, 'register', 'register');

        if (! $guard['ok']) {
            if (! empty($guard['fake_success'])) {
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Registration successful!',
                        'redirect' => route('dashboard'),
                    ]);
                }

                return redirect()->route('login')->with('success', 'Registration successful! Please sign in.');
            }

            $message = $guard['message'] ?? 'Security check failed. Please try again.';

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'errors' => ['g-recaptcha-response' => [$message]],
                ], $guard['status'] ?? 422);
            }

            return back()->withErrors([
                'g-recaptcha-response' => $message,
            ])->withInput();
        }

        $recaptcha = $guard['recaptcha'] ?? ['ok' => true];

        $bots->recordAttempt($request, 'register');

        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:tc_users,email'],
                'country_code' => ['required', 'string', 'max:6'],
                'phone' => ['required', 'string', 'min:7', 'max:20'],
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
                'terms' => ['required', 'accepted'],
            ]);

            Log::info('Form validation passed', ['email' => $validated['email']]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Form validation failed', [
                'errors' => $e->errors(),
                'email' => $request->email,
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                    'message' => 'Please fix the validation errors',
                ], 422);
            }

            throw $e;
        }

        try {
            $user = new User([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'country_code' => $validated['country_code'],
                'phone' => preg_replace('/\D/', '', $validated['phone']),
            ]);
            $user->password = $validated['password'];
            $user->setTraccarPlainPasswordForNextSave($validated['password']);
            $user->save();

            Log::info('User created successfully', ['user_id' => $user->id, 'email' => $user->email]);
        } catch (\Exception $e) {
            Log::error('User creation failed', [
                'error' => $e->getMessage(),
                'email' => $validated['email'],
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create user account. Please try again.',
                ], 500);
            }

            return back()->withErrors([
                'email' => 'Failed to create account. Please try again.',
            ])->withInput();
        }

        if (function_exists('activity')) {
            activity()
                ->causedBy($user)
                ->withProperties([
                    'type' => 'registration_success',
                    'recaptcha_score' => $recaptcha['score'] ?? null,
                    'recaptcha_skipped' => ! empty($recaptcha['skipped']),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ])
                ->log('New user registered');
        }

        Auth::login($user);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Registration successful!',
                'redirect' => route('dashboard'),
                'user_email' => $user->email,
            ]);
        }

        return redirect()->route('dashboard')
            ->with('success', 'Registration successful! Welcome aboard.');
    }
}
