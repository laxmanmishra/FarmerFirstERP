<?php

namespace Database\Seeders;

use App\Models\LookupValue;
use Illuminate\Database\Seeder;

/**
 * Initial configurable lists. Editable under System Settings → Lists.
 */
class LookupSeeder extends Seeder
{
    public function run(): void
    {
        $lists = [
            LookupValue::ENQUIRY_SOURCE => [
                'WALK_IN' => 'Walk-in / Showroom',
                'FIELD_VISIT' => 'Field visit',
                'PHONE' => 'Phone call',
                'REFERRAL' => 'Customer referral',
                'VILLAGE_CAMP' => 'Village camp / Mela',
                'SOCIAL_MEDIA' => 'Social media',
                'WEBSITE' => 'Website',
                'EXISTING_CUSTOMER' => 'Existing customer',
                'OTHER' => 'Other',
            ],
            LookupValue::CLOSE_REASON => [
                'BOUGHT_COMPETITOR' => 'Bought competitor brand',
                'PRICE' => 'Price too high',
                'FINANCE_REJECTED' => 'Finance not available',
                'POSTPONED' => 'Purchase postponed',
                'NOT_REACHABLE' => 'Customer not reachable',
                'NO_REQUIREMENT' => 'No real requirement',
                'OTHER' => 'Other',
            ],
            LookupValue::FOLLOW_UP_TYPE => [
                'CALL' => 'Phone call',
                'VISIT' => 'Field visit',
                'SHOWROOM' => 'Showroom visit',
                'WHATSAPP' => 'WhatsApp',
                'DEMO' => 'Tractor demo',
            ],
        ];

        foreach ($lists as $type => $values) {
            $order = 0;

            foreach ($values as $code => $name) {
                LookupValue::query()->firstOrCreate(['type' => $type, 'code' => $code], ['name' => $name, 'sort_order' => $order += 10]);
            }
        }
    }
}
