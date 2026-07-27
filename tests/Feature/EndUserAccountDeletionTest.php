<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Models\AccountDeletionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndUserAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_end_user_can_open_delete_account_page(): void
    {
        $user = $this->makeEndUser();

        $this->actingAs($user)
            ->get(route('user.account.delete'))
            ->assertOk()
            ->assertSee(__('app.user.account_delete.title'), false);
    }

    public function test_admin_cannot_use_end_user_account_deletion(): void
    {
        $user = User::factory()->create();
        $user->role = AppRole::Admin->value;
        $user->save();

        $this->actingAs($user)
            ->get(route('user.account.delete'))
            ->assertForbidden();
    }

    public function test_sub_account_cannot_use_end_user_account_deletion(): void
    {
        $parent = $this->makeEndUser();
        $child = User::factory()->create([
            'email' => 'subaccount-delete@example.com',
        ]);
        $child->role = AppRole::EndUser->value;
        $child->save();

        \App\Models\SubAccount::query()->create([
            'parent_user_id' => $parent->id,
            'user_id' => $child->id,
        ]);

        $this->actingAs($child->fresh())
            ->get(route('user.account.delete'))
            ->assertForbidden();
    }

    public function test_end_user_submits_deletion_request_instead_of_deleting(): void
    {
        $user = $this->makeEndUser();

        $this->actingAs($user)
            ->post(route('user.account.delete.verify'), [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('user.account.delete.confirm'));

        $this->actingAs($user)
            ->delete(route('user.account.delete.destroy'), [
                'confirmation' => 'delete',
                'reason' => 'No longer needed',
            ])
            ->assertRedirect(route('user.profile'))
            ->assertSessionHas('success');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh());
        $this->assertDatabaseHas('account_deletion_requests', [
            'user_id' => $user->id,
            'email' => strtolower($user->email),
            'status' => AccountDeletionRequest::STATUS_PENDING,
            'source' => AccountDeletionRequest::SOURCE_AUTHENTICATED,
        ]);
    }

    public function test_public_can_submit_deletion_request_without_login(): void
    {
        $this->post(route('account.deletion-request.submit'), [
            'name' => 'Guest User',
            'username' => 'guestuser',
            'email' => 'guest-delete@example.com',
            'phone' => '+966500000000',
            'reason' => 'Please delete my account',
            'form_started_at' => now()->subSeconds(10)->timestamp,
        ])->assertRedirect(route('account.deletion-request'));

        $this->assertDatabaseHas('account_deletion_requests', [
            'email' => 'guest-delete@example.com',
            'status' => AccountDeletionRequest::STATUS_PENDING,
            'source' => AccountDeletionRequest::SOURCE_PUBLIC,
        ]);
    }

    private function makeEndUser(): User
    {
        $user = User::factory()->create([
            'email' => 'enduser-delete@example.com',
        ]);
        $user->role = AppRole::EndUser->value;
        $user->save();

        return $user->fresh();
    }
}
