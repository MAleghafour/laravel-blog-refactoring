<?php

namespace App\Listeners;

use App\Jobs\SendWelcomeMailJob;
use Illuminate\Auth\Events\Registered;


class SendWelcomeMail
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //

    }

    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        SendWelcomeMailJob::dispatch(
            $event->user
        )->delay(now()->addMinutes(1));
    }
}
