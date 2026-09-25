<?php

namespace Tests\Feature;

use App\Livewire\Settings\BirSettings;
use App\Models\BirSetting;
use App\Models\Branch;
use App\Models\InvoiceSequence;
use App\Models\User;
use App\Support\InvoiceNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class BirComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_and_activate_bir_settings(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(BirSettings::class)
            ->set('registeredName', 'Sniper Retail Corporation')
            ->set('tradeName', 'Sniper Mart')
            ->set('tin', '123-456-789-00000')
            ->set('branchCode', '00001')
            ->set('registeredAddress', 'General Santos City')
            ->set('rdoCode', '110')
            ->set('taxType', BirSetting::TAX_TYPE_VAT)
            ->set('vatRate', '12')
            ->set('invoicePrefix', 'SI-GSC-')
            ->set('startingNumber', '1001')
            ->set('endingNumber', '9999')
            ->set('isActive', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('bir_settings', [
            'registered_name' => 'Sniper Retail Corporation',
            'trade_name' => 'Sniper Mart',
            'branch_code' => '00001',
            'tax_type' => BirSetting::TAX_TYPE_VAT,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('invoice_sequences', [
            'branch_code' => '00001',
            'prefix' => 'SI-GSC-',
            'current_number' => 1000,
            'starting_number' => 1001,
            'ending_number' => 9999,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'bir.settings.updated',
        ]);
    }

    public function test_cashier_cannot_access_bir_settings(): void
    {
        $cashier = User::factory()->create();

        $this->actingAs($cashier)
            ->get(route('settings.bir', [], false))
            ->assertForbidden();
    }

    public function test_invoice_numbers_are_sequential_and_never_reused(): void
    {
        $this->configureBirInvoicing();
        $service = app(InvoiceNumberService::class);

        $first = DB::transaction(fn (): array => $service->next());
        $second = DB::transaction(fn (): array => $service->next());

        $this->assertSame('SI-GSC-000000000001', $first['invoice_number']);
        $this->assertSame('SI-GSC-000000000002', $second['invoice_number']);
        $this->assertSame(2, InvoiceSequence::query()->value('current_number'));
    }

    public function test_invoice_generation_stops_when_bir_settings_are_inactive(): void
    {
        $this->expectException(ValidationException::class);

        DB::transaction(fn (): array => app(InvoiceNumberService::class)->next());
    }

    public function test_branch_sequences_are_separate_and_do_not_fall_back_to_global_settings(): void
    {
        $this->configureBirInvoicing();
        $branch = Branch::factory()->create();
        $unconfigured = Branch::factory()->create();
        BirSetting::query()->create([
            'branch_id' => $branch->id,
            'registered_name' => 'South Store',
            'tin' => '123-456-789-00001',
            'branch_code' => '00002',
            'registered_address' => 'South Address',
            'tax_type' => BirSetting::TAX_TYPE_NON_VAT,
            'vat_rate' => 12,
            'is_active' => true,
        ]);
        InvoiceSequence::query()->create([
            'branch_id' => $branch->id,
            'document_type' => InvoiceSequence::TYPE_SALES_INVOICE,
            'branch_code' => '00002',
            'prefix' => 'SI-SOUTH-',
            'current_number' => 0,
            'starting_number' => 1,
            'is_active' => true,
        ]);

        $service = app(InvoiceNumberService::class);
        $south = DB::transaction(fn (): array => $service->next($branch));
        $legacy = DB::transaction(fn (): array => $service->next());

        $this->assertSame('SI-SOUTH-000000000001', $south['invoice_number']);
        $this->assertSame('South Store', $south['setting']->registered_name);
        $this->assertSame('SI-GSC-000000000001', $legacy['invoice_number']);
        $this->assertSame(1, InvoiceSequence::query()->where('branch_id', $branch->id)->value('current_number'));
        $this->assertSame(1, InvoiceSequence::query()->whereNull('branch_id')->value('current_number'));

        try {
            DB::transaction(fn (): array => $service->next($unconfigured));
            $this->fail('An unconfigured branch must not use the legacy invoice sequence.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cart', $exception->errors());
        }
    }

    public function test_saving_legacy_bir_settings_keeps_other_branch_active(): void
    {
        $this->configureBirInvoicing();
        $branch = Branch::factory()->create();
        $branchSetting = BirSetting::query()->create([
            'branch_id' => $branch->id,
            'registered_name' => 'South Store',
            'tin' => '123-456-789-00001',
            'branch_code' => '00002',
            'registered_address' => 'South Address',
            'tax_type' => BirSetting::TAX_TYPE_NON_VAT,
            'vat_rate' => 12,
            'is_active' => true,
        ]);

        $admin = User::factory()->admin()->create();
        Livewire::actingAs($admin)->test(BirSettings::class)
            ->set('registeredName', 'Original Store')
            ->call('save')->assertHasNoErrors();

        $this->assertTrue($branchSetting->fresh()->is_active);
        $this->assertSame('Original Store', BirSetting::query()->whereNull('branch_id')->firstOrFail()->registered_name);
    }

    private function configureBirInvoicing(): void
    {
        BirSetting::query()->create([
            'registered_name' => 'Sniper Retail Corporation',
            'tin' => '123-456-789-00000',
            'branch_code' => '00001',
            'registered_address' => 'General Santos City',
            'tax_type' => BirSetting::TAX_TYPE_VAT,
            'vat_rate' => 12,
            'is_active' => true,
        ]);

        InvoiceSequence::query()->create([
            'document_type' => InvoiceSequence::TYPE_SALES_INVOICE,
            'branch_code' => '00001',
            'prefix' => 'SI-GSC-',
            'current_number' => 0,
            'starting_number' => 1,
            'is_active' => true,
        ]);
    }
}
