<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Project;
use App\Http\Controllers\Api\ContactController;

Route::get('/projects', function () {
    return Project::orderBy('display_order', 'asc')->get();
});


Route::post('/contact', [ContactController::class, 'store']);

