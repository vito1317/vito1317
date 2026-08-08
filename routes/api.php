<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Algorithm;
use App\Models\Project;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\GithubController;

Route::get('/projects', function () {
    return Project::orderBy('display_order', 'asc')->get();
});

Route::get('/github/repos', [GithubController::class, 'repos']);
Route::get('/github/contributions', [GithubController::class, 'contributions']);

Route::get('/algorithms', function () {
    return Algorithm::orderBy('display_order', 'asc')->get();
});


Route::post('/contact', [ContactController::class, 'store']);

