<?php

namespace App\Providers;

use App\Models\Character;
use Illuminate\Support\Facades\Auth;
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
        View::composer('layouts.app', function ($view) {
            $user = Auth::user();
            $characters = collect();
            $activeCharacter = null;
            if ($user) {
                $characters = $user->characters()->orderBy('name')->get();
                $activeId = session('active_character_id');
                if ($activeId) {
                    $activeCharacter = $characters->firstWhere('id', (int) $activeId);
                    if (! $activeCharacter) {
                        session()->forget('active_character_id');
                    }
                }
            }
            $view->with('sidebarCharacters', $characters)
                 ->with('activeCharacter', $activeCharacter);
        });
    }
}
