<?php

namespace App\Services;

use App\Models\SimulationEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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
    public static function fromDatetimeLocalInput(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value, self::DISPLAY_TIMEZONE)
            ->timezone(self::storageTimezone())
            ->format('Y-m-d H:i:s');
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

        if (array_key_exists('start_moment', $data)) {
            $data['start_moment'] = self::fromDatetimeLocalInput($data['start_moment']);
        }

        if (array_key_exists('end_moment', $data)) {
            $data['end_moment'] = self::fromDatetimeLocalInput($data['end_moment']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function attributesForPersistence(array $data): array
    {
        $data = self::normalizeEventMoments($data);

        $base = [
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? null,
        ];

        if (($data['type'] ?? '') === 'one-off') {
            return array_merge($base, [
                'start_moment' => $data['start_moment'] ?? null,
                'end_moment' => $data['end_moment'] ?? null,
            ], self::clearedRecurringAttributes());
        }

        return array_merge($base, [
            'start_moment' => null,
            'end_moment' => null,
            'recurring_schedule' => $data['recurring_schedule'] ?? null,
            'recurring_start_date' => $data['recurring_start_date'] ?? null,
            'recurring_end_date' => $data['recurring_end_date'] ?? null,
            'recurring_start_time' => $data['recurring_start_time'] ?? null,
            'recurring_end_time' => $data['recurring_end_time'] ?? null,
        ]);
    }

    /**
     * @return array<string, null>
     */
    private static function clearedRecurringAttributes(): array
    {
        if (! Schema::hasColumn('simulation_events', 'recurring_start_date')) {
            return [];
        }

        return [
            'recurring_schedule' => null,
            'recurring_start_date' => null,
            'recurring_end_date' => null,
            'recurring_start_time' => null,
            'recurring_end_time' => null,
        ];
    }

    public static function isActive(SimulationEvent $event, ?Carbon $now = null): bool
    {
        $now = $now ?? self::now();

        if ($event->type === 'one-off' && $event->start_moment && $event->end_moment) {
            $start = self::parseMoment($event->start_moment);
            $end = self::parseMoment($event->end_moment);

            return $now->greaterThanOrEqualTo($start) && $now->lessThan($end);
        }

        if ($event->type === 'recurring') {
            return self::isRecurringActive($event, $now);
        }

        return false;
    }

    public static function isTracked(SimulationEvent $event, ?Carbon $now = null): bool
    {
        $now = $now ?? self::now();

        if ($event->type === 'one-off') {
            if (!$event->end_moment) {
                return false;
            }

            return self::parseMoment($event->end_moment)->gt($now);
        }

        if ($event->type === 'recurring') {
            if ($event->recurring_end_date) {
                $end = Carbon::parse($event->recurring_end_date, self::storageTimezone())->endOfDay();

                return $now->lessThanOrEqualTo($end);
            }

            return true;
        }

        return false;
    }

    public static function getTrackedEvents(?Carbon $now = null): Collection
    {
        $now = $now ?? self::now();

        return SimulationEvent::with('effects')
            ->get()
            ->filter(fn (SimulationEvent $event) => self::isTracked($event, $now))
            ->values();
    }

    public static function getActiveEvents(): Collection
    {
        $now = self::now();

        return self::getTrackedEvents($now)
            ->filter(fn (SimulationEvent $event) => self::isActive($event, $now))
            ->values();
    }

    /**
     * @return array<string, float|int|string|bool|null|array<string, float>>
     */
    public static function formatEventForClient(SimulationEvent $event, Carbon $now): array
    {
        $isActive = self::isActive($event, $now);
        $modifiers = [];

        if ($isActive) {
            foreach ($event->categoryEffects as $effect) {
                $catKey = strtolower($effect->category);
                $modifiers[$catKey] = ($modifiers[$catKey] ?? 0) + (float) $effect->value;
            }
        }

        $timing = null;

        if ($event->type === 'one-off' && $event->end_moment) {
            $end = self::parseMoment($event->end_moment);

            if ($isActive) {
                $mins = max(0, (int) round($now->diffInMinutes($end, false)));
                $timing = 'Ends in ' . $mins . ' min';
            } elseif ($event->start_moment) {
                $start = self::parseMoment($event->start_moment);
                $mins = max(0, (int) round($start->diffInMinutes($now, false)));
                $timing = 'Starts in ' . $mins . ' min';
            }
        }

        if ($event->type === 'recurring') {
            $timing = 'Pattern: ' . ($event->recurring_schedule ?? 'unknown');
        }

        return [
            'id' => $event->id,
            'name' => $event->name,
            'type' => $event->type,
            'is_active' => $isActive,
            'timing' => $timing,
            'start_at' => $event->start_moment
                ? self::parseMoment($event->start_moment)->timestamp
                : null,
            'end_at' => $event->end_moment
                ? self::parseMoment($event->end_moment)->timestamp
                : null,
            'starts_at_display' => $event->start_moment
                ? self::formatForDisplay($event->start_moment)
                : null,
            'ends_at_display' => $event->end_moment
                ? self::formatForDisplay($event->end_moment)
                : null,
            'modifiers' => $modifiers === [] ? (object) [] : $modifiers,
        ];
    }

    private static function isRecurringActive(SimulationEvent $event, Carbon $now): bool
    {
        if (!self::isTracked($event, $now)) {
            return false;
        }

        if ($event->recurring_start_date) {
            $start = Carbon::parse($event->recurring_start_date, self::storageTimezone())->startOfDay();

            if ($now->lessThan($start)) {
                return false;
            }
        }

        if (!$event->recurring_start_time || !$event->recurring_end_time) {
            return true;
        }

        $local = $now->copy()->timezone(self::DISPLAY_TIMEZONE);
        $today = $local->format('Y-m-d');
        $windowStart = Carbon::parse("{$today} {$event->recurring_start_time}", self::DISPLAY_TIMEZONE);
        $windowEnd = Carbon::parse("{$today} {$event->recurring_end_time}", self::DISPLAY_TIMEZONE);

        if ($windowEnd->lessThanOrEqualTo($windowStart)) {
            $windowEnd->addDay();
        }

        return $local->greaterThanOrEqualTo($windowStart) && $local->lessThan($windowEnd);
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
     * @return array<int, array{name: string, category: string, value: float}>
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

    /**
     * @param  int  $functionId
     * @return array<string, float>
     */
    public static function getModifiersByCategoryForFunction(int $functionId): array
    {
        $modifiers = array_fill_keys(self::CATEGORY_KEYS, 0.0);

        foreach (self::getActiveEvents() as $event) {
            $applies = $event->effects->contains(fn ($e) => ($e->city_function_id ?? $e->city_function_id) == $functionId);
            if (! $applies) {
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

    // The Fix for the grid details & update not working. V

    /**
     * @param  int  $functionId
     * @return array<int, array{event_name: string, category: string, value: float}>
     */
    public static function getModifierBreakdownForFunction(int $functionId): array
    {
        $breakdown = [];

        foreach (self::getActiveEvents() as $event) {
            $applies = $event->effects->contains(fn ($e) => ($e->city_function_id ?? $e->city_function_id) == $functionId);
            if (! $applies) {
                continue;
            }

            foreach ($event->categoryEffects as $effect) {
                $catKey = strtolower($effect->category);
                if (! in_array($catKey, self::CATEGORY_KEYS, true)) {
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

}
