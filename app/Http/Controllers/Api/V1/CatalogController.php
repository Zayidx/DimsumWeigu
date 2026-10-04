<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Support\OutletAccess;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CatalogController extends Controller
{
    public function products(Request $request, OutletAccess $access): JsonResponse
    {
        $outlet = $this->outlet($request, $access);
        $warehouseIds = Warehouse::query()->where('outlet_id',$outlet->id)->where('is_active',true)->pluck('id');
        $data = $request->validate(['search'=>['nullable','string','max:120'],'warehouse_id'=>['nullable','integer'],'per_page'=>['nullable','integer','min:1','max:100']]);
        $warehouseId = $data['warehouse_id'] ?? null;
        if ($warehouseId && ! $warehouseIds->contains((int)$warehouseId)) abort(404);
        $products = Product::query()->with(['category:id,name','unit:id,name,symbol','warehouseStocks'=>fn($query)=>$query->whereIn('warehouse_id',$warehouseId ? [(int)$warehouseId] : $warehouseIds)->with('warehouse:id,name')])
            ->where('is_active',true)->where('is_sellable',true)
            ->when(($data['search'] ?? '') !== '',fn($query)=>$query->where(fn($sub)=>$sub->where('title','like','%'.$data['search'].'%')->orWhere('sku','like','%'.$data['search'].'%')->orWhere('barcode','like','%'.$data['search'].'%')))
            ->orderBy('title')->paginate((int)($data['per_page'] ?? 25));
        return response()->json(['outlet'=>['id'=>$outlet->id,'code'=>$outlet->code,'name'=>$outlet->name],'data'=>$products->through(fn(Product $product)=>[
            'id'=>$product->id,'sku'=>$product->sku,'barcode'=>$product->barcode,'name'=>$product->title,'description'=>$product->description,
            'sell_price'=>$product->sell_price,'tax_rate'=>$product->tax_rate,'unit'=>['id'=>$product->unit?->id,'name'=>$product->unit?->name,'symbol'=>$product->unit?->symbol],
            'category'=>$product->category?->only(['id','name']),'stocks'=>$product->warehouseStocks->map(fn($stock)=>['warehouse_id'=>$stock->warehouse_id,'warehouse'=>$stock->warehouse?->name,'available'=>(float)$stock->stock-(float)$stock->reserved_stock])->values(),
        ])]);
    }

    public function categories(): JsonResponse
    {
        $categories=Category::query()->where('is_active',true)->orderBy('name')->get(['id','name','slug','parent_id']);
        return response()->json(['data'=>$categories]);
    }

    public function warehouses(Request $request, OutletAccess $access): JsonResponse
    {
        $outlet=$this->outlet($request,$access);
        $warehouses=Warehouse::query()->where('outlet_id',$outlet->id)->where('is_active',true)->orderBy('name')->get(['id','code','name','type','is_sales_enabled']);
        return response()->json(['outlet'=>['id'=>$outlet->id,'code'=>$outlet->code,'name'=>$outlet->name],'data'=>$warehouses]);
    }

    public function suppliers(Request $request): JsonResponse
    {
        $data=$request->validate(['search'=>['nullable','string','max:120'],'per_page'=>['nullable','integer','min:1','max:100']]);
        $suppliers=Supplier::query()->where('is_active',true)->when(($data['search']??'')!=='',fn($query)=>$query->where(fn($sub)=>$sub->where('name','like','%'.$data['search'].'%')->orWhere('code','like','%'.$data['search'].'%')))->orderBy('name')->paginate((int)($data['per_page']??25));
        return response()->json(['data'=>$suppliers]);
    }

    public function customers(Request $request, OutletAccess $access): JsonResponse
    {
        $outlet=$this->outlet($request,$access);
        $data=$request->validate(['search'=>['nullable','string','max:120'],'per_page'=>['nullable','integer','min:1','max:100']]);
        $customers=Customer::query()->where('is_active',true)->whereHas('transactions',fn($query)=>$query->where('outlet_id',$outlet->id))
            ->when(($data['search']??'')!=='',fn($query)=>$query->where(fn($sub)=>$sub->where('name','like','%'.$data['search'].'%')->orWhere('code','like','%'.$data['search'].'%')->orWhere('phone','like','%'.$data['search'].'%')))
            ->orderBy('name')->paginate((int)($data['per_page']??25));
        return response()->json(['data'=>$customers->through(fn(Customer $customer)=>$customer->only(['id','code','name','phone','email','member_tier','loyalty_points']))]);
    }

    public function createCustomer(Request $request, OutletAccess $access, AuditLogger $audit): JsonResponse
    {
        $outlet=$this->outlet($request,$access);
        $data=$request->validate(['name'=>['required','string','max:160'],'email'=>['nullable','email','max:255'],'phone'=>['nullable','string','max:32'],'marketing_consent'=>['sometimes','boolean']]);
        $customer=Customer::query()->create(['code'=>'CUS-'.Str::upper(Str::random(8)),'name'=>trim($data['name']),'email'=>$data['email']??null,'phone'=>$data['phone']??null,'marketing_consent'=>(bool)($data['marketing_consent']??false),'is_active'=>true]);
        $audit->record('customer.created_api',$customer,null,['code'=>$customer->code,'name'=>$customer->name],['outlet_id'=>$outlet->id]);
        return response()->json(['data'=>$customer->only(['id','code','name','phone','email','member_tier','loyalty_points'])],201);
    }

    private function outlet(Request $request, OutletAccess $access)
    {
        $user=$request->user();
        if ($request->filled('outlet_id')) return $access->require($user,(int)$request->query('outlet_id'));
        $defaultId=$user->outlets()->wherePivot('is_default',true)->value('outlets.id');
        return ($defaultId ? $access->outletsFor($user)->whereKey($defaultId)->first() : null) ?? $access->outletsFor($user)->firstOrFail();
    }
}
