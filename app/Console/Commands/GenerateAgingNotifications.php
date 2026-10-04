<?php

namespace App\Console\Commands;

use App\Models\OperationalNotification;
use App\Models\Payable;
use App\Models\Receivable;
use App\Support\FixedDecimal;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class GenerateAgingNotifications extends Command
{
    protected $signature='notifications:aging';
    protected $description='Buat pengingat harian untuk piutang dan hutang yang lewat jatuh tempo';

    public function handle(): int
    {
        $created=0;
        Receivable::query()->with(['transaction:id,outlet_id,number','customer:id,name'])->whereIn('status',['unpaid','partial'])->whereNotNull('due_at')->whereDate('due_at','<',today())
            ->whereHas('transaction',fn (Builder $query)=>$query->whereNotNull('outlet_id'))
            ->chunkById(200,function ($rows) use (&$created): void {
                foreach($rows as $row) {
                    $balance=FixedDecimal::money((string)$row->amount)-FixedDecimal::money((string)$row->paid_amount);
                    $created+=$this->notify($row->transaction->outlet_id,'receivable-overdue','receivable',$row->id,__('Piutang lewat jatuh tempo'),__(':reference · :customer · sisa Rp :amount',['reference'=>$row->reference,'customer'=>$row->customer?->name??'Pelanggan','amount'=>number_format($balance/100,0,',','.') ]));
                }
            });
        Payable::query()->with(['receiving:id,warehouse_id,number','receiving.warehouse:id,outlet_id,name','supplier:id,name'])->whereIn('status',['unpaid','partial'])->whereNotNull('due_at')->whereDate('due_at','<',today())
            ->whereHas('receiving.warehouse',fn (Builder $query)=>$query->whereNotNull('outlet_id'))
            ->chunkById(200,function ($rows) use (&$created): void {
                foreach($rows as $row) {
                    $balance=FixedDecimal::money((string)$row->amount)-FixedDecimal::money((string)$row->paid_amount);
                    $created+=$this->notify($row->receiving->warehouse->outlet_id,'payable-overdue','payable',$row->id,__('Hutang lewat jatuh tempo'),__(':reference · :supplier · sisa Rp :amount',['reference'=>$row->reference,'supplier'=>$row->supplier?->name??'Pemasok','amount'=>number_format($balance/100,0,',','.') ]));
                }
            });
        $this->info("Pengingat dibuat: {$created}"); return self::SUCCESS;
    }

    private function notify(int $outletId,string $type,string $referenceType,int $referenceId,string $title,string $body): int
    {
        $exists=OperationalNotification::query()->where('outlet_id',$outletId)->where('type',$type)->where('reference_type',$referenceType)->where('reference_id',$referenceId)->whereDate('created_at',today())->exists();
        if($exists)return 0;
        OperationalNotification::query()->create(['outlet_id'=>$outletId,'type'=>$type,'title'=>$title,'body'=>$body,'reference_type'=>$referenceType,'reference_id'=>$referenceId]);
        return 1;
    }
}
