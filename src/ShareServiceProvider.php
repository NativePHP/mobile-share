<?php

namespace Native\Mobile\Providers;

use Illuminate\Support\ServiceProvider;
use Native\Mobile\Share;

class ShareServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Share::class, function () {
            return new Share;
        });
    }
}
