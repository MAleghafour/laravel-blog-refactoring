<?php

namespace App\Providers;
use App\Events\UserSubscribed;
use App\Listeners\SendSubscriberEmail;
use App\Listeners\SendWelcomeMail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        Schema::defaultStringLength(191);
        Vite::macro('image',fn(string $assets)=>$this->asset("resources/assets/images/{$assets}"));
        //Register The Listeners
        //Event::listen(Registered::class,SendWelcomeMail::class);
        //Event::listen(UserSubscribed::class,SendSubscriberEmail::class);
    }
}
