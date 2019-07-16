<?php
/**
 * RecurringTransactionTransformer.php
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

namespace FireflyIII\Transformers;


use Carbon\Carbon;
use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Factory\CategoryFactory;
use FireflyIII\Models\Recurrence;
use FireflyIII\Models\RecurrenceMeta;
use FireflyIII\Models\RecurrenceRepetition;
use FireflyIII\Models\RecurrenceTransaction;
use FireflyIII\Models\RecurrenceTransactionMeta;
use FireflyIII\Repositories\Bill\BillRepositoryInterface;
use FireflyIII\Repositories\Budget\BudgetRepositoryInterface;
use FireflyIII\Repositories\PiggyBank\PiggyBankRepositoryInterface;
use FireflyIII\Repositories\Recurring\RecurringRepositoryInterface;
use Log;

/**
 *
 * Class RecurringTransactionTransformer
 */
class RecurrenceTransformer extends AbstractTransformer
{
    /** @var BillRepositoryInterface */
    private $billRepos;
    /** @var BudgetRepositoryInterface */
    private $budgetRepos;
    /** @var CategoryFactory */
    private $factory;
    /** @var PiggyBankRepositoryInterface */
    private $piggyRepos;
    /** @var RecurringRepositoryInterface */
    private $repository;

    /**
     * RecurrenceTransformer constructor.
     *
     * @codeCoverageIgnore
     */
    public function __construct()
    {
        $this->repository  = app(RecurringRepositoryInterface::class);
        $this->billRepos   = app(BillRepositoryInterface::class);
        $this->piggyRepos  = app(PiggyBankRepositoryInterface::class);
        $this->factory     = app(CategoryFactory::class);
        $this->budgetRepos = app(BudgetRepositoryInterface::class);

        if ('testing' === config('app.env')) {
            Log::warning(sprintf('%s should not be instantiated in the TEST environment!', \get_class($this)));
        }
    }

    /**
     * Transform the recurring transaction.
     *
     * @param Recurrence $recurrence
     *
     * @return array
     * @throws FireflyException
     */
    public function transform(Recurrence $recurrence): array
    {
        Log::debug('Now in Recurrence::transform()');
        $this->repository->setUser($recurrence->user);
        $this->billRepos->setUser($recurrence->user);
        $this->piggyRepos->setUser($recurrence->user);
        $this->factory->setUser($recurrence->user);
        $this->budgetRepos->setUser($recurrence->user);

        $shortType = (string)config(sprintf('firefly.transactionTypesToShort.%s', $recurrence->transactionType->type));

        // basic data.
        $return = [
            'id'                     => (int)$recurrence->id,
            'created_at'             => $recurrence->created_at->toAtomString(),
            'updated_at'             => $recurrence->updated_at->toAtomString(),
            'transaction_type_id'    => $recurrence->transaction_type_id,
            'transaction_type'       => $shortType,
            'title'                  => $recurrence->title,
            'description'            => $recurrence->description,
            'first_date'             => $recurrence->first_date->format('Y-m-d'),
            'latest_date'            => null === $recurrence->latest_date ? null : $recurrence->latest_date->format('Y-m-d'),
            'repeat_until'           => null === $recurrence->repeat_until ? null : $recurrence->repeat_until->format('Y-m-d'),
            'apply_rules'            => $recurrence->apply_rules,
            'active'                 => $recurrence->active,
            'repetitions'            => $recurrence->repetitions,
            'notes'                  => $this->repository->getNoteText($recurrence),
            'recurrence_repetitions' => $this->getRepetitions($recurrence),
            'transactions'           => $this->getTransactions($recurrence),
            'meta'                   => $this->getMeta($recurrence),
            'links'                  => [
                [
                    'rel' => 'self',
                    'uri' => '/recurring/' . $recurrence->id,
                ],
            ],
        ];


        return $return;
    }

    /**
     * @param Recurrence $recurrence
     *
     * @return array
     */
    private function getMeta(Recurrence $recurrence): array
    {
        $return     = [];
        $collection = $recurrence->recurrenceMeta;
        Log::debug(sprintf('Meta collection length = %d', $collection->count()));
        /** @var RecurrenceMeta $recurrenceMeta */
        foreach ($collection as $recurrenceMeta) {
            $recurrenceMetaArray = [
                'name'  => $recurrenceMeta->name,
                'value' => $recurrenceMeta->value,
            ];
            switch ($recurrenceMeta->name) {
                case 'tags':
                    $recurrenceMetaArray['tags'] = explode(',', $recurrenceMeta->value);
                    break;
                case 'bill_id':
                    $bill = $this->billRepos->find((int)$recurrenceMeta->value);
                    if (null !== $bill) {
                        $recurrenceMetaArray['bill_id']   = $bill->id;
                        $recurrenceMetaArray['bill_name'] = $bill->name;
                    }
                    break;
                case 'piggy_bank_id':
                    $piggy = $this->piggyRepos->findNull((int)$recurrenceMeta->value);
                    if (null !== $piggy) {
                        $recurrenceMetaArray['piggy_bank_id']   = $piggy->id;
                        $recurrenceMetaArray['piggy_bank_name'] = $piggy->name;
                    }
                    break;
            }
            // store meta date in recurring array
            $return[] = $recurrenceMetaArray;
        }

        return $return;
    }

