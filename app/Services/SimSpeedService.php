<?php

namespace App\Services;

use App\Models\SimulationEvent;
use Illuminate\Support\Carbon;

class SimSpeedService
{
    public function calculateEffectiveDuration(int $baseDuration, float $simulationSpeed): float 
    {
        if ($simulationSpeed <= 0) {
            return (float) $baseDuration;
        }
        return $baseDuration / $simulationSpeed;
    }

    public function updateSpeedChange(SimulationEvent $activeEvent, float $oldSpeed, float $newSpeed): array
    {
        $now = now();
        $lastUpdated = Carbon::parse($activeEvent->last_updated_at);
        
        $realSecondsPassed = $now->diffInSeconds($lastUpdated);
        $simulatedSecondsPassed = $realSecondsPassed * $oldSpeed;

        $newRemainingBaseDuration = max(0, $activeEvent->remaining_base_duration - $simulatedSecondsPassed);
        
        $newRealSecondsRemaining = ($newSpeed > 0) ? ($newRemainingBaseDuration / $newSpeed) : 0;

        return [
            'remaining_base_duration' => (float) $newRemainingBaseDuration,
            'real_seconds_remaining'  => (int) round($newRealSecondsRemaining),
            'last_updated_at'         => $now->toDateTimeString()
        ];
    }

    public function isEventFinished(SimulationEvent $activeEvent, float $currentSpeed): bool
    {
        $now = now();
        $lastUpdated = Carbon::parse($activeEvent->last_updated_at);
        
        $realSecondsPassed = $now->diffInSeconds($lastUpdated);
        $simulatedSecondsPassed = $realSecondsPassed * $currentSpeed;

        $timeLeft = $activeEvent->remaining_base_duration - $simulatedSecondsPassed;

        return $timeLeft <= 0;
    }
}