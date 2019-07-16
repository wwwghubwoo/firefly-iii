<?php
/**
 * BudgetTransformerTest.php
 * Copyright (c) 2018 thegrumpydictator@gmail.com
 *
 * This file is part of Firefly III.
 *
 * Firefly III is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Firefly III is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Firefly III. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Tests\Unit\Transformers;

use Carbon\Carbon;
use FireflyIII\Models\Budget;
use FireflyIII\Repositories\Budget\BudgetRepositoryInterface;
use FireflyIII\Transformers\BudgetTransformer;
use Log;
use Symfony\Component\HttpFoundation\ParameterBag;
use Tests\TestCase;


/**
 * Class BudgetTransformerTest
 */
class BudgetTransformerTest extends TestCase
{
    /**
     *
     */
    public function setUp(): void
    {
        parent::setUp();
        Log::info(sprintf('Now in %s.', \get_class($this)));
    }
    /**
     * Basic coverage
     *
     * @covers \FireflyIII\Transformers\BudgetTransformer
     */
    public function testBasic(): void
    {
        // mocks and prep:
        $repository  = $this->mock(BudgetRepositoryInterface::class);
        $parameters  = new ParameterBag;
        $budget      = Budget::first();
        $transformer = app(BudgetTransformer::class);
        $transformer->setParameters($parameters);

        // mocks
        $repository->shouldReceive('setUser')->once();

        // action
        $result = $transformer->transform($budget);


        $this->assertEquals($budget->id, $result['id']);
        $this->assertEquals((bool)$budget->active, $result['active']);
        $this->assertEquals([], $result['spent']);

    }

    /**
     * Basic coverage
     *
     * @covers \FireflyIII\Transformers\BudgetTransformer
     */
    public function testSpentArray(): void
    {
        // mocks and prep:
        $repository = $this->mock(BudgetRepositoryInterface::class);
        $parameters = new ParameterBag;

        // set parameters
        $parameters->set('start', new Carbon('2018-01-01'));
        $parameters->set('end', new Carbon('2018-01-31'));

        $budget      = Budget::first();
        $transformer = app(BudgetTransformer::class);
        $transformer->setParameters($parameters);

        // spent data
        $spent = [
            [
                'currency_id'             => 1,
                'currency_code'           => 'AKC',
                'currency_symbol'         => 'x',
                'currency_decimal_places' => 2,
                'amount'                  => 1000,
            ],
        ];

        // mocks
        $repository->shouldReceive('setUser')->once();
        $repository->shouldReceive('spentInPeriodMc')->atLeast()->once()->andReturn($spent);

        // action
        $result = $transformer->transform($budget);

        $this->assertEquals($budget->id, $result['id']);
        $this->assertEquals((bool)$budget->active, $result['active']);
        $this->assertEquals($spent, $result['spent']);

    }
}
