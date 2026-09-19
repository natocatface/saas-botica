<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $c->tipoNombre() }} {{ $c->numero }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#334155">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden">
                    {{-- Encabezado --}}
                    <tr>
                        <td style="background:#059669;padding:22px 28px;color:#ffffff">
                            <div style="font-size:18px;font-weight:bold">{{ $cfg['fe_razon_social'] }}</div>
                            <div style="font-size:13px;opacity:.9">RUC {{ $cfg['fe_ruc'] }}</div>
                        </td>
                    </tr>

                    {{-- Cuerpo --}}
                    <tr>
                        <td style="padding:28px">
                            <p style="margin:0 0 14px;font-size:15px">Estimado(a) <strong>{{ $c->cliente_nombre ?: 'cliente' }}</strong>,</p>
                            <p style="margin:0 0 18px;font-size:14px;line-height:1.6">
                                Adjuntamos su comprobante electrónico emitido ante SUNAT. El archivo <strong>XML</strong>
                                es el documento con validez tributaria; se incluye también el <strong>CDR</strong> de SUNAT
                                @if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class))y la representación impresa en <strong>PDF</strong>@endif.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:10px;font-size:14px">
                                <tr>
                                    <td style="padding:12px 16px;color:#64748b;border-bottom:1px solid #f1f5f9">Comprobante</td>
                                    <td style="padding:12px 16px;text-align:right;font-weight:bold;border-bottom:1px solid #f1f5f9">{{ strtoupper($c->tipoNombre()) }} {{ $c->numero }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#64748b;border-bottom:1px solid #f1f5f9">Fecha</td>
                                    <td style="padding:12px 16px;text-align:right;border-bottom:1px solid #f1f5f9">{{ ($c->enviado_at ?? $c->created_at)->format('d/m/Y') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#64748b;border-bottom:1px solid #f1f5f9">IGV</td>
                                    <td style="padding:12px 16px;text-align:right;border-bottom:1px solid #f1f5f9">S/ {{ number_format($c->igv, 2) }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;color:#0f172a;font-weight:bold;font-size:16px">Total</td>
                                    <td style="padding:14px 16px;text-align:right;color:#059669;font-weight:bold;font-size:16px">S/ {{ number_format($c->total, 2) }}</td>
                                </tr>
                            </table>

                            <p style="margin:20px 0 0;font-size:13px;color:#64748b">
                                Estado ante SUNAT: <strong style="color:#334155;text-transform:capitalize">{{ $c->estado }}</strong>
                                @if ($c->sunat_codigo) (código {{ $c->sunat_codigo }})@endif.
                            </p>
                        </td>
                    </tr>

                    {{-- Pie --}}
                    <tr>
                        <td style="padding:18px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;color:#94a3b8">
                            {{ $cfg['fe_nombre_comercial'] ?: $cfg['fe_razon_social'] }} · {{ $cfg['fe_direccion'] }}<br>
                            Este es un mensaje automático, por favor no responda a este correo.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
