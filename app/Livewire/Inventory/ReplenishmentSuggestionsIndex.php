<?php

namespace App\Livewire\Inventory;

use App\Services\Inventory\ReplenishmentSuggestionService;
use App\Support\OutletAccess;
use Livewire\Component;

class ReplenishmentSuggestionsIndex extends Component
{
    public string $outletId = '';

    public string $targetDays = '3';

    public string $lookbackDays = '14';

    public function mount(OutletAccess $access): void
    {
        $this->outletId = (string) ($access->current(request())?->id ?? '');
    }

    public function render(OutletAccess $access, ReplenishmentSuggestionService $suggestions)
    {
        abort_unless(auth()->user()?->can('replenishment-access'), 403);
        $data = $this->validate([
            'outletId' => ['nullable', 'integer'],
            'targetDays' => ['required', 'in:3,5,7'],
            'lookbackDays' => ['required', 'in:7,14,30'],
        ]);
        $outlets = $access->outletsFor(auth()->user())->get(['id', 'name']);
        if ($this->outletId !== '') {
            abort_unless($outlets->contains('id', (int) $this->outletId), 404);
        }
        $result = $suggestions->suggestions(
            $outlets->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            (int) $data['targetDays'],
            (int) $data['lookbackDays'],
            $this->outletId === '' ? null : (int) $this->outletId,
        );

        return view('livewire.inventory.replenishment-suggestions-index', [
            'outlets' => $outlets,
            'centralWarehouse' => $result['central'],
            'rows' => $result['rows'],
            'periodStart' => today()->subDays((int) $this->lookbackDays - 1),
            'periodEnd' => today(),
        ])->layout('layouts.dashboard', ['title' => __('Saran stok dan produksi')]);
    }
}
