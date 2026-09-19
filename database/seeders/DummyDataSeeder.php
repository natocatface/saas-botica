<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Compra;
use App\Models\CompraDetalle;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DummyDataSeeder extends Seeder
{
    /**
     * Genera 10 registros por módulo con fechas RELATIVAS al día en que se ejecuta.
     * Las ventas/compras/ajustes/caja se reparten uno por cada uno de los últimos 10 días
     * (incluido HOY) para que el gráfico de "Ingresos de los últimos 7 días" y las
     * tarjetas del dashboard siempre muestren información.
     */
    public function run(): void
    {
        $hoy = Carbon::today();

        // ---------- 10 Categorías ----------
        $categorias = [];
        for ($i = 1; $i <= 10; $i++) {
            $categorias[] = Categoria::create([
                'nombre' => 'Categoría Demo ' . rand(1000, 9999),
                'activo' => true,
            ]);
        }

        // ---------- 10 Proveedores ----------
        $proveedores = [];
        for ($i = 1; $i <= 10; $i++) {
            $proveedores[] = Proveedor::create([
                'razon_social'   => 'Proveedor Demo ' . rand(1000, 9999) . ' S.A.C.',
                'ruc'            => '20' . rand(100000000, 999999999),
                'contacto'       => 'Contacto ' . $i,
                'telefono'       => '9' . rand(10000000, 99999999),
                'condicion_pago' => 'Contado',
                'activo'         => true,
            ]);
        }

        // ---------- 10 Productos ----------
        $productos = [];
        for ($i = 1; $i <= 10; $i++) {
            $productos[] = Producto::create([
                'codigo_barras'    => '88' . rand(1000000, 9999999),
                'nombre'           => 'Producto Demo ' . rand(1000, 9999),
                'principio_activo' => 'Principio ' . $i,
                'presentacion'     => 'Caja',
                'concentracion'    => '100mg',
                'categoria_id'     => $categorias[array_rand($categorias)]->id,
                'proveedor_id'     => $proveedores[array_rand($proveedores)]->id,
                'laboratorio'      => 'Lab Demo',
                'precio_compra'    => rand(10, 50) / 10,
                'precio_venta'     => rand(60, 150) / 10,
                'stock'            => rand(50, 200),
                'stock_minimo'     => 10,
                'lote'             => 'L-DEMO-' . $i,
                'fecha_vencimiento'=> (clone $hoy)->addMonths(rand(6, 24)),
                'requiere_receta'  => false,
                'activo'           => true,
            ]);
        }

        // ---------- 10 Clientes ----------
        $clientes = [];
        for ($i = 1; $i <= 10; $i++) {
            $clientes[] = Cliente::create([
                'nombre'           => 'Cliente Demo ' . rand(1000, 9999),
                'tipo_documento'   => 'DNI',
                'numero_documento' => (string) rand(10000000, 99999999),
                'activo'           => true,
                'puntos'           => rand(0, 50),
            ]);
        }

        // Usuarios disponibles
        $usuarios = User::pluck('id')->all();
        if (empty($usuarios)) {
            $usuarios = [User::factory()->create()->id];
        }
        $metodos = ['Efectivo', 'Tarjeta', 'Yape', 'Plin', 'Transferencia'];

        // ---------- 10 Ventas: UNA por cada uno de los últimos 10 días (incluido hoy) ----------
        for ($dia = 0; $dia < 10; $dia++) {
            $fechaVenta = (clone $hoy)->subDays($dia)->setTime(rand(8, 20), rand(0, 59));

            $venta = new Venta([
                'numero_comprobante' => 'B999-' . str_pad(rand(1, 99999), 6, '0', STR_PAD_LEFT),
                'tipo_comprobante'   => 'boleta',
                'cliente_id'         => $clientes[array_rand($clientes)]->id,
                'user_id'            => $usuarios[array_rand($usuarios)],
                'metodo_pago'        => $metodos[array_rand($metodos)],
                'estado'             => 'pagada',
            ]);
            $venta->created_at = $fechaVenta;
            $venta->updated_at = $fechaVenta;
            $venta->subtotal = 0;
            $venta->total = 0;
            $venta->igv = 0;
            $venta->save();

            $subtotalVenta = 0;
            $items = rand(2, 4);
            for ($it = 0; $it < $items; $it++) {
                $prod   = $productos[array_rand($productos)];
                $cant   = rand(1, 5);
                $precio = (float) $prod->precio_venta;
                $sub    = round($cant * $precio, 2);
                $subtotalVenta += $sub;

                $detalle = new VentaDetalle([
                    'producto_id'     => $prod->id,
                    'descripcion'     => $prod->nombre,
                    'cantidad'        => $cant,
                    'precio_unitario' => $precio,
                    'subtotal'        => $sub,
                ]);
                $detalle->venta_id   = $venta->id;
                $detalle->created_at = $fechaVenta;
                $detalle->updated_at = $fechaVenta;
                $detalle->save();
            }

            $total = round($subtotalVenta, 2);
            $base  = round($total / 1.18, 2);
            $igv   = round($total - $base, 2);
            $venta->subtotal = $base;
            $venta->igv = $igv;
            $venta->total = $total;
            $venta->save();
        }

        // ---------- 10 Compras: UNA por cada uno de los últimos 10 días ----------
        for ($dia = 0; $dia < 10; $dia++) {
            $fechaCompra = (clone $hoy)->subDays($dia)->setTime(rand(8, 20), rand(0, 59));

            $compra = new Compra([
                'proveedor_id'    => $proveedores[array_rand($proveedores)]->id,
                'user_id'         => $usuarios[array_rand($usuarios)],
                'numero_documento'=> 'F001-' . str_pad(rand(1, 99999), 6, '0', STR_PAD_LEFT),
                'fecha'           => $fechaCompra,
                'estado'          => 'recibido',
                'estado_pago'     => 'pagado',
                'observacion'     => 'Compra Demo ' . ($dia + 1),
            ]);
            $compra->created_at = $fechaCompra;
            $compra->updated_at = $fechaCompra;
            $compra->subtotal = 0;
            $compra->total = 0;
            $compra->igv = 0;
            $compra->save();

            $subtotalCompra = 0;
            $items = rand(2, 4);
            for ($it = 0; $it < $items; $it++) {
                $prod   = $productos[array_rand($productos)];
                $cant   = rand(10, 50);
                $precio = (float) $prod->precio_compra;
                $sub    = round($cant * $precio, 2);
                $subtotalCompra += $sub;

                $detalle = new CompraDetalle([
                    'producto_id'      => $prod->id,
                    'descripcion'      => $prod->nombre,
                    'cantidad'         => $cant,
                    'precio_compra'    => $precio,
                    'subtotal'         => $sub,
                    'lote'             => 'LC-' . rand(1000, 9999),
                    'fecha_vencimiento'=> (clone $hoy)->addMonths(rand(6, 24)),
                ]);
                $detalle->compra_id  = $compra->id;
                $detalle->created_at = $fechaCompra;
                $detalle->updated_at = $fechaCompra;
                $detalle->save();
            }

            $total = round($subtotalCompra, 2);
            $base  = round($total / 1.18, 2);
            $igv   = round($total - $base, 2);
            $compra->subtotal = $base;
            $compra->igv = $igv;
            $compra->total = $total;
            $compra->save();
        }

        // ---------- 10 Ajustes de Inventario: UNO por cada uno de los últimos 10 días ----------
        for ($dia = 0; $dia < 10; $dia++) {
            $fechaAjuste = (clone $hoy)->subDays($dia)->setTime(rand(8, 20), rand(0, 59));

            \App\Models\AjusteInventario::create([
                'producto_id'    => $productos[array_rand($productos)]->id,
                'user_id'        => $usuarios[array_rand($usuarios)],
                'tipo'           => ['ingreso', 'salida'][rand(0, 1)],
                'cantidad'       => rand(1, 10),
                'stock_anterior' => rand(20, 50),
                'stock_nuevo'    => rand(10, 60),
                'motivo'         => 'Ajuste Demo ' . ($dia + 1),
                'created_at'     => $fechaAjuste,
                'updated_at'     => $fechaAjuste,
            ]);
        }

        // ---------- 10 Sesiones de Caja (todas cerradas para no chocar con la caja activa) ----------
        for ($dia = 0; $dia < 10; $dia++) {
            $fechaApertura = (clone $hoy)->subDays($dia)->setTime(8, rand(0, 30));
            $fechaCierre   = (clone $fechaApertura)->addHours(rand(8, 12));

            $sesion = \App\Models\CajaSesion::create([
                'user_id'        => $usuarios[array_rand($usuarios)],
                'cerrado_por'    => $usuarios[array_rand($usuarios)],
                'monto_inicial'  => 100.00,
                'monto_esperado' => 100.00 + rand(100, 500),
                'monto_final'    => 100.00 + rand(100, 500),
                'diferencia'     => 0,
                'estado'         => 'cerrada',
                'observacion'    => 'Caja Demo ' . ($dia + 1),
                'abierta_at'     => $fechaApertura,
                'cerrada_at'     => $fechaCierre,
                'created_at'     => $fechaApertura,
                'updated_at'     => $fechaCierre,
            ]);

            // Movimiento de apertura por sesión
            \App\Models\CajaMovimiento::create([
                'caja_sesion_id' => $sesion->id,
                'user_id'        => $sesion->user_id,
                'tipo'           => 'ingreso',
                'concepto'       => 'Apertura de caja',
                'monto'          => 100.00,
                'created_at'     => $fechaApertura,
                'updated_at'     => $fechaApertura,
            ]);
        }
    }
}
