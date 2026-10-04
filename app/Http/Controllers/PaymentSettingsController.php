<?php

namespace App\Http\Controllers;

use App\Models\PaymentSetting;
use App\Support\AuditLogger;
use App\Support\OutletAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class PaymentSettingsController extends Controller
{
    private const PROVIDERS=['midtrans'=>['server_key','client_key'],'xendit'=>['secret_key','public_key','webhook_token']];

    public function edit(Request $request, OutletAccess $access): View
    {
        $outlet=$access->current($request);
        $settings=PaymentSetting::query()->where('scope_key','outlet:'.$outlet->id)->get()->keyBy('provider');
        $providers=[];
        foreach(self::PROVIDERS as $provider=>$keys) {
            $row=$settings->get($provider);
            $providers[$provider]=['mode'=>$row?->mode??'sandbox','is_enabled'=>$row?->is_enabled??false,'credentials_present'=>array_values(array_filter($keys,fn($key)=>filled(data_get($row?->credentials,$key))))];
        }
        return view('settings.payments',compact('outlet','providers'));
    }

    public function update(Request $request, OutletAccess $access, AuditLogger $audit): RedirectResponse
    {
        $outlet=$access->require($request->user(),(int)$request->session()->get('active_outlet_id'));
        $rules=[];
        foreach(self::PROVIDERS as $provider=>$keys) {
            $rules["providers.$provider.mode"]=['required','in:sandbox,production'];
            $rules["providers.$provider.is_enabled"]=['nullable','boolean'];
            foreach($keys as $key) $rules["providers.$provider.credentials.$key"]=['nullable','string','max:500'];
        }
        $validator=Validator::make($request->all(),$rules);
        if($validator->fails()) return back()->withErrors($validator);
        $data=$validator->validated();
        foreach(self::PROVIDERS as $provider=>$keys) {
            if (!($data['providers'][$provider]['is_enabled']??false)) continue;
            $current=PaymentSetting::query()->where('scope_key','outlet:'.$outlet->id)->where('provider',$provider)->first();
            $credentials=$current?->credentials??[];
            foreach($keys as $key) if(filled($data['providers'][$provider]['credentials'][$key]??null)) $credentials[$key]=$data['providers'][$provider]['credentials'][$key];
            if(count(array_filter($keys,fn($key)=>filled($credentials[$key]??null)))!==count($keys)) return back()->withErrors(["providers.$provider.is_enabled"=>__('Lengkapi semua kredensial sebelum mengaktifkan provider.')]);
        }
        DB::transaction(function () use ($data,$outlet,$audit): void {
            foreach(self::PROVIDERS as $provider=>$keys) {
                $scope='outlet:'.$outlet->id;
                $row=PaymentSetting::query()->where('scope_key',$scope)->where('provider',$provider)->lockForUpdate()->first();
                $before=['mode'=>$row?->mode,'is_enabled'=>$row?->is_enabled,'credentials_configured'=>array_values(array_filter($keys,fn($key)=>filled(data_get($row?->credentials,$key))))];
                $credentials=$row?->credentials??[];
                foreach($keys as $key) if (filled($data['providers'][$provider]['credentials'][$key]??null)) $credentials[$key]=$data['providers'][$provider]['credentials'][$key];
                $enabled=(bool)($data['providers'][$provider]['is_enabled']??false);
                if ($enabled && count(array_filter($keys,fn($key)=>filled($credentials[$key]??null))) !== count($keys)) abort(422);
                $row??=new PaymentSetting;
                $row->fill(['outlet_id'=>$outlet->id,'scope_key'=>$scope,'provider'=>$provider,'mode'=>$data['providers'][$provider]['mode'],'is_enabled'=>$enabled,'credentials'=>$credentials]);
                $row->save();
                $audit->record('payment_provider.updated',$row,$before,['mode'=>$row->mode,'is_enabled'=>$row->is_enabled,'credentials_configured'=>array_values(array_filter($keys,fn($key)=>filled($credentials[$key]??null)))]);
            }
        });
        return back()->with('status',__('Pengaturan pembayaran berhasil disimpan.'));
    }
}
