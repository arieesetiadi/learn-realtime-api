<?php

declare(strict_types=1);
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

arch()->preset()->php();
arch()->preset()->security();

arch('application code uses strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('services do not depend on HTTP concerns')
    ->expect('App\Services')
    ->not->toUse([
        Request::class,
        Auth::class,
        Gate::class,
    ]);

arch('controllers do not query the database directly')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        DB::class,
    ]);

arch('services are final')
    ->expect('App\Services')
    ->toBeFinal();
