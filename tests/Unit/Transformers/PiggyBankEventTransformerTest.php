<?php
/**
 * PiggyBankEventTransformerTest.php
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

use Amount;
use FireflyIII\Models\PiggyBankEvent;
use FireflyIII\Models\TransactionCurrency;
use FireflyIII\Repositories\Account\AccountRepositoryInterface;
use FireflyIII\Repositories\Currency\CurrencyRepositoryInterface;
use FireflyIII\Repositories\PiggyBank\PiggyBankRepositoryInterface;
use FireflyIII\Transformers\PiggyBankEventTransformer;
use Log;
use Mockery;
use Symfony\Component\HttpFoundation\ParameterBag;
use Tests\TestCase;

/**
 * Class PiggyBankEventTransformerTest
 */
class PiggyBankEventTransformerTest extends TestCase
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
     * Basic test with no meta data.
     *
     * @covers \FireflyIII\Transformers\PiggyBankEventTransformer
     */
    public function testBasic(): void
    {
        // repositories
        $currencyRepos = $this->mock(CurrencyRepositoryInterface::class);
        $piggyRepos    = $this->mock(PiggyBankRepositoryInterface::class);
        $accountRepos  = $this->mock(AccountRepositoryInterface::class);

        $currencyRepos->shouldReceive('setUser')->atLeast()->once();
        $piggyRepos->shouldReceive('setUser')->atLeast()->once();
        $accountRepos->shouldReceive('setUser')->atLeast()->once();

        // mock calls:
        $accountRepos->shouldReceive('getMetaValue')->withArgs([Mockery::any(), 'currency_id'])->atLeast()->once()->andReturn(1);
        $currencyRepos->shouldReceive('findNull')->withArgs([1])->atLeast()->once()->andReturn(TransactionCurrency::find(1));
        $piggyRepos->shouldReceive('getTransactionWithEvent')->atLeast()->once()->andReturn(123);

        $event       = PiggyBankEvent::first();
        $transformer = app(PiggyBankEventTransformer::class);
        $transformer->setParameters(new ParameterBag);

        $result = $transformer->transform($event);
        $this->assertEquals($event->id, $result['id']);
        $this->assertEquals(245, $result['amount']);
        $this->assertEquals(123, $result['transaction_id']);

    }

    /**
     * Basic test with no currency info.
     *
     * @covers \FireflyIII\Transformers\PiggyBankEventTransformer
     */
    public function testNoCurrency(): void
    {
        // repositories
        $currencyRepos = $this->mock(CurrencyRepositoryInterface::class);
        $piggyRepos    = $this->mock(PiggyBankRepositoryInterface::class);
        $accountRepos  = $this->mock(AccountRepositoryInterface::class);

        $currencyRepos->shouldReceive('setUser')->atLeast()->once();
        $piggyRepos->shouldReceive('setUser')->atLeast()->once();
        $accountRepos->shouldReceive('setUser')->atLeast()->once();

        // mock calls:
        $accountRepos->shouldReceive('getMetaValue')->withArgs([Mockery::any(), 'currency_id'])->atLeast()->once()->andReturn(1);
        $currencyRepos->shouldReceive('findNull')->withArgs([1])->atLeast()->once()->andReturn(null);
        $piggyRepos->shouldReceive('getTransactionWithEvent')->atLeast()->once()->andReturn(123);

        Amount::shouldReceive('getDefaultCurrencyByUser')->andReturn(TransactionCurrency::find(1))->atLeast()->once();

        $event       = PiggyBankEvent::first();
        $transformer = app(PiggyBankEventTransformer::class);
        $transformer->setParameters(new ParameterBag);

        $result = $transformer->transform($event);
        $this->assertEquals($event->id, $result['id']);
        $this->assertEquals(245, $result['amount']);
        $this->assertEquals(123, $result['transaction_id']);

    }
}
