<?php

namespace App\Providers;

use App\Listeners\EnsureMesDossiersRacineExists;
use App\Notifications\Channels\GedSmsChannel;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        Event::listen(Registered::class, [EnsureMesDossiersRacineExists::class, 'handleRegistered']);
        Event::listen(Login::class, [EnsureMesDossiersRacineExists::class, 'handleLogin']);

        Paginator::defaultView('pagination::tailwind');
        Paginator::defaultSimpleView('pagination::simple-tailwind');

        View::composer('partials.header', function ($view) {
            $unreadCount = auth()->check()
                ? auth()->user()->unreadNotifications()->count()
                : 0;
            $view->with('unreadNotificationsCount', $unreadCount);
        });

        Notification::extend('ged_sms', fn ($app) => $app->make(GedSmsChannel::class));
    }
}
