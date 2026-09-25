<?php

namespace App\Livewire\Pos;

use App\Models\Branch;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class BranchSaleTerminal extends SaleTerminal
{
    public function mount(Branch $branch): void
    {
        $this->branchId = $branch->id;
        $this->assertBranchAccess();
    }
}
