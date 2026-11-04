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

// R13: language helper and cultural tips (browsing is public, favorites need a login).
Route::get('/phrases/languages', [\App\Http\Controllers\Api\PhraseController::class, 'languages']);
Route::get('/phrases', [\App\Http\Controllers\Api\PhraseController::class, 'index']);
Route::get('/cultural-tips/countries', [\App\Http\Controllers\Api\PhraseController::class, 'countries']);
Route::get('/cultural-tips', [\App\Http\Controllers\Api\PhraseController::class, 'tips']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/phrases/favorites', [\App\Http\Controllers\Api\PhraseController::class, 'favorites']);
    Route::post('/phrases/{phrase}/favorite', [\App\Http\Controllers\Api\PhraseController::class, 'favorite']);
    Route::delete('/phrases/{phrase}/favorite', [\App\Http\Controllers\Api\PhraseController::class, 'unfavorite']);
});
