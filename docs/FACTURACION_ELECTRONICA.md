# Facturación Electrónica (SUNAT · Perú)

Módulo de emisión de comprobantes electrónicos (UBL 2.1): boletas, facturas y
notas de crédito, con envío directo a SUNAT mediante el motor **Greenter**.

## Qué se agregó

**Backend**
- `database/migrations/2026_08_07_000001_create_comprobantes_electronicos_table.php` — tabla de comprobantes y su estado ante SUNAT.
- `app/Models/ComprobanteElectronico.php` — modelo.
- `app/Support/FacturacionConfig.php` — configuración (reutiliza la tabla `configuraciones` con prefijo `fe_`).
- `app/Services/Facturacion/` — motor de emisión con arquitectura de *drivers*:
  - `EmisorInterface`, `ResultadoEmision` (DTO)
  - `Drivers/NullEmisor` (no emite, deja pendiente)
  - `Drivers/GreenterEmisor` (emisión real: UBL 2.1 + firma + SOAP + CDR)
  - `FacturacionService` (orquestador: emitir venta, nota de crédito, reintentar, probar conexión)
- `app/Http/Controllers/FacturacionController.php` — configuración, probar conexión, reintentar, nota de crédito, descargar XML/CDR.

**Frontend / integración**
- `resources/views/facturacion/configuracion.blade.php` — módulo de configuración (estilo de la botica).
- Sidebar: el badge pasa a **ON/OFF** según el estado real.
- POS (`PosController@store`): al cerrar una venta boleta/factura se emite automáticamente (sin bloquear la venta si falla).

**Rutas** (`/facturacion`): `index`, `update`, `probar`, `reintentar`, `nota`, `descargar`.

## Puesta en marcha

Ejecuta en `C:\SAAS\saas_botica`:

```bash
# 1) Crear la tabla de comprobantes
php artisan migrate

# 2) Instalar el motor de emisión SUNAT (edita composer.json y composer.lock)
composer require greenter/lite

# 3) Limpiar cachés de configuración/rutas/vistas
php artisan optimize:clear
```

> **Requisitos PHP para Greenter:** habilita las extensiones `soap` y `openssl`
> en tu `php.ini` (quita el `;` de `extension=soap` y `extension=openssl`) y
> reinicia el servidor.

## Configurar (entorno BETA / homologación)

1. Entra a **Facturación Electrónica** en el menú.
2. En *Estado y modo*: marca **Habilitar** y **Emitir automáticamente**,
   Driver = **Greenter**, Entorno = **Beta**.
3. *Credenciales SUNAT* (valores de prueba de SUNAT):
   - RUC: `20000000001`
   - Usuario Clave SOL: `MODDATOS`
   - Clave SOL: `MODDATOS`
   - Certificado: `storage/facturacion/pe/certificate.pem` (ya generado, de pruebas).
4. **Guardar configuración** → **Probar conexión con SUNAT**.
5. Registra una venta (boleta o factura) en el POS: se emite sola y aparece en
   *Comprobantes electrónicos recientes* con su estado y CDR.

## Pasar a PRODUCCIÓN

1. Reemplaza `storage/facturacion/pe/certificate.pem` por tu **certificado digital
   real** (formato PEM: clave privada + certificado en un mismo archivo).
2. En el módulo: Entorno = **Producción**, RUC/razón social reales y tu
   usuario + Clave SOL de producción.
3. Guarda y prueba la conexión.

## Notas

- El certificado de pruebas (`certificate.pem`) es autofirmado y sirve solo para
  BETA/homologación. SUNAT beta acepta comprobantes con este certificado.
- Los XML firmados se guardan en `storage/facturacion/pe/xml/` y los CDR en
  `storage/facturacion/pe/cdr/`.
- **Representación impresa (PDF):** cada comprobante tiene una vista A4 con QR
  (según especificación SUNAT) y hash, accesible con el botón **PDF** del
  historial de facturación y el ícono de descarga del Historial de Ventas.
  Se imprime o guarda como PDF desde el navegador (Imprimir → Guardar como PDF).
- **Reporte para el contador:** en el módulo, botón *Reporte para contador*
  (`/facturacion/reporte`). Filtra por rango de fechas, tipo y estado; muestra
  KPIs (Op. Gravada, IGV y Total de los aceptados) y una tabla tipo registro de
  ventas. Exporta a **Excel (CSV con BOM)** o a **PDF** (Imprimir, A4 horizontal
  sin el menú).
- **Envío por correo al cliente:** en la representación impresa del comprobante
  hay un formulario *Enviar por correo* (usa el email del cliente si lo tiene).
  Adjunta el **XML firmado** y el **CDR**; y también el **PDF** si instalas el
  motor dompdf. Se envía con la configuración de correo de tu `.env`.
  - PDF adjunto (opcional): `composer require barryvdh/laravel-dompdf`
  - Para enviar de verdad, configura SMTP en `.env` (`MAIL_MAILER=smtp`, host,
    puerto, usuario, clave). Con el valor por defecto `MAIL_MAILER=log`, el
    correo se escribe en `storage/logs/laravel.log` (útil para probar).
- Puedes borrar los archivos vacíos `cert.tmp.pem` y `key.tmp.pem` de
  `storage/facturacion/pe/` (temporales de la generación del certificado).
