<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class NavigationMenuTest extends TestCase
{
    public function test_mobile_links_render_active_states_and_escape_labels(): void
    {
        $html = Blade::render(
            '<x-mobile-navigation-link :href="$href" :label="$label" icon="lock" :active="true" />',
            ['href' => route('passwords.index'), 'label' => 'Passwords <script>'],
        );

        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('Passwords &lt;script&gt;', $html);
        $this->assertStringContainsString('Current page', $html);
    }

    public function test_menu_labels_do_not_resolve_translation_file_arrays(): void
    {
        // Reproduce the case-insensitive translation-file collision on every OS.
        app('translator')->addLines(['Passwords.reset' => 'Password reset'], 'en');
        $this->assertIsArray(__('Passwords'));

        $html = Blade::render(
            '<x-navigation-menu label="Credentials" :items="$items" />',
            ['items' => [
                ['route' => 'clients.index', 'label' => 'Clients', 'description' => 'Client directory'],
                ['route' => 'passwords.index', 'label' => 'Passwords', 'description' => 'Saved credentials'],
            ]],
        );

        $this->assertStringContainsString('Passwords</span>', $html);
        $this->assertStringContainsString(e(route('passwords.index')), $html);
        $this->assertStringContainsString('Clients</span>', $html);
    }
}
