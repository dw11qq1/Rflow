<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use Illuminate\Broadcasting\BroadcastServiceProvider;

return [
    BroadcastServiceProvider::class,
    AppServiceProvider::class,
    FortifyServiceProvider::class,
];
