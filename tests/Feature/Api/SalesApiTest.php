<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCrmData;
use Tests\Concerns\CreatesSalesData;
use Tests\TestCase;

class SalesApiTest extends TestCase
{
    use CreatesCrmData, CreatesSalesData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_customer_and_deal_lookups_respect_visibility(): void
    {
        $salesman = $this->crmUser('Salesman');
        $deal = $this->win($salesman, $this->validatedEnquiry($salesman));
        $token = $salesman->createToken('phone')->plainTextToken;

        $this->withToken($token)->getJson(route('api.v1.customers.index'))
            ->assertOk()->assertJsonPath('data.0.customer_no', $deal->customer->customer_no)->assertJsonPath('meta.pagination.total', 1);
        $this->withToken($token)->getJson(route('api.v1.customers.show', $deal->customer_id))
            ->assertOk()->assertJsonPath('data.deals.0.deal_no', $deal->deal_no)->assertJsonPath('data.deals.0.stage.code', 'DRAFT');
        $this->withToken($token)->getJson(route('api.v1.deals.index'))->assertOk()->assertJsonCount(1, 'data');

        $other = $this->crmUser('Salesman')->createToken('phone')->plainTextToken;
        $this->app['auth']->forgetGuards(); // the guard caches the resolved user within one test
        $this->withToken($other)->getJson(route('api.v1.customers.show', $deal->customer_id))->assertNotFound();
        $this->withToken($other)->getJson(route('api.v1.deals.index'))->assertOk()->assertJsonCount(0, 'data');
    }
}
