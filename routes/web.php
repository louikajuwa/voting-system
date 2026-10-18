<?php

use App\Http\Controllers\Admin\CandidateController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ElectionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ResultsController;
use App\Http\Controllers\VoteController;
use Illuminate\Support\Facades\Route;

// ---------- Public (no auth) ----------
Route::get('/', [ResultsController::class, 'home'])->name('home');
Route::get('/vote/{election}', [VoteController::class, 'show'])->name('vote.show');
Route::post('/vote/{election}', [VoteController::class, 'store'])
    ->middleware('throttle:10,1')->name('vote.store');
Route::get('/vote/{election}/thanks', [VoteController::class, 'thanks'])->name('vote.thanks');
Route::get('/vote/{election}/results', [ResultsController::class, 'show'])->name('results.show');
Route::get('/vote/{election}/tally', [ResultsController::class, 'tally'])
    ->middleware('throttle:60,1')->name('results.tally');

// ---------- Authenticated ----------
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    // JSON: server-side searched + paginated elections for the live/dynamic dashboard table.
    Route::get('/dashboard/elections', [DashboardController::class, 'elections'])->name('dashboard.elections');
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ---------- Admin (Admin or Manager) ----------
    Route::middleware('role:Admin|Manager')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('elections', ElectionController::class);
        Route::post('elections/{election}/status', [ElectionController::class, 'status'])->name('elections.status');
        // scoped(): {candidate} must belong to {election} — closes the IDOR gap.
        Route::resource('elections.candidates', CandidateController::class)->scoped()->except(['index', 'show']);
        // JSON: searchable + paginated candidates + voter audit log.
        Route::get('elections/{election}/candidates-list', [CandidateController::class, 'list'])->name('elections.candidates.list');
        Route::get('elections/{election}/votes', [ElectionController::class, 'votes'])->name('elections.votes');
        Route::get('elections/{election}/report.csv', [ReportController::class, 'csv'])->name('elections.report');
        Route::get('elections/{election}/votes.csv', [ReportController::class, 'votesCsv'])->name('elections.votes.csv');
        Route::get('elections/{election}/votes.xlsx', [ReportController::class, 'votesExcel'])->name('elections.votes.excel');

        // Analytics (Admin + Manager)
        Route::get('analytics', [\App\Http\Controllers\Admin\AnalyticsController::class, 'index'])->name('analytics');
        Route::get('analytics/data', [\App\Http\Controllers\Admin\AnalyticsController::class, 'data'])->name('analytics.data');

        // Admin-only: organization settings + member management
        Route::middleware('role:Admin')->group(function () {
            Route::get('organization', [\App\Http\Controllers\Admin\OrganizationController::class, 'edit'])->name('org.edit');
            Route::patch('organization', [\App\Http\Controllers\Admin\OrganizationController::class, 'update'])->name('org.update');

            Route::get('members', [\App\Http\Controllers\Admin\MemberController::class, 'index'])->name('members.index');
            Route::get('members/list', [\App\Http\Controllers\Admin\MemberController::class, 'list'])->name('members.list');
            Route::post('members', [\App\Http\Controllers\Admin\MemberController::class, 'store'])->name('members.store');
            Route::patch('members/{user}', [\App\Http\Controllers\Admin\MemberController::class, 'update'])->name('members.update');
            Route::delete('members/{user}', [\App\Http\Controllers\Admin\MemberController::class, 'destroy'])->name('members.destroy');
        });
    });
});

require __DIR__.'/auth.php';
