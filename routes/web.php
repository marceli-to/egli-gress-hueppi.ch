<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Home page (accessible to all)
Volt::route('/', 'pages.home')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('dashboard', 'pages.dashboard')->name('dashboard');
    Volt::route('games', 'pages.games.index')->name('games');
    Volt::route('ranking', 'pages.ranking')->name('ranking');
    Volt::route('special-tipps', 'pages.special-tipps')->name('special-tipps');
    Volt::route('groups', 'pages.groups.index')->name('groups');
    Volt::route('stats', 'pages.stats')->name('stats');
    Volt::route('profile', 'pages.profile')->name('profile');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Volt::route('games', 'pages.admin.games')->name('admin.games');
});

require __DIR__.'/auth.php';
