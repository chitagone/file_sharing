<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    
    // Document routes
    Route::prefix('documents')->group(function () {
        Route::get('/', [DocumentController::class, 'index']); // Get all documents
        Route::post('/', [DocumentController::class, 'store']); // Upload new document
        Route::get('/{id}', [DocumentController::class, 'show']); // Get specific document
        Route::put('/{id}', [DocumentController::class, 'update']); // Update document metadata
        Route::delete('/{id}', [DocumentController::class, 'destroy']); // Delete document
        
        // Version management
        Route::post('/{id}/versions', [DocumentController::class, 'uploadVersion']); // Upload new version
        
        // File operations
        Route::get('/{id}/download', [DocumentController::class, 'download']); // Download latest version
        Route::get('/{id}/download/{versionId}', [DocumentController::class, 'download']); // Download specific version
        Route::get('/{id}/preview', [DocumentController::class, 'preview']); // Preview document
    });
    
});

// Public routes (no authentication required)
Route::prefix('public')->group(function () {
    // These would be for public document access via sharing links
    // We'll implement these in a later step if needed
});