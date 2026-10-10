<?php

namespace App\Livewire\Admin;

use App\Livewire\PublicSite\Home;
use App\Models\Setting;
use App\Queries\PublicSite\HomepageData;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class HomepageSettingsIndex extends Component
{
    use WithFileUploads;

    public array $content = [];

    public $heroImage;

    public array $menuImages = [];

    public array $removeMenuImages = [];

    public function mount(): void
    {
        $this->content = $this->currentContent();
    }

    public function save(AuditLogger $audit): void
    {
        abort_unless(auth()->user()->can('homepage-settings-update'), 403);

        $validated = $this->validate([
            'content.hero_eyebrow' => ['required', 'string', 'max:100'],
            'content.hero_title' => ['required', 'string', 'max:160'],
            'content.order_url' => ['nullable', 'url:http,https', 'max:2048'],
            'content.order_label' => ['required', 'string', 'max:60'],
            'content.hero_caption' => ['required', 'string', 'max:500'],
            'content.story_eyebrow' => ['required', 'string', 'max:100'],
            'content.story_title' => ['required', 'string', 'max:160'],
            'content.story_body' => ['required', 'string', 'max:1000'],
            'content.story_second' => ['required', 'string', 'max:500'],
            'content.menu_title' => ['required', 'string', 'max:120'],
            'content.menu_caption' => ['required', 'string', 'max:300'],
            'content.outlet_title' => ['required', 'string', 'max:120'],
            'content.outlet_caption' => ['required', 'string', 'max:300'],
            'heroImage' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'menuImages.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $before = $this->currentContent();
        $next = $validated['content'];
        $next['menu_images'] = $before['menu_images'];
        $oldFiles = [];

        if ($this->heroImage) {
            $path = $this->heroImage->store('homepage', 'public');
            if (! empty($before['hero_image'])) {
                $oldFiles[] = $before['hero_image'];
            }
            $next['hero_image'] = $path;
        } else {
            $next['hero_image'] = $before['hero_image'] ?? null;
        }

        foreach ($this->removeMenuImages as $slug => $remove) {
            if ($remove && isset($next['menu_images'][$slug])) {
                $oldFiles[] = $next['menu_images'][$slug];
                unset($next['menu_images'][$slug]);
            }
        }

        foreach ($this->menuImages as $slug => $image) {
            if (! $image) {
                continue;
            }
            $path = $image->store('homepage/menu', 'public');
            if (! empty($next['menu_images'][$slug])) {
                $oldFiles[] = $next['menu_images'][$slug];
            }
            $next['menu_images'][$slug] = $path;
        }

        Setting::write('homepage_content', $next, 'homepage');
        $audit->record('homepage.content_updated', null, $before, $next);

        foreach (array_unique($oldFiles) as $oldFile) {
            Storage::disk('public')->delete($oldFile);
        }

        $this->content = $next;
        $this->heroImage = null;
        $this->menuImages = [];
        $this->removeMenuImages = [];
        $this->dispatch('toast', type: 'success', message: 'Konten homepage berhasil disimpan.');
    }

    public function render()
    {
        abort_unless(auth()->user()->can('homepage-settings-access'), 403);

        $menus = [];
        foreach (Home::menuCategories() as $category) {
            foreach ($category['items'] as $item) {
                $menus[] = ['slug' => Str::slug($item['name']), 'name' => $item['name'], 'image' => $this->content['menu_images'][Str::slug($item['name'])] ?? null];
            }
        }

        return view('livewire.admin.homepage-settings-index', [
            'menus' => $menus,
            'canEdit' => auth()->user()->can('homepage-settings-update'),
        ])->layout('layouts.dashboard', ['title' => 'Konten homepage']);
    }

    private function currentContent(): array
    {
        return app(HomepageData::class)->get()['homepage'];
    }
}
