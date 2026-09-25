<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BranchProduct> */
class BranchProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'product_id' => Product::factory(),
            'on_hand' => 0,
            'reserved' => 0,
            'reorder_level' => 5,
            'is_available' => true,
        ];
    }
}
