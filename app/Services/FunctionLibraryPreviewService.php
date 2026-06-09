<?php

namespace App\Services;

use App\Models\AdjacencyRule;
use App\Models\CityFunction;
use App\Models\Condition;

class FunctionLibraryPreviewService
{
    public const EFFECT_CATEGORIES = [
        'safety' => 'Safety',
        'recreation' => 'Recreation',
        'environment' => 'Environment',
        'amenities' => 'Amenities',
        'mobility' => 'Mobility',
    ];

    private const CONDITION_TYPE_LABELS = [
        'bonus' => 'Bonus',
        'penalty' => 'Penalty',
        'forbidden' => 'Forbidden',
        'trait' => 'Trait',
        'none' => 'None',
    ];

    private const CONDITION_SORT_ORDER = [
        'forbidden' => 0,
        'penalty' => 1,
        'bonus' => 2,
        'trait' => 3,
        'none' => 4,
    ];

    public function build(CityFunction $function): array
    {
        $function->loadMissing('effects');

        return [
            'function' => [
                'id' => $function->id,
                'name' => $function->name,
                'category' => $function->category,
                'image' => $function->image ? asset($function->image) : null,
            ],
            'effects' => $this->buildEffects($function),
            'conditions' => $this->buildConditions($function),
        ];
    }

    private function buildEffects(CityFunction $function): array
    {
        $effectsByCategory = $function->effects->keyBy('category');

        return collect(self::EFFECT_CATEGORIES)->map(function (string $label, string $category) use ($effectsByCategory) {
            $effect = $effectsByCategory->get($category);
            $value = $effect?->value;

            return [
                'category' => $category,
                'label' => $label,
                'value' => $value,
                'display_value' => $this->formatEffectValue($value),
                'value_tone' => $this->effectValueTone($value),
            ];
        })->values()->all();
    }

    private function formatEffectValue(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        $numeric = (int) $value;

        if ($numeric > 0) {
            return '+'.$numeric;
        }

        return (string) $numeric;
    }

    private function effectValueTone(mixed $value): string
    {
        if ($value === null) {
            return 'missing';
        }

        $numeric = (int) $value;

        if ($numeric > 0) {
            return 'positive';
        }

        if ($numeric < 0) {
            return 'negative';
        }

        return 'neutral';
    }

    private function buildConditions(CityFunction $function): array
    {
        $conditions = Condition::with(['functionA', 'functionB'])
            ->where(function ($query) use ($function) {
                $query->where('function_a', $function->id)
                    ->orWhere('function_b', $function->id);
            })
            ->get();

        $adjacencyRules = AdjacencyRule::with(['a', 'b'])
            ->where(function ($query) use ($function) {
                $query->where('function_a', $function->id)
                    ->orWhere('function_b', $function->id);
            })
            ->get();

        $items = [];

        foreach ($conditions as $rule) {
            $items[] = $this->formatRule($function, $rule->function_a, $rule->function_b, $rule->type, $rule->value, $rule->functionA, $rule->functionB);
        }

        foreach ($adjacencyRules as $rule) {
            $items[] = $this->formatRule($function, $rule->function_a, $rule->function_b, $rule->type, $rule->value, $rule->a, $rule->b);
        }

        $items = $this->dedupeConditions($items);

        if ($function->sensitivity === 'sensitive') {
            $items[] = $this->formatTrait('Sensitive', 'Receives a penalty when placed adjacent to polluting destinations.');
        }

        if ($function->pollution === 'polluting') {
            $items[] = $this->formatTrait('Polluting', 'Causes a penalty to sensitive destinations when placed adjacent.');
        }

        if ($items === []) {
            $items[] = $this->emptyCondition();
        }

        usort($items, function (array $a, array $b) {
            $orderA = self::CONDITION_SORT_ORDER[$a['type']] ?? 99;
            $orderB = self::CONDITION_SORT_ORDER[$b['type']] ?? 99;

            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            return strcmp($a['partner_name'] ?? '', $b['partner_name'] ?? '');
        });

        return $items;
    }

    private function emptyCondition(): array
    {
        return [
            'partner_id' => null,
            'partner_name' => null,
            'type' => 'none',
            'type_label' => self::CONDITION_TYPE_LABELS['none'],
            'value' => null,
            'display_value' => '—',
            'description' => 'No placement conditions defined.',
        ];
    }

    private function formatRule(
        CityFunction $function,
        int $functionA,
        int $functionB,
        string $type,
        mixed $value,
        ?CityFunction $partnerA,
        ?CityFunction $partnerB,
    ): array {
        $partner = $functionA === $function->id ? $partnerB : $partnerA;
        $partnerName = $partner?->name ?? 'Unknown destination';

        return [
            'partner_id' => $partner?->id,
            'partner_name' => $partnerName,
            'type' => $type,
            'type_label' => self::CONDITION_TYPE_LABELS[$type] ?? ucfirst($type),
            'value' => $value,
            'display_value' => $this->formatConditionValue($type, $value),
            'description' => $this->describeCondition($function->name, $partnerName, $type, $value),
        ];
    }

    private function formatTrait(string $label, string $description): array
    {
        return [
            'partner_id' => null,
            'partner_name' => null,
            'type' => 'trait',
            'type_label' => self::CONDITION_TYPE_LABELS['trait'],
            'value' => null,
            'display_value' => $label,
            'description' => $description,
        ];
    }

    private function formatConditionValue(string $type, mixed $value): string
    {
        return match ($type) {
            'forbidden' => 'Forbidden',
            'bonus' => $this->formatEffectValue($value),
            'penalty' => $this->formatEffectValue($value),
            default => $value === null ? '—' : (string) $value,
        };
    }

    private function describeCondition(string $name, string $partnerName, string $type, mixed $value): string
    {
        return match ($type) {
            'forbidden' => "Cannot be placed adjacent to {$partnerName}.",
            'bonus' => "Bonus {$this->formatEffectValue($value)} QoL when adjacent to {$partnerName}.",
            'penalty' => "Penalty {$this->formatEffectValue($value)} QoL when adjacent to {$partnerName}.",
            default => "Rule with {$partnerName}.",
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function dedupeConditions(array $items): array
    {
        $seen = [];
        $unique = [];

        foreach ($items as $item) {
            $valueKey = ($item['type'] ?? '') === 'forbidden' ? '' : ($item['value'] ?? '');

            $key = implode('|', [
                $item['partner_id'] ?? 'trait',
                $item['type'],
                $valueKey,
            ]);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $item;
        }

        return $unique;
    }
}
