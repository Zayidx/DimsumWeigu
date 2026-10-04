<?php

namespace App\Livewire\Catalog;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class UnitsIndex extends Component
{
    public bool $showForm = false;

    public ?int $unitId = null;

    public string $name = '';

    public string $symbol = '';

    public string $dimension = 'quantity';

    public bool $isActive = true;

    public bool $showConversionForm = false;

    public ?int $conversionId = null;

    public string $fromUnitId = '';

    public string $toUnitId = '';

    public string $factor = '';

    public function create(): void
    {
        $this->requirePermission('units-create');
        $this->resetUnitForm();
        $this->showForm = true;
    }

    public function closeUnitForm(): void
    {
        $this->resetUnitForm();
    }

    public function edit(int $id): void
    {
        $this->requirePermission('units-edit');
        $unit = Unit::query()->findOrFail($id);
        $this->unitId = $unit->id;
        $this->name = $unit->name;
        $this->symbol = $unit->symbol;
        $this->dimension = $unit->dimension;
        $this->isActive = $unit->is_active;
        $this->showForm = true;
    }

    public function saveUnit(AuditLogger $audit): void
    {
        $this->requirePermission($this->unitId ? 'units-edit' : 'units-create');
        $currentUnit = $this->unitId ? Unit::query()->findOrFail($this->unitId) : null;
        $symbolRules = ['required', 'string', 'max:16'];
        if (! $currentUnit || trim($this->symbol) !== $currentUnit->symbol) {
            $symbolRules[] = Rule::unique('units', 'symbol')->ignore($this->unitId);
        }
        $data = $this->validate(['name' => ['required', 'string', 'max:100'], 'symbol' => $symbolRules, 'dimension' => ['required', 'string', 'max:24', 'alpha_dash'], 'isActive' => ['boolean']]);
        DB::transaction(function () use ($data, $audit): void {
            $unit = $this->unitId ? Unit::query()->lockForUpdate()->findOrFail($this->unitId) : new Unit;
            $before = $unit->exists ? $unit->only(['name', 'symbol', 'dimension', 'is_active']) : null;
            if ($unit->exists && $unit->dimension !== $data['dimension'] && (Product::withTrashed()->where('unit_id', $unit->id)->exists() || $unit->productUnits()->exists() || UnitConversion::query()->where(fn ($query) => $query->where('from_unit_id', $unit->id)->orWhere('to_unit_id', $unit->id))->exists())) {
                throw ValidationException::withMessages(['dimension' => __('Dimensi tidak dapat diubah karena satuan sudah dipakai.')]);
            }
            $unit->fill(['name' => trim($data['name']), 'symbol' => trim($data['symbol']), 'dimension' => $data['dimension'], 'is_active' => $data['isActive']]);
            $unit->save();
            $audit->record($before ? 'unit.updated' : 'unit.created', $unit, $before, $unit->only(['name', 'symbol', 'dimension', 'is_active']));
        });
        $this->resetUnitForm();
        $this->dispatch('toast', type: 'success', message: __('Satuan berhasil disimpan.'));
    }

    public function createConversion(): void
    {
        $this->requirePermission('units-edit');
        $this->resetConversionForm();
        $this->showConversionForm = true;
    }

    public function closeConversionForm(): void
    {
        $this->resetConversionForm();
    }

    public function editConversion(int $id): void
    {
        $this->requirePermission('units-edit');
        $row = UnitConversion::query()->findOrFail($id);
        $this->conversionId = $row->id;
        $this->fromUnitId = (string) $row->from_unit_id;
        $this->toUnitId = (string) $row->to_unit_id;
        $this->factor = (string) $row->factor;
        $this->showConversionForm = true;
    }

