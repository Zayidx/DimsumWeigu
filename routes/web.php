<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\PublicSite\Home;

Route::get('/', Home::class)->name('home');
