<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\DestinationController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\PreferenceController;
use App\Http\Controllers\Api\RecommendationController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/health', function () {
    return ['status' => 'ok'];
});

Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/preferences', [PreferenceController::class, 'show']);
    Route::put('/preferences', [PreferenceController::class, 'update']);
    Route::post('/preferences/skip', [PreferenceController::class, 'skip']);

    Route::get('/recommendations', [RecommendationController::class, 'index']);
    Route::post('/attractions/{attraction}/feedback', [RecommendationController::class, 'feedback']);

    Route::post('/budgets/estimate', [BudgetController::class, 'estimate']);
    Route::get('/budgets/{budget}/suggestions', [BudgetController::class, 'suggestions']);
    Route::apiResource('budgets', BudgetController::class);
    Route::post('/budgets/{budget}/expenses', [ExpenseController::class, 'store']);
    Route::delete('/budgets/{budget}/expenses/{expense}', [ExpenseController::class, 'destroy']);
});

Route::get('/destinations', [DestinationController::class, 'index']);
Route::get('/destinations/{destination}', [DestinationController::class, 'show']);

// R15: language helper and cultural tips (browsing is public, favorites need a login).
Route::get('/phrases/languages', [\App\Http\Controllers\Api\PhraseController::class, 'languages']);
Route::get('/phrases', [\App\Http\Controllers\Api\PhraseController::class, 'index']);
Route::get('/cultural-tips/countries', [\App\Http\Controllers\Api\PhraseController::class, 'countries']);
Route::get('/cultural-tips', [\App\Http\Controllers\Api\PhraseController::class, 'tips']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/phrases/favorites', [\App\Http\Controllers\Api\PhraseController::class, 'favorites']);
    Route::post('/phrases/{phrase}/favorite', [\App\Http\Controllers\Api\PhraseController::class, 'favorite']);
    Route::delete('/phrases/{phrase}/favorite', [\App\Http\Controllers\Api\PhraseController::class, 'unfavorite']);
});

// R9: flight and stay search, price alerts and bookings (flight data is admin-managed).
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/flights/options', [\App\Http\Controllers\Api\FlightController::class, 'options']);
    Route::get('/flights/search', [\App\Http\Controllers\Api\FlightController::class, 'search']);
    Route::post('/flights/multi-city', [\App\Http\Controllers\Api\FlightController::class, 'multiCity']);
    Route::get('/flights/price-calendar', [\App\Http\Controllers\Api\FlightController::class, 'priceCalendar']);
    Route::get('/price-alerts', [\App\Http\Controllers\Api\FlightController::class, 'alerts']);
    Route::post('/price-alerts', [\App\Http\Controllers\Api\FlightController::class, 'storeAlert']);
    Route::delete('/price-alerts/{priceAlert}', [\App\Http\Controllers\Api\FlightController::class, 'destroyAlert']);
    Route::get('/stays/options', [\App\Http\Controllers\Api\AccommodationController::class, 'options']);
    Route::get('/stays/search', [\App\Http\Controllers\Api\AccommodationController::class, 'search']);
    Route::get('/stays/{accommodation}', [\App\Http\Controllers\Api\AccommodationController::class, 'show']);
    Route::apiResource('bookings', \App\Http\Controllers\Api\BookingController::class);

    Route::middleware(\App\Http\Middleware\EnsureUserIsAdmin::class)->prefix('admin')->group(function () {
        Route::get('/flights', [\App\Http\Controllers\Api\FlightController::class, 'adminIndex']);
        Route::post('/flights', [\App\Http\Controllers\Api\FlightController::class, 'adminStore']);
        Route::put('/flights/{flight}', [\App\Http\Controllers\Api\FlightController::class, 'adminUpdate']);
        Route::delete('/flights/{flight}', [\App\Http\Controllers\Api\FlightController::class, 'adminDestroy']);
    });
});

// R8: spend plan vs actual and the variance report (expenses are added through R7's expense routes).
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/currencies', [\App\Http\Controllers\Api\SpendTrackerController::class, 'currencies']);
    Route::get('/budgets/{budget}/deviation', [\App\Http\Controllers\Api\SpendTrackerController::class, 'show']);
    Route::put('/budgets/{budget}/tracking', [\App\Http\Controllers\Api\SpendTrackerController::class, 'updateTracking']);
    Route::get('/budgets/{budget}/variance-report', [\App\Http\Controllers\Api\SpendTrackerController::class, 'report']);
    Route::get('/budgets/{budget}/variance-report.csv', [\App\Http\Controllers\Api\SpendTrackerController::class, 'reportCsv']);
});
