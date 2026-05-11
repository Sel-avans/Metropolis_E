<?php

namespace App\Observers;

use App\Models\CityFunction;
use App\Mail\NewFunctionNotification;
use Illuminate\Support\Facades\Mail;

class CityFunctionObserver
{
    /**
     * Handle the CityFunction "created" event.
     */
    public function created(CityFunction $cityFunction): void
    {
        // Check if this city function has not been notified yet
        if ($cityFunction->notified_at === null) {
            $expertEmail = 'expert@metropolis.test';

            // Send the notification email to the expert
            Mail::to($expertEmail)->send(new NewFunctionNotification($cityFunction));

            // Mark as notified and save without triggering events
            $cityFunction->notified_at = now();
            $cityFunction->saveQuietly();
        }
    }

    /**
     * Handle the CityFunction "updated" event.
     */
    public function updated(CityFunction $cityFunction): void
    {
        //
    }

    /**
     * Handle the CityFunction "deleted" event.
     */
    public function deleted(CityFunction $cityFunction): void
    {
        //
    }

    /**
     * Handle the CityFunction "restored" event.
     */
    public function restored(CityFunction $cityFunction): void
    {
        //
    }

    /**
     * Handle the CityFunction "force deleted" event.
     */
    public function forceDeleted(CityFunction $cityFunction): void
    {
        //
    }
}