    public function saveConversion(AuditLogger $audit): void
    {
        $this->requirePermission('units-edit');
        $data = $this->validate(['fromUnitId' => ['required', 'integer', 'different:toUnitId', 'exists:units,id'], 'toUnitId' => ['required', 'integer', 'exists:units,id'], 'factor' => ['required', 'numeric', 'decimal:0,8', 'min:0.00000001', 'max:100000000']]);
        $from = Unit::query()->findOrFail($data['fromUnitId']);
        $to = Unit::query()->findOrFail($data['toUnitId']);
        if (! $from->is_active || ! $to->is_active || $from->dimension !== $to->dimension) {
            $this->addError('fromUnitId', __('Konversi hanya berlaku untuk satuan aktif dengan dimensi yang sama.'));

            return;
        }
        DB::transaction(function () use ($data, $audit, $from, $to): void {
            $row = $this->conversionId ? UnitConversion::query()->lockForUpdate()->findOrFail($this->conversionId) : new UnitConversion;
            $before = $row->exists ? $row->only(['from_unit_id', 'to_unit_id', 'factor']) : null;
            if (UnitConversion::query()->where('from_unit_id', $from->id)->where('to_unit_id', $to->id)->when($row->exists, fn ($query) => $query->where('id', '<>', $row->id))->exists()) {
                throw ValidationException::withMessages(['toUnitId' => __('Konversi dari satuan ini sudah tersedia.')]);
            }
            if ($row->exists && ($row->from_unit_id !== $from->id || $row->to_unit_id !== $to->id)) {
                UnitConversion::query()->where('from_unit_id', $row->to_unit_id)->where('to_unit_id', $row->from_unit_id)->delete();
            }
            $row->fill(['from_unit_id' => $from->id, 'to_unit_id' => $to->id, 'factor' => $data['factor']]);
            $row->save();
            $inverse = UnitConversion::query()->firstOrNew(['from_unit_id' => $to->id, 'to_unit_id' => $from->id]);
            $inverse->factor = 1 / (float) $data['factor'];
            $inverse->save();
            $audit->record($before ? 'unit_conversion.updated' : 'unit_conversion.created', $row, $before, ['from_unit' => $from->symbol, 'to_unit' => $to->symbol, 'factor' => $row->factor, 'inverse_factor' => $inverse->factor]);
        });
        $this->resetConversionForm();
        $this->dispatch('toast', type: 'success', message: __('Konversi dua arah berhasil disimpan.'));
    }

    public function deactivate(int $id, AuditLogger $audit): void
    {
        $this->requirePermission('units-delete');
        $unit = Unit::query()->findOrFail($id);
        if (Product::withTrashed()->where('unit_id', $id)->exists() || ProductUnit::query()->where('unit_id', $id)->exists() || UnitConversion::query()->where(fn ($query) => $query->where('from_unit_id', $id)->orWhere('to_unit_id', $id))->exists()) {
            $before = $unit->only(['is_active']);
            $unit->forceFill(['is_active' => false])->save();
            $audit->record('unit.deactivated', $unit, $before, ['is_active' => false]);
            $this->dispatch('toast', type: 'warning', message: __('Satuan masih dipakai; satuan dinonaktifkan.'));
        } else {
            $before = $unit->only(['is_active']);
            $unit->delete();
            $audit->record('unit.deleted', $unit, $before, ['deleted' => true]);
            $this->dispatch('toast', type: 'success', message: __('Satuan dihapus.'));
        }
    }

    public function render()
    {
        $this->requirePermission('units-access');

        return view('livewire.catalog.units-index', [
            'units' => Unit::query()->withCount(['products', 'productUnits'])->orderBy('dimension')->orderBy('name')->get(),
            'conversions' => UnitConversion::query()->with(['fromUnit:id,name,symbol,dimension', 'toUnit:id,name,symbol,dimension'])->orderBy('from_unit_id')->orderBy('to_unit_id')->get(),
            'activeUnits' => Unit::query()->where('is_active', true)->orderBy('dimension')->orderBy('name')->get(['id', 'name', 'symbol', 'dimension']),
        ])->layout('layouts.dashboard', ['title' => __('Satuan & konversi')]);
    }

    private function requirePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }

    private function resetUnitForm(): void
    {
        $this->showForm = false;
        $this->unitId = null;
        $this->name = '';
        $this->symbol = '';
        $this->dimension = 'quantity';
        $this->isActive = true;
        $this->resetValidation();
    }

    private function resetConversionForm(): void
    {
        $this->showConversionForm = false;
        $this->conversionId = null;
        $this->fromUnitId = '';
        $this->toUnitId = '';
        $this->factor = '';
        $this->resetValidation();
    }
}
