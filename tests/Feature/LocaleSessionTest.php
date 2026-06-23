<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_locale_switch_updates_session_only_not_user_record(): void
    {
        $user = User::factory()->create();
        $user->setLocalePreference('ar');

        $response = $this->actingAs($user)
            ->from('/user/dashboard')
            ->withSession(['locale' => 'ar'])
            ->get(route('locale.switch', 'en'));

        $response->assertRedirect('/user/dashboard');
        $response->assertSessionHas('locale', 'en');

        $user->refresh();
        $this->assertSame('ar', $user->getLocalePreference());
    }

    public function test_middleware_uses_session_locale_not_stored_user_preference(): void
    {
        $user = User::factory()->create();
        $user->setLocalePreference('ar');

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get('/login');

        $this->assertSame('en', app()->getLocale());
    }

    public function test_new_session_uses_app_default_not_user_preference(): void
    {
        $user = User::factory()->create();
        $user->setLocalePreference('ar');

        config(['app.locale' => 'en']);

        $this->actingAs($user)
            ->get('/login');

        $this->assertSame('en', app()->getLocale());
        $this->assertSame('en', session('locale'));
    }
}
