<?php

namespace App\Jobs;

use App\Services\StockReservationService;
use Illuminate\Foundation\Bus\Dispatchable;

/** Deliberately NOT ShouldQueue — see PublishOutboxMessagesJob for why. */
class ReleaseExpiredReservationsJob
{
    use Dispatchable;

    public function handle(StockReservationService $reservations): void
    {
        $reservations->releaseExpired();
    }
}
