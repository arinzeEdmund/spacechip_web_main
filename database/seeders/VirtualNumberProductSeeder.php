<?php

namespace Database\Seeders;

use App\Models\VirtualNumberProduct;
use Illuminate\Database\Seeder;

class VirtualNumberProductSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'country_iso' => 'US',
                'label' => 'USA Local Number',
                'cap_sms' => true,
                'cap_voice' => true,
                'currency' => 'USD',
                'monthly_amount_minor' => 500,
                'twilio_search_filters' => [],
                'active' => true,
            ],
            [
                'country_iso' => 'GB',
                'label' => 'UK Local Number',
                'cap_sms' => true,
                'cap_voice' => true,
                'currency' => 'USD',
                'monthly_amount_minor' => 600,
                'twilio_search_filters' => [],
                'active' => true,
            ],
            [
                'country_iso' => 'CA',
                'label' => 'Canada Local Number',
                'cap_sms' => true,
                'cap_voice' => true,
                'currency' => 'USD',
                'monthly_amount_minor' => 500,
                'twilio_search_filters' => [],
                'active' => true,
            ],
        ];

        foreach ($items as $row) {
            VirtualNumberProduct::updateOrCreate(
                [
                    'country_iso' => $row['country_iso'],
                    'label' => $row['label'],
                ],
                $row
            );
        }
    }
}
