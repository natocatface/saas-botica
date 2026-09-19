<?php

namespace App\Mail;

use App\Models\ComprobanteElectronico;
use App\Support\FacturacionConfig;
use App\Support\NumeroALetras;
use App\Models\Configuracion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Envía al cliente su comprobante electrónico: XML firmado + CDR y, si hay un
 * motor PDF disponible (dompdf), también el PDF de la representación impresa.
 */
class ComprobanteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ComprobanteElectronico $comprobante)
    {
        $this->comprobante->loadMissing(['venta.detalles', 'referencia']);
    }

    public function envelope(): Envelope
    {
        $razon = FacturacionConfig::get('fe_razon_social');

        return new Envelope(
            subject: "{$this->comprobante->tipoNombre()} {$this->comprobante->numero} · {$razon}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.comprobante',
            with: [
                'c'   => $this->comprobante,
                'cfg' => FacturacionConfig::todas(),
            ],
        );
    }

    public function attachments(): array
    {
        $adjuntos = [];
        $c = $this->comprobante;

        if ($c->xml_path && is_file(storage_path($c->xml_path))) {
            $adjuntos[] = Attachment::fromPath(storage_path($c->xml_path))
                ->as($c->numero . '.xml')
                ->withMime('application/xml');
        }

        if ($c->cdr_path && is_file(storage_path($c->cdr_path))) {
            $adjuntos[] = Attachment::fromPath(storage_path($c->cdr_path))
                ->as('R-' . $c->numero . '.zip')
                ->withMime('application/zip');
        }

        // PDF opcional: solo si el motor dompdf está instalado.
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $adjuntos[] = Attachment::fromData(fn () => $this->generarPdf(), $c->numero . '.pdf')
                ->withMime('application/pdf');
        }

        return $adjuntos;
    }

    private function generarPdf(): string
    {
        $c = $this->comprobante;
        $igvPct = (float) Configuracion::valor('igv_porcentaje', 18);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('facturacion.comprobante_pdf', [
            'c'        => $c,
            'cfg'      => FacturacionConfig::todas(),
            'igvPct'   => $igvPct,
            'enLetras' => NumeroALetras::soles((float) $c->total),
        ])->setPaper('a4');

        return $pdf->output();
    }
}
