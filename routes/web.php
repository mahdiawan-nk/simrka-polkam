<?php

use App\Exports\AccountTemplateExport;
use App\Http\Controllers\DatabaseBackupDownloadController;
use App\Http\Controllers\FundRequestPdfController;
use App\Http\Controllers\KnowledgeBookPdfController;
use App\Services\RkaApprovedTemplateService;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;

Route::middleware(['auth'])->group(function () {
    Route::get('/fund-requests/{record}/pdf-preview', [FundRequestPdfController::class, 'preview'])
        ->name('fund-requests.pdf-preview');

    Route::get('/fund-requests/{record}/attachment/{index}', [FundRequestPdfController::class, 'attachment'])
        ->name('fund-requests.attachment');
    Route::get('/database/backups/files/{backupFile}/download', DatabaseBackupDownloadController::class)
        ->withoutMiddleware(['auth'])
        ->middleware([Authenticate::class])
        ->name('database.backups.files.download');

    Route::get('/accounts/download-template', function (Request $request) {
        $user = $request->user();
        abort_unless($user && ($user->can('ViewAny:Account') || $user->can('Create:Account')), 403);

        return Excel::download(
            new AccountTemplateExport,
            'Template_Import_COA_RKA.xlsx'
        );
    })->name('accounts.download-template');

    Route::get('/admin/rka-headers/import-approved/template', function (Request $request, RkaApprovedTemplateService $templateService) {
        return $templateService->downloadBlankImportTemplate($request->user());
    })
        ->withoutMiddleware(['auth'])
        ->middleware([Authenticate::class])
        ->name('rka-headers.import-approved.template');

    Route::prefix('/knowledge-center/handbook')
        ->withoutMiddleware(['auth'])
        ->middleware([Authenticate::class])
        ->group(function (): void {
            Route::get('/role', [KnowledgeBookPdfController::class, 'roleHandbook'])
                ->name('knowledge-center.handbook.role');
            Route::get('/complete', [KnowledgeBookPdfController::class, 'completeHandbook'])
                ->name('knowledge-center.handbook.complete');
            Route::get('/article/{slug}', [KnowledgeBookPdfController::class, 'singleArticle'])
                ->name('knowledge-center.handbook.article');
        });
});
Route::get('/', function () {
    return view('welcome');
});
