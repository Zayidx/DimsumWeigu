<?php

namespace App\Livewire\Admin;

use App\Models\Outlet;
use App\Models\Setting;
use App\Support\AuditLogger;
use App\Support\OutletAccess;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class StoreSettingsIndex extends Component
{
    use WithFileUploads;
    public string $storeName='';
    public string $storePhone='';
    public string $storeEmail='';
    public $storeLogo;
    public string $outletName='';
    public string $phone='';
    public string $email='';
    public string $address='';
    public string $city='';
    public string $description='';
    public string $website='';
    public string $taxNumber='';
    public string $businessNumber='';
    public string $defaultTaxRate='0';
    public string $taxMode='exclusive';
    public string $monthlyTarget='0';

    public function mount(OutletAccess $access): void
    {
        $outlet=$access->current(request());
        abort_unless($outlet,404);
        $this->storeName=(string)Setting::value('store_name','Weigu Dimsum & Gyoza'); $this->storePhone=(string)Setting::value('store_phone',''); $this->storeEmail=(string)Setting::value('store_email','');
        $this->outletName=$outlet->name; $this->phone=$outlet->phone??''; $this->email=$outlet->email??''; $this->address=$outlet->address??''; $this->city=$outlet->city??'';
        $id=$outlet->id;
        $this->description=(string)Setting::value('store_description','',$id); $this->website=(string)Setting::value('store_website','',$id);
        $this->taxNumber=(string)Setting::value('store_tax_number','',$id); $this->businessNumber=(string)Setting::value('store_business_number','',$id);
        $this->defaultTaxRate=(string)Setting::value('default_tax_rate',0,$id); $this->taxMode=(string)Setting::value('tax_mode','exclusive',$id);
        $this->monthlyTarget=(string)Setting::value('monthly_sales_target',0,$id);
    }

    public function saveOutlet(OutletAccess $access, AuditLogger $audit): void
    {
        $this->requireRecentPasswordConfirmation();
        abort_unless(auth()->user()->can('store-settings-update'),403);
        $outlet=$access->require(auth()->user(),(int)session('active_outlet_id'));
        $data=$this->validate(['outletName'=>['required','string','max:160'],'phone'=>['nullable','string','max:32'],'email'=>['nullable','email','max:255'],'address'=>['nullable','string','max:2000'],'city'=>['nullable','string','max:100']]);
        DB::transaction(function () use ($outlet,$data,$audit): void {
            $locked=Outlet::query()->lockForUpdate()->findOrFail($outlet->id);
            $before=$locked->only(['name','phone','email','address','city']);
            $locked->fill(['name'=>trim($data['outletName']),'phone'=>$data['phone']?:null,'email'=>$data['email']?:null,'address'=>$data['address']?:null,'city'=>$data['city']?:null])->save();
            $audit->record('outlet.profile_updated',$locked,$before,$locked->only(['name','phone','email','address','city']));
        });
        $this->dispatch('toast',type:'success',message:__('Profil outlet disimpan.'));
    }

    public function saveBusinessSettings(AuditLogger $audit): void
    {
        $this->requireRecentPasswordConfirmation();
        abort_unless(auth()->user()->can('store-settings-update'),403);
        $outletId=(int)session('active_outlet_id');
        app(OutletAccess::class)->require(auth()->user(),$outletId);
        $data=$this->validate(['description'=>['nullable','string','max:1000'],'website'=>['nullable','url','max:255'],'taxNumber'=>['nullable','string','max:64'],'businessNumber'=>['nullable','string','max:64'],'defaultTaxRate'=>['required','numeric','min:0','max:100'],'taxMode'=>['required','in:exclusive,inclusive'],'monthlyTarget'=>['required','numeric','min:0','max:9999999999999.99']]);
        $values=['store_description'=>$data['description']?:null,'store_website'=>$data['website']?:null,'store_tax_number'=>$data['taxNumber']?:null,'store_business_number'=>$data['businessNumber']?:null,'default_tax_rate'=>$data['defaultTaxRate'],'tax_mode'=>$data['taxMode'],'monthly_sales_target'=>$data['monthlyTarget']];
        DB::transaction(function () use ($values,$outletId,$audit): void {
            $before=[]; foreach(array_keys($values) as $key) $before[$key]=Setting::value($key,null,$outletId);
            foreach($values as $key=>$value) Setting::write($key,$value,in_array($key,['default_tax_rate','tax_mode'],true)?'tax':'general',$outletId);
            $audit->record('store.settings_updated',null,$before,$values);
        });
        $this->dispatch('toast',type:'success',message:__('Pengaturan toko untuk outlet aktif disimpan.'));
    }

    public function saveGlobalProfile(AuditLogger $audit): void
    {
        $this->requireRecentPasswordConfirmation();
        abort_unless(auth()->user()->hasRole('super-admin') && auth()->user()->can('store-settings-update'),403);
        $data=$this->validate(['storeName'=>['required','string','max:160'],'storePhone'=>['nullable','string','max:32'],'storeEmail'=>['nullable','email','max:255'],'storeLogo'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:2048']]);
        $oldLogo=Setting::value('store_logo');
        $newLogo=$this->storeLogo ? $this->storeLogo->store('branding','public') : $oldLogo;
        $before=['store_name'=>Setting::value('store_name'),'store_phone'=>Setting::value('store_phone'),'store_email'=>Setting::value('store_email'),'store_logo'=>$oldLogo];
        DB::transaction(function () use ($data,$newLogo,$audit,$before): void {
            Setting::write('store_name',trim($data['storeName']),'general');
            Setting::write('store_phone',$data['storePhone']?:null,'contact');
            Setting::write('store_email',$data['storeEmail']?:null,'contact');
            if ($newLogo) Setting::write('store_logo',$newLogo,'branding');
            $audit->record('store.global_profile_updated',null,$before,['store_name'=>$data['storeName'],'store_phone'=>$data['storePhone'],'store_email'=>$data['storeEmail'],'store_logo'=>$newLogo]);
        });
        if ($this->storeLogo && $oldLogo && $oldLogo !== $newLogo) Storage::disk('public')->delete($oldLogo);
        $this->storeLogo=null;
        $this->dispatch('toast',type:'success',message:__('Profil global toko disimpan.'));
    }

    public function render(OutletAccess $access)
    {
        abort_unless(auth()->user()->can('store-settings-access'),403);
        $outlet=$access->current(request());
        return view('livewire.admin.store-settings-index',['outlet'=>$outlet,'canEdit'=>auth()->user()->can('store-settings-update'),'isSuperAdmin'=>auth()->user()->hasRole('super-admin')])->layout('layouts.dashboard',['title'=>__('Profil toko')]);
    }

    private function requireRecentPasswordConfirmation(): void { abort_if(now()->timestamp - (int) session('auth.password_confirmed_at', 0) > (int) config('auth.password_timeout',10800),423,__('Konfirmasi kata sandi diperlukan. Muat ulang halaman untuk mengonfirmasi.')); }
}
