<?php

namespace App\Http\Controllers;

use App\Models\FundRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class FundRequestPdfController extends Controller
{
    public function preview(FundRequest $record): Response
    {
        $user = auth()->user();
        abort_unless($user && $user->can('view', $record), 403, 'Anda tidak memiliki akses ke dokumen ini.');

        // Pastikan hanya pengajuan yang sudah approved yang bisa di-preview
        if (strtolower($record->status) !== 'approved') {
            abort(403, 'Dokumen belum disetujui.');
        }

        $pdf = Pdf::loadView('pdf.fund-request', [
            'record' => $record->load([
                'department',
                'items.account.departmentBudgets',
                'verifications.account',
                'verifications.finalAccount',
            ]),
        ])->setPaper('a4', 'portrait');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Formulir-Pencairan-Dana-'.$record->document_number.'.pdf"',
        ]);
    }
}
