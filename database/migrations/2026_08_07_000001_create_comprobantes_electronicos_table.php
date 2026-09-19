<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comprobantes electrónicos emitidos ante SUNAT (Perú · UBL 2.1).
 * Guarda el estado de cada boleta/factura/nota de crédito y la respuesta de SUNAT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes_electronicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();

            // 01 = Factura, 03 = Boleta, 07 = Nota de crédito (catálogo 01 SUNAT)
            $table->string('tipo_comprobante', 2)->default('03');
            $table->string('serie', 5);
            $table->unsignedInteger('correlativo');
            $table->string('numero', 20)->index(); // SERIE-CORRELATIVO (ej. B001-000123)

            // Emisor
            $table->string('ruc_emisor', 11);

            // Receptor
            $table->string('cliente_tipo_doc', 1)->default('1'); // 6=RUC, 1=DNI, 0=Sin doc
            $table->string('cliente_num_doc', 15)->nullable();
            $table->string('cliente_nombre', 250)->nullable();

            // Importes
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('gravado', 12, 2)->default(0);
            $table->decimal('igv', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            // Ciclo de vida ante SUNAT
            // pendiente | enviado | aceptado | observado | rechazado | anulado | error
            $table->string('estado', 20)->default('pendiente')->index();
            $table->string('entorno', 10)->default('beta'); // beta | produccion
            $table->string('driver', 20)->nullable();       // greenter | null

            // Respuesta SUNAT
            $table->string('sunat_codigo', 10)->nullable();      // código CDR
            $table->string('sunat_descripcion', 500)->nullable();
            $table->string('hash_cpe', 100)->nullable();         // DigestValue del XML firmado
            $table->string('ticket', 100)->nullable();           // para procesos asíncronos / bajas

            // Archivos generados
            $table->string('xml_path', 255)->nullable();
            $table->string('cdr_path', 255)->nullable();

            // Nota de crédito: referencia al comprobante que modifica
            $table->foreignId('referencia_id')->nullable()->constrained('comprobantes_electronicos')->nullOnDelete();
            $table->string('motivo_nota', 250)->nullable();

            $table->timestamp('enviado_at')->nullable();
            $table->timestamps();

            $table->unique(['serie', 'correlativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes_electronicos');
    }
};