    /**
     * @param Recurrence $recurrence
     *
     * @return array
     * @throws FireflyException
     */
    private function getRepetitions(Recurrence $recurrence): array
    {
        $fromDate = $recurrence->latest_date ?? $recurrence->first_date;
        // date in the past? use today:
        $today    = new Carbon;
        $fromDate = $fromDate->lte($today) ? $today : $fromDate;
        $return   = [];

        /** @var RecurrenceRepetition $repetition */
        foreach ($recurrence->recurrenceRepetitions as $repetition) {
            $repetitionArray = [
                'id'          => $repetition->id,
                'created_at'  => $repetition->created_at->toAtomString(),
                'updated_at'  => $repetition->updated_at->toAtomString(),
                'type'        => $repetition->repetition_type,
                'moment'      => $repetition->repetition_moment,
                'skip'        => (int)$repetition->repetition_skip,
                'weekend'     => (int)$repetition->weekend,
                'description' => $this->repository->repetitionDescription($repetition),
                'occurrences' => [],
            ];

            // get the (future) occurrences for this specific type of repetition:
            $occurrences = $this->repository->getXOccurrences($repetition, $fromDate, 5);
            /** @var Carbon $carbon */
            foreach ($occurrences as $carbon) {
                $repetitionArray['occurrences'][] = $carbon->format('Y-m-d');
            }

            $return[] = $repetitionArray;
        }

        return $return;
    }

    /**
     * @param RecurrenceTransaction $transaction
     *
     * @return array
     */
    private function getTransactionMeta(RecurrenceTransaction $transaction): array
    {
        $return = [];
        // get meta data for each transaction:
        /** @var RecurrenceTransactionMeta $transactionMeta */
        foreach ($transaction->recurrenceTransactionMeta as $transactionMeta) {
            $transactionMetaArray = [
                'name'  => $transactionMeta->name,
                'value' => $transactionMeta->value,
            ];
            switch ($transactionMeta->name) {
                case 'category_name':
                    $category = $this->factory->findOrCreate(null, $transactionMeta->value);
                    if (null !== $category) {
                        $transactionMetaArray['category_id']   = $category->id;
                        $transactionMetaArray['category_name'] = $category->name;
                    }
                    break;
                case 'budget_id':
                    $budget = $this->budgetRepos->findNull((int)$transactionMeta->value);
                    if (null !== $budget) {
                        $transactionMetaArray['budget_id']   = $budget->id;
                        $transactionMetaArray['budget_name'] = $budget->name;
                    }
                    break;
            }
            // store transaction meta data in transaction
            $return[] = $transactionMetaArray;
        }

        return $return;
    }

    /**
     * @param Recurrence $recurrence
     *
     * @return array
     * @throws FireflyException
     */
    private function getTransactions(Recurrence $recurrence): array
    {
        $return = [];
        // get all transactions:
        /** @var RecurrenceTransaction $transaction */
        foreach ($recurrence->recurrenceTransactions as $transaction) {

            $sourceAccount         = $transaction->sourceAccount;
            $destinationAccount    = $transaction->destinationAccount;
            $foreignCurrencyCode   = null;
            $foreignCurrencySymbol = null;
            $foreignCurrencyDp     = null;
            if (null !== $transaction->foreign_currency_id) {
                $foreignCurrencyCode   = $transaction->foreignCurrency->code;
                $foreignCurrencySymbol = $transaction->foreignCurrency->symbol;
                $foreignCurrencyDp     = $transaction->foreignCurrency->decimal_places;
            }
            $amount        = round($transaction->amount, $transaction->transactionCurrency->decimal_places);
            $foreignAmount = null;
            if (null !== $transaction->foreign_currency_id && null !== $transaction->foreign_amount) {
                $foreignAmount = round($transaction->foreign_amount, $foreignCurrencyDp);
            }
            $transactionArray = [
                'currency_id'                     => $transaction->transaction_currency_id,
                'currency_code'                   => $transaction->transactionCurrency->code,
                'currency_symbol'                 => $transaction->transactionCurrency->symbol,
                'currency_decimal_places'         => $transaction->transactionCurrency->decimal_places,
                'foreign_currency_id'             => $transaction->foreign_currency_id,
                'foreign_currency_code'           => $foreignCurrencyCode,
                'foreign_currency_symbol'         => $foreignCurrencySymbol,
                'foreign_currency_decimal_places' => $foreignCurrencyDp,
                'source_id'                       => $transaction->source_id,
                'source_name'                     => null === $sourceAccount ? '' : $sourceAccount->name,
                'destination_id'                  => $transaction->destination_id,
                'destination_name'                => null === $destinationAccount ? '' : $destinationAccount->name,
                'amount'                          => $amount,
                'foreign_amount'                  => $foreignAmount,
                'description'                     => $transaction->description,
                'meta'                            => $this->getTransactionMeta($transaction),
            ];
            if (null !== $transaction->foreign_currency_id) {
                $transactionArray['foreign_currency_code']           = $transaction->foreignCurrency->code;
                $transactionArray['foreign_currency_symbol']         = $transaction->foreignCurrency->symbol;
                $transactionArray['foreign_currency_decimal_places'] = $transaction->foreignCurrency->decimal_places;
            }

            // store transaction in recurrence array.
            $return[] = $transactionArray;
        }

        return $return;
    }

}
