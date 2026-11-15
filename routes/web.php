<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FundRequestPdfController;
use App\Exports\AccountTemplateExport;
use Maatwebsite\Excel\Facades\Excel;

Route::middleware(['auth'])->group(function () {
    Route::get('/fund-requests/{record}/pdf-preview', [FundRequestPdfController::class, 'preview'])
        ->name('fund-requests.pdf-preview');
    Route::get('/accounts/download-template', function () {
        return Excel::download(
            new AccountTemplateExport,
            'Template_Import_COA_RKA.xlsx'
        );
    })->name('accounts.download-template');
});
Route::get('/', function () {
    return view('welcome');
});
