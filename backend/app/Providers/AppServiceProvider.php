<?php

namespace App\Providers;

use App\Models\NewsItem;
use Illuminate\Support\Facades\Route;
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
        // Explicit route model binding for NewsItem
        // This ensures both {news} and {newsItem} route parameters resolve to NewsItem
        Route::model('news', NewsItem::class);
        Route::model('newsItem', NewsItem::class);
    }
}
