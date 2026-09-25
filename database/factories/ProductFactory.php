<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-######')),
            'name' => fake()->words(3, true),
            'cost_price' => 10,
            'selling_price' => 15,
            'stock_quantity' => 0,
            'low_stock_level' => 5,
            'status' => Product::STATUS_ACTIVE,
        ];
    }
}
