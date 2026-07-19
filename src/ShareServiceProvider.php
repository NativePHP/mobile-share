<?php

namespace Native\Mobile\Providers;

use Illuminate\Support\ServiceProvider;
use Native\Mobile\Providers\Testing\ShareMacros;
use Native\Mobile\Share;
use Native\Mobile\Testing\FakeBridge;

class ShareServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Share::class, function () {
            return new Share;
        });

        // Test sugar (assertSharedUrl() etc.) — only under a test runner, and
        // only on a core whose FakeBridge is macroable (the method_exists
        // guard keeps older v4 and v3 cores fatal-free).
        if ($this->app->runningUnitTests()
            && class_exists(FakeBridge::class)
            && method_exists(FakeBridge::class, 'macro')) {
            ShareMacros::register();
        }
    }
}
