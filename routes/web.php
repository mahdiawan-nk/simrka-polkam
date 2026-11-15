<?php

use App\Exports\AccountTemplateExport;
use App\Http\Controllers\DatabaseBackupDownloadController;
use App\Http\Controllers\FundRequestPdfController;
use App\Services\RkaApprovedTemplateService;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;

Route::middleware(['auth'])->group(function () {
    Route::get('/fund-requests/{record}/pdf-preview', [FundRequestPdfController::class, 'preview'])
        ->name('fund-requests.pdf-preview');
    Route::get('/database/backups/files/{backupFile}/download', DatabaseBackupDownloadController::class)
        ->withoutMiddleware(['auth'])
        ->middleware([Authenticate::class])
        ->withoutMiddleware(['auth'])
        ->middleware([Authenticate::class])
        ->name('database.backups.files.download');
    Route::get('/accounts/download-template', function () {
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
});
Route::get('/', function () {
    return view('welcome');
});
