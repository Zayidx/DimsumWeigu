<?php

namespace Tests\Feature;

use App\Livewire\Admin\HomepageSettingsIndex;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HomepageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_publish_homepage_copy_and_direct_order_link(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $manager = User::create(['name' => 'Manager', 'email' => 'manager@example.test', 'password' => 'Test-password-123!', 'is_active' => true]);
        $manager->assignRole('manager');
        $this->actingAs($manager)->get(route('settings.homepage'))->assertOk();

        Livewire::actingAs($manager)->test(HomepageSettingsIndex::class)
            ->set('content.hero_title', 'Menu untuk makan siang')
            ->set('content.order_url', 'https://example.test/toko-weigu')
            ->set('content.order_label', 'Pesan di toko')
            ->call('save')->assertHasNoErrors();

        $this->get('/')->assertOk()->assertSee('Menu untuk makan siang')
            ->assertSee('https://example.test/toko-weigu')->assertSee('Pesan di toko');
        $this->assertDatabaseHas('audit_logs', ['event' => 'homepage.content_updated', 'user_id' => $manager->id]);
    }

    public function test_unsafe_order_link_does_not_replace_published_content(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $manager = User::create(['name' => 'Manager', 'email' => 'manager@example.test', 'password' => 'Test-password-123!', 'is_active' => true]);
        $manager->assignRole('manager');
        Setting::write('homepage_content', ['hero_title' => 'Judul tersimpan']);

        Livewire::actingAs($manager)->test(HomepageSettingsIndex::class)
            ->set('content.order_url', 'javascript:alert(1)')
            ->call('save')->assertHasErrors(['content.order_url']);

        $this->assertSame('Judul tersimpan', Setting::value('homepage_content')['hero_title']);
    }

    public function test_read_only_editor_cannot_publish_changes(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $viewer = User::create(['name' => 'Viewer', 'email' => 'viewer@example.test', 'password' => 'Test-password-123!', 'is_active' => true]);
        $viewer->givePermissionTo('homepage-settings-access');

        Livewire::actingAs($viewer)->test(HomepageSettingsIndex::class)
            ->set('content.hero_title', 'Tidak boleh diterbitkan')
            ->call('save')->assertForbidden();

        $this->assertNull(Setting::value('homepage_content'));
    }
}
