<?php

namespace App\Http\Controllers;

use App\Models\FundRequest;
use App\Services\FundRequest\FundRequestDetailService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FundRequestPdfController extends Controller
{
    public function preview(FundRequest $record): Response
    {
        $user = auth()->user();
        abort_unless($user && $user->can('view', $record), 403, 'Anda tidak memiliki akses ke dokumen ini.');

        try {
            $pdfData = app(FundRequestDetailService::class)->buildForPdf($record);
        } catch (RuntimeException $e) {
            return response($e->getMessage(), 422, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        $pdf = Pdf::loadView('pdf.fund-request', $pdfData)->setPaper('a4', 'portrait');

        $safeDocNumber = preg_replace('/[^A-Za-z0-9\-_]+/', '-', (string) $record->document_number) ?: (string) $record->id;
        $safeFiscalYear = preg_replace('/[^A-Za-z0-9\-_]+/', '-', (string) $record->fiscal_year) ?: date('Y');
        $filename = "Fund-Request-{$safeDocNumber}-{$safeFiscalYear}.pdf";

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function attachment(FundRequest $record, int $index): StreamedResponse
    {
        $user = auth()->user();
        abort_unless($user && $user->can('view', $record), 403, 'Anda tidak memiliki akses ke lampiran ini.');

        $attachments = array_values(is_array($record->attachments) ? $record->attachments : []);
        $path = $attachments[$index] ?? null;

        abort_if(! is_string($path) || $path === '', 404, 'Lampiran tidak ditemukan.');

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->download($path, basename($path));
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->download($path, basename($path));
        }

        abort(404, 'File lampiran tidak ditemukan di penyimpanan.');
    }
}
