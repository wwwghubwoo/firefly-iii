<?php
/**
 * RuleTransformer.php
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


use FireflyIII\Models\Rule;
use FireflyIII\Models\RuleAction;
use FireflyIII\Models\RuleTrigger;
use FireflyIII\Repositories\Rule\RuleRepositoryInterface;
use Log;

/**
 * Class RuleTransformer
 */
class RuleTransformer extends AbstractTransformer
{
    /** @var RuleRepositoryInterface */
    private $ruleRepository;

    /**
     * CurrencyTransformer constructor.
     *
     * @codeCoverageIgnore
     */
    public function __construct()
    {
        $this->ruleRepository = app(RuleRepositoryInterface::class);
        if ('testing' === config('app.env')) {
            Log::warning(sprintf('%s should not be instantiated in the TEST environment!', \get_class($this)));
        }
    }

    /**
     * Transform the rule.
     *
     * @param Rule $rule
     *
     * @return array
     */
    public function transform(Rule $rule): array
    {
        $this->ruleRepository->setUser($rule->user);

        $data = [
            'id'              => (int)$rule->id,
            'created_at'      => $rule->created_at->toAtomString(),
            'updated_at'      => $rule->updated_at->toAtomString(),
            'rule_group_id'   => (int)$rule->rule_group_id,
            'title'           => $rule->title,
            'description'     => $rule->description,
            'order'           => (int)$rule->order,
            'active'          => $rule->active,
            'strict'          => $rule->strict,
            'stop_processing' => $rule->stop_processing,
            'triggers'        => $this->triggers($rule),
            'actions'         => $this->actions($rule),
            'links'           => [
                [
                    'rel' => 'self',
                    'uri' => '/rules/' . $rule->id,
                ],
            ],
        ];

        return $data;
    }

    /**
     * @param Rule $rule
     *
     * @return array
     */
    private function actions(Rule $rule): array
    {
        $result  = [];
        $actions = $this->ruleRepository->getRuleActions($rule);
        /** @var RuleAction $ruleAction */
        foreach ($actions as $ruleAction) {
            $result[] = [
                'id'              => (int)$ruleAction->id,
                'created_at'      => $ruleAction->created_at->toAtomString(),
                'updated_at'      => $ruleAction->updated_at->toAtomString(),
                'type'            => $ruleAction->action_type,
                'value'           => $ruleAction->action_value,
                'order'           => $ruleAction->order,
                'active'          => $ruleAction->active,
                'stop_processing' => $ruleAction->stop_processing,
            ];
        }

        return $result;
    }

    /**
     * @param Rule $rule
     *
     * @return array
     */
    private function triggers(Rule $rule): array
    {
        $result   = [];
        $triggers = $this->ruleRepository->getRuleTriggers($rule);
        /** @var RuleTrigger $ruleTrigger */
        foreach ($triggers as $ruleTrigger) {
            $result[] = [
                'id'              => (int)$ruleTrigger->id,
                'created_at'      => $ruleTrigger->created_at->toAtomString(),
                'updated_at'      => $ruleTrigger->updated_at->toAtomString(),
                'type'            => $ruleTrigger->trigger_type,
                'value'           => $ruleTrigger->trigger_value,
                'order'           => $ruleTrigger->order,
                'active'          => $ruleTrigger->active,
                'stop_processing' => $ruleTrigger->stop_processing,
            ];
        }

        return $result;
    }
}
