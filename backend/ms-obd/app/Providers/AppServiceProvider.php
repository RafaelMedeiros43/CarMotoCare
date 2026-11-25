<?php

declare(strict_types=1);

namespace App\Providers;

use App\Clients\PhpMqttPublisher;
use App\Contracts\MqttPublisherInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Nos testes substitui-se esta ligação por um fake, para não precisar de broker.
        $this->app->singleton(MqttPublisherInterface::class, PhpMqttPublisher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
