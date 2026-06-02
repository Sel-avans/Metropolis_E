<?php

namespace App\Services;

use App\Models\SimulationEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EventModifierService
{
    /** Times in the database are stored/parsed in this timezone (matches config app.timezone). */
    public const STORAGE_TIMEZONE = 'UTC';

    /** Shown to users in the UI (Netherlands). */
    public const DISPLAY_TIMEZONE = 'Europe/Amsterdam';

    public const CATEGORY_KEYS = [
        'safety',
        'recreation',
        'environment',
        'amenities',
        'mobility',
    ];

    public static function storageTimezone(): string
    {
        return (string) config('app.timezone', self::STORAGE_TIMEZONE);
    }

    public static function now(): Carbon
    {
        return Carbon::now(self::storageTimezone());
    }

    public static function parseMoment($value): Carbon
    {
        return Carbon::parse($value, self::storageTimezone());
    }

    public static function formatForDisplay($value): string
    {
        return self::parseMoment($value)
            ->timezone(self::DISPLAY_TIMEZONE)
            ->format('d-m-Y H:i');
    }

    /** Value for HTML datetime-local inputs (shown in Dutch time). */
    public static function toDatetimeLocalValue($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::parseMoment($value)
            ->timezone(self::DISPLAY_TIMEZONE)
            ->format('Y-m-d\TH:i');
    }

    /** Parse datetime-local form input (Dutch time) and store in app/DB timezone (UTC). */
    public static function fromDatetimeLocalInput(mixed $value): ?string
    {
        $local = self::asDatetimeLocalString($value);
        if ($local === null || $local === '') {
            return null;
        }

        return Carbon::parse($local, self::DISPLAY_TIMEZONE)
            ->timezone(self::storageTimezone())
            ->format('Y-m-d H:i:s');
    }

    /** @return non-empty-string|null */
    public static function asDatetimeLocalString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            // Laravel's date rule parses in app timezone (UTC), preserving wall-clock digits.
            return $value->format('Y-m-d\TH:i');
        }

        $str = (string) $value;

        if (str_contains($str, 'T')) {
            return substr($str, 0, 16);
        }

        return str_replace(' ', 'T', substr($str, 0, 16));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeEventMoments(array $data): array
    {
        if (($data['type'] ?? '') !== 'one-off') {
            return $data;
        }

        foreach (['start_moment', 'end_moment'] as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                continue;
            }

            $data[$field] = self::fromDatetimeLocalInput($data[$field]);
        }

        return $data;
    }

    public static function isActive(SimulationEvent $event, ?Carbon $now = null): bool
    {
        $now = $now ?? self::now();

        if ($event->type === 'one-off' && $event->start_moment && $event->end_moment) {
            $start = self::parseMoment($event->start_moment);
            $end = self::parseMoment($event->end_moment);

            return $now->greaterThanOrEqualTo($start) && $now->lessThanOrEqualTo($end);
        }

        if ($event->type === 'recurring') {
            return true;
        }

        return false;
    }

    public static function getActiveEvents(): Collection
    {
        $now = self::now();

        return SimulationEvent::with(['categoryEffects', 'effects'])
            ->get()
            ->filter(fn (SimulationEvent $event) => self::isActive($event, $now))
            ->values();
    }

    /**
     * @return array<string, float>
     */
    public static function getModifiersByCategory(): array
    {
        $modifiers = array_fill_keys(self::CATEGORY_KEYS, 0.0);

        foreach (self::getActiveEvents() as $event) {
            foreach ($event->categoryEffects as $effect) {
                $catKey = strtolower($effect->category);
                if (isset($modifiers[$catKey])) {
                    $modifiers[$catKey] += (float) $effect->value;
                }
            }
        }

        return $modifiers;
    }

    /**
     * Event category modifiers that apply to a specific city function on the grid.
     *
     * @return array<string, float>
     */
    public static function getModifiersByCategoryForFunction(int $functionId): array
    {
        $modifiers = array_fill_keys(self::CATEGORY_KEYS, 0.0);

        foreach (self::getActiveEvents() as $event) {
            if (!self::eventAppliesToFunction($event, $functionId)) {
                continue;
            }

            foreach ($event->categoryEffects as $effect) {
                $catKey = strtolower($effect->category);
                if (isset($modifiers[$catKey])) {
                    $modifiers[$catKey] += (float) $effect->value;
                }
            }
        }

        return $modifiers;
    }

    public static function eventAppliesToFunction(SimulationEvent $event, int $functionId): bool
    {
        return $event->effects->contains(
            fn ($eventEffect) => (int) $eventEffect->city_function_id === $functionId
        );
    }

    /**
     * @return array<int, array{event_name: string, category: string, value: float}>
     */
    public static function getModifierBreakdownForFunction(int $functionId): array
    {
        $breakdown = [];

        foreach (self::getActiveEvents() as $event) {
            if (!self::eventAppliesToFunction($event, $functionId)) {
                continue;
            }

            foreach ($event->categoryEffects as $effect) {
                $catKey = strtolower($effect->category);
                if (!in_array($catKey, self::CATEGORY_KEYS, true)) {
                    continue;
                }

                $breakdown[] = [
                    'event_name' => $event->name,
                    'category' => $catKey,
                    'value' => (float) $effect->value,
                ];
            }
        }

        return $breakdown;
    }

    /**
     * @return array<int, array{event_name: string, category: string, value: float}>
     */
    public static function getModifierBreakdown(): array
    {
        $breakdown = [];

        foreach (self::getActiveEvents() as $event) {
            foreach ($event->categoryEffects as $effect) {
                $catKey = strtolower($effect->category);
                if (!in_array($catKey, self::CATEGORY_KEYS, true)) {
                    continue;
                }

                $breakdown[] = [
                    'event_name' => $event->name,
                    'category' => $catKey,
                    'value' => (float) $effect->value,
                ];
            }
        }

        return $breakdown;
    }

    public static function applyToTotals(array &$totals, array &$categories): void
    {
        foreach (self::getModifierBreakdown() as $modifier) {
            $catKey = $modifier['category'];
            $value = $modifier['value'];

            if ($value == 0 || !isset($totals[$catKey])) {
                continue;
            }

            $categories[$catKey][] = [
                'function' => "{$modifier['event_name']} (event)",
                'value' => $value,
            ];
            $totals[$catKey] += $value;
        }
    }

}
