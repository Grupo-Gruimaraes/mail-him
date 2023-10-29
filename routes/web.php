<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/campaigns', [App\Http\Controllers\CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/campaigns-create', function(){ return view('campaigns.create'); });
    Route::post('/campaigns-create-store', [App\Http\Controllers\CampaignController::class, 'campaignStore'])->name('campaigns.store');
    Route::get('/campaigns-postback-cron-form', [App\Http\Controllers\CampaignController::class, 'showPostbackCronForm'])->name('campaigns.postback-cron-form');
    Route::post('/campaigns-postback-cron', [App\Http\Controllers\CampaignController::class, 'postbackCron'])->name('campaigns.postback.cron');
});

Route::post('/temporary-webhook', 'CampaignController@temporaryWebhook');

require __DIR__.'/auth.php';
