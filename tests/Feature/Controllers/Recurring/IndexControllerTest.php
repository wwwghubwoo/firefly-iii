<?php
/**
 * IndexControllerTest.php
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

namespace Tests\Feature\Controllers\Recurring;

use FireflyIII\Factory\CategoryFactory;
use FireflyIII\Models\Configuration;
use FireflyIII\Repositories\Budget\BudgetRepositoryInterface;
use FireflyIII\Repositories\Recurring\RecurringRepositoryInterface;
use FireflyIII\Repositories\User\UserRepositoryInterface;
use FireflyIII\Transformers\RecurrenceTransformer;
use Illuminate\Support\Collection;
use Log;
use Mockery;
use Tests\TestCase;

/**
 *
 * Class IndexControllerTest
 */
class IndexControllerTest extends TestCase
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
     * @covers \FireflyIII\Http\Controllers\Recurring\IndexController
     */
    public function testIndex(): void
    {

        $repository      = $this->mock(RecurringRepositoryInterface::class);
        $budgetRepos     = $this->mock(BudgetRepositoryInterface::class);
        $userRepos       = $this->mock(UserRepositoryInterface::class);
        $categoryFactory = $this->mock(CategoryFactory::class);
        $transformer     = $this->mock(RecurrenceTransformer::class);

        $transformer->shouldReceive('setParameters')->atLeast()->once();
        $transformer->shouldReceive('transform')->atLeast()->once()->andReturn(
            [
                'id' => 5,
                'first_date' => '2018-01-01',
                'repeat_until' =>null,
                'latest_date' => null,
            ]
        );

        $userRepos->shouldReceive('hasRole')->withArgs([Mockery::any(), 'owner'])->atLeast()->once()->andReturn(true);

        $config       = new Configuration;
        $config->data = 0;

        $falseConfig       = new Configuration;
        $falseConfig->data = false;

        $collection = $this->user()->recurrences()->take(2)->get();

        // mock cron job config:
        \FireflyConfig::shouldReceive('get')->withArgs(['last_rt_job', 0])->once()->andReturn($config);
        \FireflyConfig::shouldReceive('get')->withArgs(['is_demo_site', false])->once()->andReturn($falseConfig);

        $repository->shouldReceive('get')->andReturn($collection)->once();


        $this->be($this->user());
        $response = $this->get(route('recurring.index'));
        $response->assertStatus(200);
        $response->assertSee('<ol class="breadcrumb">');
    }

    public function testShow(): void
    {
        $repository      = $this->mock(RecurringRepositoryInterface::class);
        $budgetRepos     = $this->mock(BudgetRepositoryInterface::class);
        $userRepos       = $this->mock(UserRepositoryInterface::class);
        $categoryFactory = $this->mock(CategoryFactory::class);
        $transformer     = $this->mock(RecurrenceTransformer::class);

        $transformer->shouldReceive('setParameters')->atLeast()->once();
        $transformer->shouldReceive('transform')->atLeast()->once()->andReturn(
            [
                'id' => 5,
                'first_date' => '2018-01-01',
                'repeat_until' =>null,
                'latest_date' => null,
                'recurrence_repetitions' => [],
            ]
        );

        $userRepos->shouldReceive('hasRole')->withArgs([Mockery::any(), 'owner'])->atLeast()->once()->andReturn(true);

        $recurrence = $this->user()->recurrences()->first();
        $repository->shouldReceive('setUser');
        $repository->shouldReceive('getTransactions')->andReturn(new Collection)->atLeast()->once();

        $this->be($this->user());
        $response = $this->get(route('recurring.show', [$recurrence->id]));
        $response->assertStatus(200);
        $response->assertSee('<ol class="breadcrumb">');
    }


}
