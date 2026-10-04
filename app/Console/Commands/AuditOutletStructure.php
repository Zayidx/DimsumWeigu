<?php

namespace App\Console\Commands;

use App\Models\Outlet;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Console\Command;

class AuditOutletStructure extends Command
{
    protected $signature='outlet:audit {--strict : Return failure if any inconsistency is found}';
    protected $description='Periksa konsistensi outlet, gudang, dan assignment default pengguna';

    public function handle(): int
    {
        $issues=0;
        foreach(Warehouse::query()->with('outlet:id,code,is_sales_enabled')->get() as $warehouse) {
            if($warehouse->type==='central' && ($warehouse->outlet_id!==null || $warehouse->is_sales_enabled)) { $this->error("{$warehouse->code}: central warehouse harus tanpa outlet dan non-sales"); $issues++; }
            if($warehouse->is_sales_enabled && (!$warehouse->outlet || !$warehouse->outlet->is_sales_enabled)) { $this->error("{$warehouse->code}: gudang penjualan harus terkait outlet penjualan aktif"); $issues++; }
        }
        foreach(Outlet::query()->where('is_active',true)->where('is_sales_enabled',true)->get() as $outlet) {
            if(!$outlet->warehouses()->where('is_active',true)->where('is_sales_enabled',true)->exists()) { $this->error("{$outlet->code}: outlet penjualan belum memiliki warehouse penjualan aktif"); $issues++; }
        }
        foreach(User::query()->where('is_active',true)->with('outlets')->get() as $user) {
            $defaults=$user->outlets->where('pivot.is_default',true);
            if($user->outlets->isEmpty() || $defaults->count()!==1) { $this->error("user #{$user->id}: harus memiliki outlet dan tepat satu default outlet"); $issues++; }
            elseif(!$defaults->first()->is_active) { $this->error("user #{$user->id}: default outlet nonaktif"); $issues++; }
        }
        $this->info("Audit selesai. Masalah: {$issues}");
        return $issues && $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }
}
