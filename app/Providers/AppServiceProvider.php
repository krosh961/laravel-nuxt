<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\Resource;
use Illuminate\Support\ServiceProvider;

// use Validator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Carbon::setLocale(config('app.locale'));
        Resource::withoutWrapping();

        // Validator::extend('cyrillic', function ($attribute, $value, $parameters, $validator) {
        //     return preg_match('/[А-Яа-яЁё]/u', $value);
        // });;
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
