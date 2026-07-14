<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['name' => 'شيكل', 'code' => 'ILS', 'value' => 1, 'value_to_ils' => 1],
            ['name' => 'دولار', 'code' => 'USD', 'value' => 1, 'value_to_ils' => 3.70],
            ['name' => 'دينار أردني', 'code' => 'JOD', 'value' => 1, 'value_to_ils' => 5.20],
            ['name' => 'يورو', 'code' => 'EUR', 'value' => 1, 'value_to_ils' => 4.00],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }
    }
}
