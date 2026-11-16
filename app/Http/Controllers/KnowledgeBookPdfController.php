<?php

namespace App\Http\Controllers;

use App\Services\Knowledge\KnowledgeBookPdfService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class KnowledgeBookPdfController extends Controller
{
    public function roleHandbook(Request $request, KnowledgeBookPdfService $pdfService): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        try {
            $result = $pdfService->generateForCurrentRole($user);
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        }

        return response($result['pdf']->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function completeHandbook(Request $request, KnowledgeBookPdfService $pdfService): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        try {
            $result = $pdfService->generateCompleteHandbook($user);
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        }

        return response($result['pdf']->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function singleArticle(Request $request, string $slug, KnowledgeBookPdfService $pdfService): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        try {
            $result = $pdfService->generateSingleArticle($user, $slug);
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        }

        return response($result['pdf']->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
