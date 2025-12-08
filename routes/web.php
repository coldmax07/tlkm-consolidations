<?php
// routes/web.php

use App\Http\Controllers\Api\FiscalYearController;
use App\Http\Controllers\Api\LegWorkflowController;
use App\Http\Controllers\Api\PeriodGenerationController;
use App\Http\Controllers\Api\PeriodLockController;
use App\Http\Controllers\Api\StatementController;
use App\Http\Controllers\Api\StatementMetaController;
use App\Http\Controllers\Api\TemplateLookupController;
use App\Http\Controllers\Api\ThreadController;
use App\Http\Controllers\Api\TransactionTemplateController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Auth endpoints (JSON responses for SPA)
 */
Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
        'remember' => ['nullable', 'boolean'],
    ]);

    if (Auth::attempt([
        'email' => $credentials['email'],
        'password' => $credentials['password'],
    ], (bool)($credentials['remember'] ?? false))) {
        $request->session()->regenerate();
        return response()->json(['ok' => true]);
    }

    return response()->json([
        'ok' => false,
        'message' => 'The provided credentials are incorrect.'
    ], 422);
})->name('login');

Route::get('/csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
});

Route::post('/logout', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return response()->json(['ok' => true]);
})->middleware('auth');

Route::get('/user', function (Request $request) {
    if ($request->user()) {
        return response()->json($request->user());
    }
    return response()->json(['message' => 'Unauthenticated.'], 401);
});

/**
 * Example protected data route (server-side)
 */
Route::get('/admin/data/summary', function () {
    return ['cards' => [
        ['title' => 'KPI One', 'value' => 42],
        ['title' => 'KPI Two', 'value' => 7],
    ]];
})->middleware('auth');

Route::middleware('auth')->prefix('api')->group(function () {
    Route::get('/templates/meta', TemplateLookupController::class);
    Route::apiResource('templates', TransactionTemplateController::class);
    Route::post('/periods/{period}/generate-transactions', PeriodGenerationController::class);
    Route::get('/fiscal-years', [FiscalYearController::class, 'index']);
    Route::post('/fiscal-years', [FiscalYearController::class, 'store']);
    Route::post('/fiscal-years/{fiscalYear}/close', [FiscalYearController::class, 'close']);
    Route::post('/periods/{period}/lock', [PeriodLockController::class, 'lock']);
    Route::post('/periods/{period}/unlock', [PeriodLockController::class, 'unlock']);
    Route::get('/statements/meta', StatementMetaController::class);
    Route::get('/statements', [StatementController::class, 'index']);
    Route::patch('/legs/{leg}', [LegWorkflowController::class, 'update']);
    Route::post('/legs/{leg}/submit', [LegWorkflowController::class, 'submit']);
    Route::post('/legs/{leg}/approve', [LegWorkflowController::class, 'approve']);
    Route::post('/legs/{leg}/reject', [LegWorkflowController::class, 'reject']);
    Route::patch('/legs/{leg}/receiver', [LegWorkflowController::class, 'updateReceiver']);
    Route::post('/legs/{leg}/receiver/submit', [LegWorkflowController::class, 'submitReceiver']);
    Route::post('/legs/{leg}/receiver/approve', [LegWorkflowController::class, 'approveReceiver']);
    Route::post('/legs/{leg}/receiver/reject', [LegWorkflowController::class, 'rejectReceiver']);
    Route::get('/transactions/{transaction}/thread', [ThreadController::class, 'show']);
    Route::post('/transactions/{transaction}/messages', [ThreadController::class, 'storeMessage']);
    Route::post('/messages/{message}/attachments', [ThreadController::class, 'storeAttachment']);
    Route::get('/reports', \App\Http\Controllers\Api\ReportController::class);
    Route::get('/reports/export', [\App\Http\Controllers\Api\ReportController::class, 'export']);
    Route::get('/reports/export-pdf', [\App\Http\Controllers\Api\ReportController::class, 'exportPdf']);
    Route::get('/dashboard', \App\Http\Controllers\Api\DashboardController::class);
});

/**
 * SPA shell for all front-end routes
 * (keep this LAST so it doesn’t swallow /login POST)
 */
Route::get('/{any}', fn() => view('app'))
    ->where('any', '.*');
