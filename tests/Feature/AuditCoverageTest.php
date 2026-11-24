<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Password;
use App\Models\ProjectType;
use App\Models\User;
use App\Models\UserDepartment;
use App\Models\WorkJob;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_logins_for_existing_and_unknown_accounts_are_audited_without_claiming_identity(): void
    {
        $user = User::factory()->create();
        foreach ([$user->email, 'unknown@example.com'] as $email) {
            $this->post('/login', ['email' => $email, 'password' => 'wrong-secret'])
                ->assertSessionHasErrors('email');
            $this->assertGuest();
        }

        $entries = DB::table('audit_authentication_attempts_logs')->get();
        $this->assertCount(2, $entries);
        foreach ($entries as $entry) {
            $this->assertSame('login_failed', $entry->action);
            $this->assertNull($entry->causer_id);
            $this->assertNull($entry->causer_type);
            $metadata = json_decode($entry->metadata, true);
            $this->assertSame('An unauthenticated visitor', $metadata['actor_name']);
            $this->assertSame('127.0.0.1', $metadata['ip_address']);
            $this->assertStringNotContainsString('wrong-secret', $entry->metadata);
        }
        $this->assertSame([$user->email, 'unknown@example.com'], $entries->map(fn ($entry) => json_decode($entry->metadata, true)['attempted_email'])->all());
    }

    public function test_throttled_logins_are_audited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => 'unknown@example.com', 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }
        $this->assertSame(5, DB::table('audit_authentication_attempts_logs')->where('action', 'login_failed')->count());
        $this->assertSame(1, DB::table('audit_authentication_attempts_logs')->where('action', 'login_throttled')->count());
    }

    public function test_explicit_actor_is_stored_even_when_a_different_user_is_authenticated(): void
    {
        $admin = User::factory()->create();
        $actor = User::factory()->create();
        $this->actingAs($admin);

        app(ActivityLogger::class)->record($actor, $actor, 'example', 'performed an action');

        $this->assertDatabaseHas('audit_users_logs', [
            'action' => 'example', 'causer_id' => (string) $actor->id, 'causer_type' => User::class,
        ]);
    }

    public function test_application_change_rolls_back_when_audit_write_fails(): void
    {
        $this->actingAs(User::factory()->create());
        $this->mock(ActivityLogger::class, function ($mock) {
            $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        });

        $this->post(route('clients.store'), ['name' => 'Must roll back'])->assertStatus(500);
        $this->assertDatabaseMissing('clients', ['name' => 'Must roll back']);
    }

    public function test_login_does_not_leave_an_authenticated_session_when_audit_write_fails(): void
    {
        $user = User::factory()->create();
        $this->mock(ActivityLogger::class, function ($mock) {
            $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        });
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(500);
        $this->assertGuest();
    }

    public function test_contact_changes_and_prior_audit_entries_roll_back_if_a_later_audit_write_fails(): void
    {
        $this->actingAs(User::factory()->create());
        $client = Client::create(['name' => 'Client']);
        $original = $client->contacts()->create([
            'name' => 'Original contact', 'role' => 'Manager', 'email' => 'original@example.com',
        ]);
        $logger = new ActivityLogger;
        $calls = 0;
        $this->mock(ActivityLogger::class, function ($mock) use ($logger, &$calls) {
            $mock->shouldReceive('record')->twice()->andReturnUsing(function (...$arguments) use ($logger, &$calls) {
                if (++$calls === 2) {
                    throw new \RuntimeException('Second audit failed');
                }
                $logger->record(...$arguments);
            });
        });

        $this->put(route('clients.update', $client), [
            'name' => 'Client', 'contacts_present' => '1',
            'contacts' => [['name' => 'Replacement', 'role' => 'Manager', 'email' => 'new@example.com']],
        ])->assertStatus(500);
        $this->assertModelExists($original);
        $this->assertDatabaseMissing('client_contacts', ['email' => 'new@example.com']);
        $this->assertDatabaseCount('audit_clients_logs', 0);
    }

    public function test_client_rename_and_credential_deletion_are_audited(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $client = Client::create(['name' => 'Old client']);
        $this->put(route('clients.update', $client), ['name' => 'New client'])->assertRedirect();
        $entry = DB::table('audit_clients_logs')->where('action', 'rename_client')->first();
        $this->assertNotNull($entry);
        $this->assertSame("{$user->name} has renamed client Old client to New client.", json_decode($entry->metadata, true)['message']);

        $password = Password::create(['client_id' => $client->id, 'name' => 'Login', 'username' => 'name', 'password' => 'secret']);
        $this->delete(route('passwords.destroy', $password))->assertRedirect();
        $this->assertDatabaseHas('audit_passwords_logs', ['action' => 'password_deleted', 'entity_id' => (string) $password->id]);
    }

    public function test_account_deletion_retains_actor_attribution_for_self_and_admin_deletion(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');
        $this->assertDatabaseHas('audit_users_logs', [
            'action' => 'user_deleted', 'entity_id' => (string) $user->id, 'causer_id' => (string) $user->id,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();
        $this->actingAs($admin)->delete(route('admin.users.delete', $target), ['password' => 'password'])->assertRedirect();
        $this->assertDatabaseHas('audit_users_logs', [
            'action' => 'user_deleted', 'entity_id' => (string) $target->id, 'causer_id' => (string) $admin->id,
        ]);
    }

    public function test_job_reassignment_message_uses_the_new_client(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $oldClient = Client::create(['name' => 'Old client']);
        $newClient = Client::create(['name' => 'New client']);
        $job = WorkJob::create(['title' => 'Job', 'client_id' => $oldClient->id]);
        $this->put(route('jobs.update', $job), [
            'title' => 'Job', 'client_id' => $newClient->id, 'active' => true, 'completed' => false,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $entry = DB::table('audit_work_jobs_logs')->where('action', 'job_updated')->first();
        $this->assertSame('updated job for New client', json_decode($entry->metadata, true)['description']);
    }

    public function test_project_type_changes_department_deletion_and_job_deletion_are_audited(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $type = ProjectType::create(['name' => 'Old type']);
        $client = Client::create(['name' => 'Client']);
        $job = WorkJob::create(['client_id' => $client->id, 'title' => 'Job', 'project_type' => 'Old type']);
        $this->patch(route('admin.project-types.update', $type), ['name' => 'New type'])->assertRedirect();
        $this->assertDatabaseHas('audit_project_types_logs', ['action' => 'projectTypeUpdate']);
        $this->assertSame('New type', $job->fresh()->project_type);
        $this->delete(route('admin.project-types.delete', $type))->assertRedirect();
        $this->assertDatabaseHas('audit_project_types_logs', ['action' => 'projectTypeDelete']);

        $department = UserDepartment::create(['department' => 'Test department']);
        $this->delete(route('admin.company-departments.delete', $department))->assertRedirect();
        $this->assertDatabaseHas('audit_user_departments_logs', ['action' => 'delete_company_department']);

        $this->delete(route('jobs.destroy', $job))->assertRedirect();
        $this->assertDatabaseHas('audit_work_jobs_logs', ['action' => 'job_deleted', 'entity_id' => (string) $job->id]);
    }
}
