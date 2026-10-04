<?php

namespace App\Livewire\Admin;

use App\Models\BankAccount;
use App\Support\AuditLogger;
use Livewire\Component;

class BankAccountsIndex extends Component
{
    public bool $showForm=false;
    public ?int $accountId=null;
    public string $bankName='';
    public string $accountName='';
    public string $accountNumber='';
    public int $sortOrder=0;
    public bool $isActive=true;

    public function create(): void { $this->permit('bank-accounts-manage'); $this->resetForm(); $this->showForm=true; }
    public function edit(int $id): void { $this->permit('bank-accounts-manage'); $row=BankAccount::query()->findOrFail($id); $this->accountId=$row->id; $this->bankName=$row->bank_name; $this->accountName=$row->account_name; $this->accountNumber=$row->account_number; $this->sortOrder=$row->sort_order; $this->isActive=$row->is_active; $this->showForm=true; }
    public function closeForm(): void { $this->resetForm(); }

    public function save(AuditLogger $audit): void
    {
        $this->stepUp(); $this->permit('bank-accounts-manage');
        $data=$this->validate(['bankName'=>['required','string','max:120'],'accountName'=>['required','string','max:160'],'accountNumber'=>['required','string','max:80'],'sortOrder'=>['required','integer','min:0','max:100000'],'isActive'=>['boolean']]);
        $row=$this->accountId ? BankAccount::query()->findOrFail($this->accountId) : new BankAccount;
        $before=$row->exists ? $row->only(['bank_name','account_name','account_number','sort_order','is_active']) : null;
        $row->fill(['bank_name'=>trim($data['bankName']),'account_name'=>trim($data['accountName']),'account_number'=>trim($data['accountNumber']),'sort_order'=>$data['sortOrder'],'is_active'=>$data['isActive']])->save();
        $audit->record($before?'bank_account.updated':'bank_account.created',$row,$this->safeSnapshot($before),$this->safeSnapshot($row->only(['bank_name','account_name','account_number','sort_order','is_active'])));
        $this->resetForm(); $this->dispatch('toast',type:'success',message:__('Rekening bank disimpan.'));
    }

    public function deactivate(int $id, AuditLogger $audit): void
    {
        $this->stepUp(); $this->permit('bank-accounts-manage'); $row=BankAccount::query()->findOrFail($id); $before=['is_active'=>$row->is_active]; $row->forceFill(['is_active'=>false])->save(); $audit->record('bank_account.deactivated',$row,$before,['is_active'=>false]);
    }

    public function render()
    {
        $this->permit('bank-accounts-access');
        return view('livewire.admin.bank-accounts-index',['accounts'=>BankAccount::query()->orderBy('sort_order')->orderBy('bank_name')->get()])->layout('layouts.dashboard',['title'=>__('Rekening bank')]);
    }

    private function permit(string $permission): void { abort_unless(auth()->user()?->can($permission),403); }
    private function safeSnapshot(?array $data): ?array { if($data && isset($data['account_number'])) $data['account_number']='••••'.substr((string)$data['account_number'],-4); return $data; }
    private function stepUp(): void { abort_if(now()->timestamp-(int)session('auth.password_confirmed_at',0)>(int)config('auth.password_timeout',10800),423,__('Konfirmasi kata sandi diperlukan. Muat ulang halaman untuk mengonfirmasi.')); }
    private function resetForm(): void { $this->showForm=false; $this->accountId=null; $this->bankName=''; $this->accountName=''; $this->accountNumber=''; $this->sortOrder=0; $this->isActive=true; $this->resetValidation(); }
}
