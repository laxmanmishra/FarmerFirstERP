<?php

namespace Database\Seeders;

use App\Models\NumberSeries;
use Illuminate\Database\Seeder;

/**
 * Default document numbering (SRS v6.1 §8). Formats are editable in
 * Administration → System Settings; confirm them during business sign-off.
 */
class NumberSeriesSeeder extends Seeder
{
    public function run(): void
    {
        $fy = NumberSeries::RESET_FINANCIAL_YEAR;
        $never = NumberSeries::RESET_NEVER;

        $series = [
            // entity => [name, prefix, format, padding, reset, per_branch]
            'employee' => ['Employee', 'EMP', '{prefix}{seq}', 4, $never, false],
            'farmer' => ['Farmer', 'FRM', '{prefix}{seq}', 6, $never, false],
            'customer' => ['Customer', 'CUS', '{prefix}{seq}', 6, $never, false],
            'enquiry' => ['Enquiry', 'ENQ', '{prefix}/{fy}/{seq}', 5, $fy, false],
            'quotation' => ['Quotation', 'QT', '{prefix}/{fy}/{seq}', 5, $fy, false],
            'deal' => ['Deal', 'DL', '{prefix}/{fy}/{seq}', 5, $fy, false],
            'order' => ['Order / Booking', 'ORD', '{prefix}/{fy}/{seq}', 5, $fy, false],
            'fulfilment' => ['Fulfilment', 'FUL', '{prefix}/{fy}/{seq}', 5, $fy, false],
            'finance_file' => ['Finance File', 'FIN', '{prefix}/{fy}/{seq}', 5, $fy, false],
            'account_file' => ['Account File', 'ACC', '{prefix}/{fy}/{seq}', 5, $fy, false],
            'payment' => ['Payment', 'PAY', '{prefix}/{fy}/{seq}', 6, $fy, false],
            'refund' => ['Refund', 'REF', '{prefix}/{fy}/{seq}', 5, $fy, false],
            'receipt' => ['Receipt', 'RCP', '{prefix}/{branch}/{fy}/{seq}', 5, $fy, true],
            'rto_file' => ['RTO File', 'RTO', '{prefix}/{fy}/{seq}', 5, $fy, false],
            'insurance_file' => ['Insurance File', 'INS', '{prefix}/{fy}/{seq}', 5, $fy, false],
            'pdi_file' => ['PDI File', 'PDI', '{prefix}/{fy}/{seq}', 5, $fy, false],
            'delivery' => ['Delivery', 'DLV', '{prefix}/{fy}/{seq}', 5, $fy, false],
            'waiver' => ['Waiver', 'WVR', '{prefix}/{fy}/{seq}', 5, $fy, false],
            'document' => ['Document', 'DOC', '{prefix}{seq}', 8, $never, false],
            'stock_inward' => ['Stock Inward / GRN', 'GRN', '{prefix}/{fy}/{seq}', 5, $fy, false],
        ];

        foreach ($series as $entity => [$name, $prefix, $format, $padding, $reset, $perBranch]) {
            NumberSeries::query()->firstOrCreate(['entity' => $entity], [
                'name' => $name,
                'prefix' => $prefix,
                'format' => $format,
                'padding' => $padding,
                'reset_policy' => $reset,
                'per_branch' => $perBranch,
            ]);
        }
    }
}
