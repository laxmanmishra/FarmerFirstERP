<?php

namespace Database\Seeders;

use App\Actions\Enquiries\CreateEnquiry;
use App\Actions\Enquiries\EnquiryData;
use App\Actions\Farmers\SaveFarmer;
use App\Actions\FollowUps\ScheduleFollowUp;
use App\Actions\Pipeline\MoveEnquiryStage;
use App\Actions\Telecaller\ClaimEnquiry;
use App\Actions\Telecaller\RecordCallAttempt;
use App\Actions\Territory\AssignTerritory;
use App\Enums\TerritoryLevel;
use App\Models\District;
use App\Models\Enquiry;
use App\Models\Farmer;
use App\Models\Product;
use App\Models\User;
use App\Models\Village;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Development-only CRM sample data created through the real Actions, so it obeys
 * the same rules (numbering, duplicates, territory assignment, workflow history).
 */
class DemoCrmSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo CRM data must never be seeded in production.');
        }

        if (Enquiry::query()->exists()) {
            return;
        }

        $owner = User::query()->where('email', 'owner@farmerfirst.test')->firstOrFail();
        $dwarika = User::query()->where('email', 'dwarika@farmerfirst.test')->firstOrFail();
        $ramesh = User::query()->where('email', 'salesman2@farmerfirst.test')->firstOrFail();
        $telecaller = User::query()->where('email', 'telecaller@farmerfirst.test')->firstOrFail();

        Auth::setUser($owner);
        $territory = app(AssignTerritory::class);
        $territory->handle($dwarika->employee, TerritoryLevel::District, District::query()->where('name', 'Sehore')->value('id'), true, now()->subMonths(6));
        $territory->handle($ramesh->employee, TerritoryLevel::District, District::query()->where('name', 'Raisen')->value('id'), true, now()->subMonths(6));

        $people = [
            ['Ramkishan Patel', 'Mohanlal Patel', '9826011001', 'Bilkisganj'],
            ['Shivcharan Meena', 'Harnarayan Meena', '9826011002', 'Shyampur'],
            ['Bhagwan Singh Thakur', 'Ranjit Singh', '9826011003', 'Arniya'],
            ['Kailash Yadav', 'Babulal Yadav', '9826011004', 'Sanchi'],
            ['Govind Prasad Sahu', 'Nathuram Sahu', '9826011005', 'Pipaliya'],
            ['Sunita Devi Kushwaha', 'Ramesh Kushwaha', '9826011006', 'Jhagariya'],
            ['Mukesh Lodhi', 'Kanhaiyalal Lodhi', '9826011007', 'Deopura'],
            ['Harish Chandra Verma', 'Shankar Verma', '9826011008', 'Mainwada'],
        ];

        $saveFarmer = app(SaveFarmer::class);
        $farmers = [];

        foreach ($people as [$name, $father, $mobile, $villageName]) {
            $village = Village::query()->where('name', $villageName)->firstOrFail();
            $farmers[] = $saveFarmer->handle([
                'name' => $name, 'father_name' => $father, 'mobile' => $mobile, 'whatsapp_number' => $mobile,
                'village_id' => $village->id, 'land_acres' => random_int(3, 40),
            ], $owner->currentBranch);
        }

        $tractors = Product::query()->where('product_type', 'tractor')->get();
        $create = app(CreateEnquiry::class);
        $sources = ['WALK_IN', 'FIELD_VISIT', 'PHONE', 'REFERRAL', 'VILLAGE_CAMP'];
        $enquiries = [];

        foreach ($farmers as $index => $farmer) {
            /** @var Farmer $farmer */
            $creator = $index % 3 === 0 ? $telecaller : ($farmer->village->tehsil->district->name === 'Sehore' ? $dwarika : $ramesh);
            Auth::setUser($creator);
            $product = $tractors[$index % $tractors->count()];

            $enquiries[] = $create->handle($creator, $farmer, EnquiryData::fromArray([
                'source_code' => $sources[$index % count($sources)],
                'deal_type' => $index === 2 ? 'exchange' : 'new',
                'expected_purchase_date' => today()->addDays([1, 4, 9, 20, 35, 3, 12, 60][$index])->toDateString(),
                'budget' => (string) (600000 + $index * 25000),
                'requirements' => [['requirement_type' => 'tractor', 'brand_id' => $product->brand_id, 'product_id' => $product->id, 'quantity' => 1]],
                'exchange' => ['brand_name' => 'Eicher', 'model_name' => '380', 'manufacturing_year' => 2012, 'hours_used' => 6500, 'customer_expected_price' => '210000'],
            ]));
        }

        // Telecaller validates the first five; two of them progress in the pipeline.
        Auth::setUser($telecaller);
        $claim = app(ClaimEnquiry::class);
        $call = app(RecordCallAttempt::class);
        $valid = WorkflowStage::findByCode(WorkflowDefinition::ENQUIRY_VALIDATION, 'VALID');
        $callback = WorkflowStage::findByCode(WorkflowDefinition::ENQUIRY_VALIDATION, 'CALLBACK');

        foreach (array_slice($enquiries, 0, 5) as $enquiry) {
            $claim->handle($telecaller->employee, $enquiry);
            $call->handle($telecaller, $enquiry->fresh(), $valid, 'Confirmed requirement and budget.', 180);
        }

        $claim->handle($telecaller->employee, $enquiries[5]);
        $call->handle($telecaller, $enquiries[5]->fresh(), $callback, 'Asked to call back in the evening.', 45, now()->addHours(5));

        Auth::setUser($dwarika);
        $move = app(MoveEnquiryStage::class);
        $move->handle($dwarika, $enquiries[0]->fresh(), WorkflowStage::findByCode(WorkflowDefinition::SALES_PIPELINE, 'NEGOTIATION'), 'Discussed exchange and finance.');
        $move->handle($dwarika, $enquiries[1]->fresh(), WorkflowStage::findByCode(WorkflowDefinition::SALES_PIPELINE, 'QUOTATION_REQUIRED'));

        $schedule = app(ScheduleFollowUp::class);
        $schedule->handle($dwarika, $enquiries[0]->fresh(), $dwarika->employee, 'VISIT', now()->addHours(3), 'Visit farm with price offer');
        $schedule->handle($dwarika, $enquiries[1]->fresh(), $dwarika->employee, 'CALL', now()->addDay()->setTime(11, 0), 'Share quotation');

        Auth::logout();
    }
}
