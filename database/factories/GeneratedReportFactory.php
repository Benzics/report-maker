<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GeneratedReport>
 */
class GeneratedReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'user_id' => User::factory(),
            'name' => $this->faker->words(3, true) . ' Report',
            'description' => $this->faker->sentence(),
            'selected_columns' => $this->faker->randomElements([
                'customer_name', 'order_date', 'product_name', 'quantity', 'price', 'total'
            ], $this->faker->numberBetween(2, 4)),
            'filter_column' => $this->faker->randomElement(['status', 'category', 'region']),
            'filter_value' => $this->faker->word(),
            'file_path' => 'reports/' . $this->faker->uuid() . '.xlsx',
            'file_name' => $this->faker->words(2, true) . '_report.xlsx',
            'file_size' => $this->faker->numberBetween(1024, 10240), // 1KB to 10KB
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'is_saved' => true,
        ];
    }
}