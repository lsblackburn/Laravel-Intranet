<?php

namespace Tests\Feature;

use App\Models\ProjectType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_empty_and_populated_project_types(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('admin.view-project-types'))->assertOk()->assertSee('No project types added');

        $type = ProjectType::create(['name' => 'Website design']);
        $this->get(route('admin.view-project-types'))->assertOk()->assertSee($type->name)
            ->assertSee(route('admin.project-types.update', $type), false)
            ->assertSee(route('admin.project-types.delete', $type), false)
            ->assertDontSee('Delete department');
    }

    public function test_admin_can_create_rename_and_delete_a_project_type(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post(route('admin.project-types.create'), ['name' => 'Website design'])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.view-project-types'));
        $type = ProjectType::where('name', 'Website design')->firstOrFail();

        $this->patch(route('admin.project-types.update', $type), ['name' => 'Marketing'])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.view-project-types'));
        $this->assertDatabaseHas('project_types', ['id' => $type->id, 'name' => 'Marketing']);

        $this->delete(route('admin.project-types.delete', $type))
            ->assertRedirect(route('admin.view-project-types'));
        $this->assertDatabaseMissing('project_types', ['id' => $type->id]);
    }

    public function test_names_are_required_unique_and_limited_in_length(): void
    {
        $type = ProjectType::create(['name' => 'Website design']);
        $other = ProjectType::create(['name' => 'Marketing']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach (['', 'Website design', str_repeat('a', 256)] as $name) {
            $this->post(route('admin.project-types.create'), ['name' => $name])->assertSessionHasErrors('name');
        }
        foreach (['', $other->name, str_repeat('a', 256)] as $name) {
            $this->from(route('admin.view-project-types'))
                ->patch(route('admin.project-types.update', $type), ['name' => $name, '_project_type_id' => $type->id])
                ->assertSessionHasErrors('name', errorBag: 'updateProjectType'.$type->id);
            $this->get(route('admin.view-project-types'))->assertOk()->assertSee('name="_project_type_id"', false);
        }
        $this->assertSame('Website design', $type->fresh()->name);
        $this->patch(route('admin.project-types.update', $type), ['name' => $type->name])
            ->assertSessionHasNoErrors();
    }

    public function test_unknown_project_types_return_not_found(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->patch(route('admin.project-types.update', 999), ['name' => 'Unknown'])->assertNotFound();
        $this->delete(route('admin.project-types.delete', 999))->assertNotFound();
    }

    public function test_employees_cannot_manage_project_types(): void
    {
        $type = ProjectType::create(['name' => 'Website design']);
        $this->actingAs(User::factory()->create(['role' => 'employee']));
        $this->get(route('admin.view-project-types'))->assertForbidden();
        $this->post(route('admin.project-types.create'), ['name' => 'Marketing'])->assertForbidden();
        $this->patch(route('admin.project-types.update', $type), ['name' => 'Marketing'])->assertForbidden();
        $this->delete(route('admin.project-types.delete', $type))->assertForbidden();
        $this->assertDatabaseCount('project_types', 1);
        $this->assertSame('Website design', $type->fresh()->name);
    }

    public function test_guests_cannot_manage_project_types(): void
    {
        $type = ProjectType::create(['name' => 'Website design']);
        $this->get(route('admin.view-project-types'))->assertRedirect(route('login'));
        $this->post(route('admin.project-types.create'), ['name' => 'Marketing'])->assertRedirect(route('login'));
        $this->patch(route('admin.project-types.update', $type), ['name' => 'Marketing'])->assertRedirect(route('login'));
        $this->delete(route('admin.project-types.delete', $type))->assertRedirect(route('login'));
        $this->assertSame('Website design', $type->fresh()->name);
    }
}
