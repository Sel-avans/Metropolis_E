<?php

namespace App\Services;

use App\Models\activeEvent;

class simspeed
{
    public function calculateEffectiveDuration(int $baseDuration, float $simulationSpeed): float 
    {
        if ($simulationSpeed <= 0) {
            return (float) $baseDuration;
        }
        return $baseDuration / $simulationSpeed;
    }

    public function updateSpeedChange(activeEvent $activeEvent, float $oldSpeed, float $newSpeed): array
    {
        
        $now = now()->timestamp;
        
        $realSecondsPassed = $now - strtotime($activeEvent->last_updated_at);

        $simulatedSecondsPassed = $realSecondsPassed * $oldSpeed;

        $newRemainingBaseDuration = $activeEvent->remaining_base_duration - $simulatedSecondsPassed;
        
        if ($newRemainingBaseDuration < 0) {
            $newRemainingBaseDuration = 0;
        }

        $newRealSecondsRemaining = 0;
        if ($newSpeed > 0) {
            $newRealSecondsRemaining = $newRemainingBaseDuration / $newSpeed;
        }

        return [
            'remaining_base_duration' => (float) $newRemainingBaseDuration,
            'real_seconds_remaining'  => (int) round($newRealSecondsRemaining),
            
            'last_updated_at'         => now()->toDateTimeString()
        ];
    }

    public function isEventFinished(activeEvent $activeEvent, float $currentSpeed): bool
    {
       
        $now = now()->timestamp;
        
        $realSecondsPassed = $now - strtotime($activeEvent->last_updated_at);
        $simulatedSecondsPassed = $realSecondsPassed * $currentSpeed;

        $timeLeft = $activeEvent->remaining_base_duration - $simulatedSecondsPassed;

        return $timeLeft <= 1;
    }
}