<?php

/**
 * Cron.php
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

namespace FireflyIII\Console\Commands;

use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Support\Cronjobs\RecurringCronjob;
use Illuminate\Console\Command;

/**
 * Class Cron
 *
 * @codeCoverageIgnore
 */
class Cron extends Command
{
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs all Firefly III cron-job related commands. Configure a cron job according to the official Firefly III documentation.';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firefly:cron';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): int
    {
        $recurring = new RecurringCronjob;
        try {
            $result = $recurring->fire();
        } catch (FireflyException $e) {
            $this->error($e->getMessage());

            return 0;
        }
        if (false === $result) {
            $this->line('The recurring transaction cron job did not fire.');
        }
        if (true === $result) {
            $this->line('The recurring transaction cron job fired successfully.');
        }

        $this->info('More feedback on the cron jobs can be found in the log files.');

        return 0;
    }


}
