<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComprobanteElectronico extends Model
{
    protected $table = 'comprobantes_electronicos';

    protected $fillable = [
        'venta_id', 'tipo_comprobante', 'serie', 'correlativo', 'numero',
        'ruc_emisor', 'cliente_tipo_doc', 'cliente_num_doc', 'cliente_nombre',
        'moneda', 'gravado', 'igv', 'total',
        'estado', 'entorno', 'driver',
        'sunat_codigo', 'sunat_descripcion', 'hash_cpe', 'ticket',
        'xml_path', 'cdr_path', 'referencia_id', 'motivo_nota', 'enviado_at',
    ];

    protected $casts = [
        'gravado' => 'decimal:2',
        'igv' => 'decimal:2',
        'total' => 'decimal:2',
        'enviado_at' => 'datetime',
    ];

    public const TIPOS = [
        '01' => 'Factura',
        '03' => 'Boleta',
        '07' => 'Nota de crédito',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function referencia(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referencia_id');
    }

    public function tipoNombre(): string
    {
        return self::TIPOS[$this->tipo_comprobante] ?? 'Comprobante';
    }

    /** Clases Tailwind para el badge de estado. */
    public function estadoClase(): string
    {
        return match ($this->estado) {
            'aceptado' => 'bg-emerald-100 text-emerald-700',
            'enviado' => 'bg-sky-100 text-sky-700',
            'pendiente' => 'bg-amber-100 text-amber-700',
            'observado' => 'bg-orange-100 text-orange-700',
            'anulado' => 'bg-slate-200 text-slate-600',
            'rechazado', 'error' => 'bg-red-100 text-red-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }

    public function esAceptado(): bool
    {
        return $this->estado === 'aceptado';
    }
}
