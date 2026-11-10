<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Leave;
use App\Models\Password;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmationDialogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_credential_lists_render_delete_forms_and_preserve_the_client_edit_link(): void
    {
        $client = Client::create(['name' => 'Acme & Co']);
        $password = Password::create([
            'client_id' => $client->id,
            'name' => 'Email login',
            'username' => 'user@example.com',
            'password' => 'example-password',
        ]);
        $this->actingAs(User::factory()->create());

        $this->get(route('clients.index'))->assertOk()
            ->assertSee('Delete Acme &amp; Co?', false)
            ->assertSee('action="'.route('clients.destroy', $client).'"', false)
            ->assertSee('role="dialog"', false);

        $this->get(route('passwords.index', ['client_id' => $client->id]))->assertOk()
            ->assertSee('action="'.route('passwords.destroy', $password).'"', false)
            ->assertSee('Delete Email login?')
            ->assertSee(route('passwords.edit', ['password' => $password, 'client_id' => $client->id]), false);
    }

    public function test_pending_leave_renders_its_own_confirmation_and_delete_form(): void
    {
        $user = User::factory()->create();
        $leave = Leave::create([
            'user_id' => $user->id,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-01',
            'reason' => 'Annual leave',
            'is_half_day' => false,
        ]);

        $this->actingAs($user)->get(route('leave.view'))->assertOk()
            ->assertSee('cancel-leave-'.$leave->id.'-title')
            ->assertSee('action="'.route('leave.delete', $leave).'"', false)
            ->assertSee('Cancel request')->assertSee('Keep request')
            ->assertDontSee('form.submit()', false);
    }
}
