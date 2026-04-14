<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Site;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class InvoicePdfController extends Controller
{
    public function __invoke(Site $site, Invoice $invoice): Response
    {
        abort_unless($invoice->site_id === $site->id, 404);

        $invoice->load('items');

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'site' => $site,
        ])->setPaper('a4');

        $filename = 'invoice-'.str_replace(['/', '\\', ' '], '-', $invoice->number).'.pdf';

        return $pdf->download($filename);
    }
}
