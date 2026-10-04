<?php

namespace App\Support;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OutletAccess
{
    public function outletsFor(User $user): Builder
    {
        $query = Outlet::query()->where('is_active', true);

        if (! $user->hasRole('super-admin')) {
            $query->whereHas('users', fn (Builder $users) => $users->whereKey($user->id));
        }

        return $query->orderBy('name');
    }

    public function current(Request $request): ?Outlet
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $outlets = $this->outletsFor($user);
        $id = $request->session()->get('active_outlet_id');

        if ($id && ($outlet = (clone $outlets)->whereKey($id)->first())) {
            return $outlet;
        }

        $defaultId = $user->outlets()->wherePivot('is_default', true)->value('outlets.id');
        $outlet = $defaultId ? (clone $outlets)->whereKey($defaultId)->first() : null;
        $outlet ??= (clone $outlets)->first();

        if ($outlet) {
            $request->session()->put('active_outlet_id', $outlet->id);
        }

        return $outlet;
    }

    public function require(User $user, int $outletId): Outlet
    {
        return $this->outletsFor($user)->whereKey($outletId)->first() ?? throw new NotFoundHttpException;
    }
}
