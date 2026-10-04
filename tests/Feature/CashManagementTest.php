<?php

namespace Tests\Feature;

use App\Livewire\Finance\CashManagementIndex;
use App\Models\Cart;
use App\Models\CashHandover;
use App\Models\CashierShift;
use App\Models\Outlet;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Cashier\CashHandoverService;
use App\Services\Cashier\CashierShiftService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CashManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $sender;

    private User $manager;

    private User $receiver;

    private Warehouse $source;

    private Warehouse $destination;

    private CashierShift $sourceShift;

    private CashierShift $destinationShift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $sourceOutlet = Outlet::create(['code' => 'CASH-A', 'slug' => 'cash-a', 'name' => 'Cash A', 'is_active' => true, 'is_sales_enabled' => true]);
        $destOutlet = Outlet::create(['code' => 'CASH-B', 'slug' => 'cash-b', 'name' => 'Cash B', 'is_active' => true, 'is_sales_enabled' => true]);
        $this->source = Warehouse::create(['outlet_id' => $sourceOutlet->id, 'code' => 'CASH-A-WH', 'name' => 'Cash A warehouse', 'type' => 'branch', 'is_active' => true, 'is_sales_enabled' => true]);
        $this->destination = Warehouse::create(['outlet_id' => $destOutlet->id, 'code' => 'CASH-B-WH', 'name' => 'Cash B warehouse', 'type' => 'branch', 'is_active' => true, 'is_sales_enabled' => true]);
        foreach (['sender' => 'cashier', 'manager' => 'manager', 'receiver' => 'cashier'] as $name => $role) {
            $this->{$name} = User::create(['name' => $name, 'email' => $name.'@cash.example.test', 'password' => 'Test-password-123!', 'is_active' => true]);
            $this->{$name}->assignRole($role);
        }
        $this->sender->outlets()->attach([$sourceOutlet->id => ['is_default' => true], $destOutlet->id => ['is_default' => false]]);
        $this->manager->outlets()->attach([$sourceOutlet->id => ['is_default' => true], $destOutlet->id => ['is_default' => false]]);
        $this->receiver->outlets()->attach($destOutlet->id, ['is_default' => true]);
        $this->as($this->receiver, $this->destination);
        $this->destinationShift = app(CashierShiftService::class)->open($destOutlet->id, $this->destination->id, '0');
        $this->as($this->sender, $this->source);
        $this->sourceShift = app(CashierShiftService::class)->open($sourceOutlet->id, $this->source->id, '1000');
    }

    private function as(User $user, Warehouse $warehouse): void
    {
        $this->actingAs($user)->withSession(['active_outlet_id' => $warehouse->outlet_id, 'auth.password_confirmed_at' => now()->timestamp]);
    }

    private function request(string $amount = '250', ?string $key = null): CashHandover
    {
        return app(CashHandoverService::class)->request($this->source->id, $this->destination->id, $amount, 'Pickup to branch B', $key ?? (string) Str::uuid());
    }

    public function test_cash_moves_only_after_approval_and_receipt_and_retries_once(): void
    {
        $service = app(CashHandoverService::class);
        $key = (string) Str::uuid();
        $handover = $this->request(key: $key);
        $this->assertSame($handover->id, $this->request(key: $key)->id);
        $this->assertDatabaseCount('shift_cash_movements', 0);
        $this->as($this->manager, $this->source);
        $service->approve($handover->id);
        $service->approve($handover->id);
        $this->assertSame('750.00', $this->sourceShift->fresh()->expected_cash);
        $this->assertSame('0.00', $this->destinationShift->fresh()->expected_cash);
        $this->assertDatabaseHas('warehouse_cash_ledgers', ['type' => 'handover_out', 'balance_after' => 750]);
        $this->as($this->receiver, $this->destination);
        $service->confirm($handover->id);
        $service->confirm($handover->id);
        $this->assertSame('250.00', $this->destinationShift->fresh()->expected_cash);
        $this->assertDatabaseCount('shift_cash_movements', 2);
        $this->assertDatabaseCount('warehouse_cash_ledgers', 4);
        $this->assertDatabaseHas('cash_handovers', ['id' => $handover->id, 'status' => 'confirmed', 'approved_by' => $this->manager->id, 'confirmed_by' => $this->receiver->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'cash_handover.approved', 'outlet_id' => $this->source->outlet_id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'cash_handover.confirmed', 'outlet_id' => $this->destination->outlet_id]);
    }

    public function test_approval_rechecks_cash_and_closed_source_shift_atomically(): void
    {
        $handover = $this->request('900');
        app(CashierShiftService::class)->recordMovement($this->sourceShift, 'out', '200', 'Petty cash', (string) Str::uuid());
        $this->as($this->manager, $this->source);
        try {
            app(CashHandoverService::class)->approve($handover->id);
            $this->fail('Insufficient approval accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }
        $this->assertSame('pending', $handover->fresh()->status);
        $this->assertDatabaseCount('warehouse_cash_ledgers', 3);
        $this->as($this->sender, $this->source);
        app(CashierShiftService::class)->close($this->sourceShift->id, '800');
        $this->as($this->manager, $this->source);
        try {
            app(CashHandoverService::class)->approve($handover->id);
            $this->fail('Closed shift accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('handover', $e->errors());
        }
        $this->assertSame('pending', $handover->fresh()->status);
    }

    public function test_cancel_pending_has_no_cash_effect_and_sent_cash_cannot_cancel(): void
    {
        $handover = $this->request();
        $service = app(CashHandoverService::class);
        $service->cancel($handover->id);
        $service->cancel($handover->id);
        $this->assertSame('cancelled', $handover->fresh()->status);
        $this->assertDatabaseCount('shift_cash_movements', 0);
        $sent = $this->request();
        $this->as($this->manager, $this->source);
        $service->approve($sent->id);
        try {
            $service->cancel($sent->id);
            $this->fail('Sent transfer cancelled');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('handover', $e->errors());
        }
        $this->assertSame('approved', $sent->fresh()->status);
        $this->assertSame('750.00', $this->sourceShift->fresh()->expected_cash);
    }

    public function test_confirm_requires_destination_shift_and_correct_context(): void
    {
        $handover = $this->request();
        $service = app(CashHandoverService::class);
        $this->as($this->manager, $this->source);
        $service->approve($handover->id);
        try {
            $service->confirm($handover->id);
            $this->fail('Wrong context accepted');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
        $this->as($this->receiver, $this->destination);
        app(CashierShiftService::class)->close($this->destinationShift->id, '0');
        try {
            $service->confirm($handover->id);
            $this->fail('Missing destination shift accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('handover', $e->errors());
        }
        $this->assertSame('approved', $handover->fresh()->status);
        $this->assertDatabaseCount('warehouse_cash_ledgers', 3);
    }

    public function test_conflicting_key_self_approval_and_stepup_are_rejected(): void
    {
        $key = (string) Str::uuid();
        $handover = $this->request(key: $key);
        try {
            $this->request('300', $key);
            $this->fail('Conflicting key accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('idempotency_key', $e->errors());
        }
        $this->sender->assignRole('manager');
        $this->as($this->sender->fresh(), $this->source);
        try {
            app(CashHandoverService::class)->approve($handover->id);
            $this->fail('Self approval accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('handover', $e->errors());
        }
        session()->forget('auth.password_confirmed_at');
        try {
            $this->request();
            $this->fail('Missing stepup accepted');
        } catch (HttpException $e) {
            $this->assertSame(423, $e->getStatusCode());
        }
        $this->assertDatabaseCount('cash_handovers', 1);
    }

    public function test_force_close_requires_permission_stepup_reason_and_active_outlet(): void
    {
        $service = app(CashierShiftService::class);
        $this->as($this->manager, $this->source);
        try {
            $service->forceClose($this->sourceShift->id, '950', 'Emergency closure');
            $this->fail('Manager force closed');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->manager->assignRole('super-admin');
        $this->as($this->manager->fresh(), $this->source);
        session()->forget('auth.password_confirmed_at');
        try {
            $service->forceClose($this->sourceShift->id, '950', 'Emergency closure');
            $this->fail('No stepup force close');
        } catch (HttpException $e) {
            $this->assertSame(423, $e->getStatusCode());
        }
        $this->as($this->manager->fresh(), $this->destination);
        try {
            $service->forceClose($this->sourceShift->id, '950', 'Emergency closure');
            $this->fail('Cross context closure');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
        $this->as($this->manager->fresh(), $this->source);
        try {
            $service->forceClose($this->sourceShift->id, '1000', '');
            $this->fail('No reason closure');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }
        $cart = Cart::create(['uuid' => (string) Str::uuid(), 'user_id' => $this->sender->id, 'cashier_shift_id' => $this->sourceShift->id, 'outlet_id' => $this->source->outlet_id, 'warehouse_id' => $this->source->id, 'status' => 'held']);
        $closed = $service->forceClose($this->sourceShift->id, '950', 'Emergency closure, difference counted');
        $this->assertTrue($closed->is_force_closed);
        $this->assertSame('-50.00', $closed->cash_difference);
        $this->assertNull($closed->open_user_id);
        $this->assertSame('expired', $cart->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'cashier_shift.force_closed', 'user_id' => $this->manager->id, 'outlet_id' => $this->source->outlet_id]);
    }

    public function test_livewire_handover_workflow_and_force_close_form_routes(): void
    {
        $this->get(route('cash-management.index'))->assertOk();
        $this->app->rebinding('request', fn ($app, $request) => $request->setLaravelSession($app['session']->driver()));
        Livewire::test(CashManagementIndex::class)->call('create')->set('sourceId', $this->source->id)->set('destinationId', $this->destination->id)->set('amount', '250')->set('notes', 'Pickup to branch B')->call('requestHandover')->assertHasNoErrors()->assertSet('showForm', false);
        $handover = CashHandover::firstOrFail();
        $this->as($this->manager, $this->source);
        Livewire::test(CashManagementIndex::class)->call('approve', $handover->id)->assertHasNoErrors()->assertSee('Dalam perjalanan');
        $this->as($this->receiver, $this->destination);
        Livewire::test(CashManagementIndex::class)->call('confirm', $handover->id)->assertHasNoErrors()->assertSee('Diterima');
        $this->manager->assignRole('super-admin');
        $this->as($this->manager->fresh(), $this->source);
        $this->get(route('shifts.force-form', $this->sourceShift->id))->assertOk()->assertSee('sender');
        $this->post(route('shifts.force-close', $this->sourceShift->id), ['actual_cash' => '750', 'reason' => 'Emergency closing'])->assertRedirect(route('shifts.index'));
        $this->assertTrue($this->sourceShift->fresh()->is_force_closed);
    }

    public function test_manual_cash_movement_retries_once_and_cannot_overdraw_shift(): void
    {
        $key = (string) Str::uuid();
        $service = app(CashierShiftService::class);
        $movement = $service->recordMovement($this->sourceShift, 'out', '200', 'Petty cash', $key);
        $this->assertSame($movement->id, $service->recordMovement($this->sourceShift, 'out', '200', 'Petty cash', $key)->id);
        try {
            $service->recordMovement($this->sourceShift, 'out', '300', 'Petty cash', $key);
            $this->fail('Conflicting movement accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }
        try {
            $service->recordMovement($this->sourceShift, 'out', '900', 'Petty cash', (string) Str::uuid());
            $this->fail('Overdraft accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }
        $this->assertDatabaseCount('shift_cash_movements', 1);
        $this->assertSame('800.00', $this->sourceShift->fresh()->expected_cash);
        $this->get(route('cash-management.index'))->assertOk();
        $this->app->rebinding('request', fn ($app, $request) => $request->setLaravelSession($app['session']->driver()));
        Livewire::test(CashManagementIndex::class)->set('movementType', 'in')->set('movementAmount', '50')->set('movementNote', 'Additional float')->call('recordMovement')->assertHasNoErrors()->assertSet('movementAmount', '');
        $this->assertSame('850.00', $this->sourceShift->fresh()->expected_cash);
    }

    public function test_ledger_export_is_scoped_and_expired_confirmation_blocks_mutation(): void
    {
        $this->as($this->manager, $this->source);
        $this->get(route('cash-management.index'))->assertOk();
        $this->app->rebinding('request', fn ($app, $request) => $request->setLaravelSession($app['session']->driver()));
        $page = Livewire::test(CashManagementIndex::class)->call('export')->assertFileDownloaded();
        $csv = base64_decode($page->effects['download']['content']);
        $this->assertStringContainsString('Cash A warehouse', $csv);
        $this->assertStringNotContainsString('Cash B warehouse', $csv);
        Livewire::test(CashManagementIndex::class)->set('ledgerWarehouse', (string) $this->destination->id)->assertNotFound();
        $this->as($this->sender, $this->source);
        session()->put('auth.password_confirmed_at', now()->subHours(4)->timestamp);
        try {
            app(CashierShiftService::class)->close($this->sourceShift->id, '1000');
            $this->fail('Expired confirmation accepted');
        } catch (HttpException $e) {
            $this->assertSame(423, $e->getStatusCode());
        }
        $this->assertSame('open', $this->sourceShift->fresh()->status);
    }
}
