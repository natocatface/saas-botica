<?php

namespace Database\Seeders;

use App\Models\AjusteInventario;
use App\Models\Auditoria;
use App\Models\CajaMovimiento;
use App\Models\CajaSesion;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DashboardDemoSeeder
 * -------------------
 * Agrega 10 registros a CADA módulo del sistema con fechas repartidas en los
 * últimos 10 días (incluido HOY y toda la última semana), de modo que el gráfico
 * "Ingresos de los últimos 7 días" y las tarjetas del dashboard siempre muestren
 * información al momento de ejecutarlo.
 *
 * Ejecutar:  php artisan db:seed --class=DashboardDemoSeeder
 */
class DashboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        $hoy      = Carbon::today();
        $metodos  = ['Efectivo', 'Tarjeta', 'Yape', 'Plin', 'Transferencia'];
        $sufijo   = now()->format('His'); // evita choques de códigos únicos entre ejecuciones

        // ---------- 10 Personal / Usuarios ----------
        $roles = ['vendedor', 'cajero', 'farmaceutico', 'admin'];
        for ($i = 1; $i <= 10; $i++) {
            User::updateOrCreate(
                ['email' => "demo{$sufijo}_{$i}@mibotica.test"],
                [
                    'name'     => 'Empleado Demo ' . $sufijo . '-' . $i,
                    'password' => Hash::make('password'),
                    'rol'      => $roles[array_rand($roles)],
                    'telefono' => '9' . rand(10000000, 99999999),
                    'activo'   => true,
                ]
            );
        }
        $usuarios = User::pluck('id')->all();

        // ---------- 10 Categorías ----------
        $categorias = [];
        for ($i = 1; $i <= 10; $i++) {
            $categorias[] = Categoria::create([
                'nombre'      => 'Categoría Demo ' . $sufijo . '-' . $i,
                'descripcion' => 'Categoría de demostración',
                'activo'      => true,
            ]);
        }

        // ---------- 10 Proveedores ----------
        $proveedores = [];
        for ($i = 1; $i <= 10; $i++) {
            $proveedores[] = Proveedor::create([
                'razon_social'   => 'Proveedor Demo ' . $sufijo . '-' . $i . ' S.A.C.',
                'ruc'            => '20' . rand(100000000, 999999999),
                'contacto'       => 'Contacto ' . $i,
                'telefono'       => '01' . rand(1000000, 9999999),
                'email'          => "proveedor{$i}@demo.test",
                'condicion_pago' => ['Contado', 'Crédito 15 días', 'Crédito 30 días'][array_rand([0, 1, 2])],
                'activo'         => true,
            ]);
        }

        // ---------- 10 Productos ----------
        $nombresBase = [
            'Naproxeno 550mg', 'Diclofenaco 50mg', 'Cetirizina 10mg', 'Ranitidina 150mg',
            'Salbutamol Inhalador', 'Ketorolaco 10mg', 'Multivitamínico', 'Zinc + Vit C',
            'Ibuprofeno 600mg', 'Clonazepam 2mg',
        ];
        $nuevos = [];
        for ($i = 1; $i <= 10; $i++) {
            $venc = (clone $hoy)->addMonths(rand(6, 24));
            // algunos por vencer/vencidos para alimentar Alertas Sanitarias
            if ($i === 4) { $venc = (clone $hoy)->addDays(30); }
            if ($i === 8) { $venc = (clone $hoy)->subDays(10); }
            $nuevos[] = Producto::create([
                'codigo_barras'     => '77' . $sufijo . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'nombre'            => $nombresBase[$i - 1] . ' (Demo ' . $sufijo . ')',
                'principio_activo'  => $nombresBase[$i - 1],
                'presentacion'      => ['Tableta', 'Cápsula', 'Jarabe', 'Sobre'][array_rand([0, 1, 2, 3])],
                'concentracion'     => rand(1, 500) . 'mg',
                'categoria_id'      => $categorias[array_rand($categorias)]->id,
                'proveedor_id'      => $proveedores[array_rand($proveedores)]->id,
                'laboratorio'       => ['Genfar', 'Bayer', 'Medifarma', 'Hersil', 'GSK'][array_rand([0, 1, 2, 3, 4])],
                'precio_compra'     => rand(20, 200) / 10,
                'precio_venta'      => rand(50, 500) / 10,
                'stock'             => ($i === 5) ? rand(1, 8) : rand(40, 200), // uno con stock bajo -> alerta
                'stock_minimo'      => 10,
                'lote'              => 'L-DEMO-' . $sufijo . '-' . $i,
                'fecha_vencimiento' => $venc,
                'requiere_receta'   => (bool) rand(0, 1),
                'activo'            => true,
            ]);
        }
        // Combinar con productos existentes para que las ventas se vean realistas
        $productos = Producto::inRandomOrder()->limit(20)->get()->all();
        if (empty($productos)) {
            $productos = $nuevos;
        }

        // ---------- 10 Clientes ----------
        $clientes = [];
        for ($i = 1; $i <= 10; $i++) {
            $clientes[] = Cliente::create([
                'nombre'           => 'Cliente Demo ' . $sufijo . '-' . $i,
                'tipo_documento'   => 'DNI',
                'numero_documento' => (string) rand(40000000, 79999999),
                'telefono'         => '9' . rand(10000000, 99999999),
                'puntos'           => rand(0, 120),
                'activo'           => true,
            ]);
        }

        // ---------- 10 Ventas: UNA por cada uno de los últimos 10 días (incluido hoy) ----------
        for ($dia = 0; $dia < 10; $dia++) {
            $fecha = (clone $hoy)->subDays($dia)->setTime(rand(8, 20), rand(0, 59));

            $venta = new Venta([
                'numero_comprobante' => 'B' . $sufijo . '-' . str_pad((string) ($dia + 1), 5, '0', STR_PAD_LEFT),
                'tipo_comprobante'   => 'boleta',
                'cliente_id'         => $clientes[array_rand($clientes)]->id,
                'user_id'            => $usuarios[array_rand($usuarios)],
                'metodo_pago'        => $metodos[array_rand($metodos)],
                'estado'             => 'pagada',
            ]);
            $venta->created_at = $fecha;
            $venta->updated_at = $fecha;
            $venta->subtotal = 0; $venta->igv = 0; $venta->total = 0;
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
                $detalle->created_at = $fecha;
                $detalle->updated_at = $fecha;
                $detalle->save();
            }

            $total = round($subtotalVenta, 2);
            $base  = round($total / 1.18, 2);
            $venta->subtotal = $base;
            $venta->igv      = round($total - $base, 2);
            $venta->total    = $total;
            $venta->save();
        }

        // ---------- 10 Compras: UNA por cada uno de los últimos 10 días ----------
        for ($dia = 0; $dia < 10; $dia++) {
            $fecha = (clone $hoy)->subDays($dia)->setTime(rand(8, 20), rand(0, 59));

            $compra = new Compra([
                'numero_documento' => 'F' . $sufijo . '-' . str_pad((string) ($dia + 1), 5, '0', STR_PAD_LEFT),
                'proveedor_id'     => $proveedores[array_rand($proveedores)]->id,
                'user_id'          => $usuarios[array_rand($usuarios)],
                'fecha'            => $fecha,
                'estado'           => 'recibida',
                'estado_pago'      => ['pagada', 'pendiente'][array_rand([0, 1])],
                'observacion'      => 'Compra Demo ' . ($dia + 1),
            ]);
            $compra->created_at = $fecha;
            $compra->updated_at = $fecha;
            $compra->subtotal = 0; $compra->igv = 0; $compra->total = 0;
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
                    'producto_id'       => $prod->id,
                    'descripcion'       => $prod->nombre,
                    'cantidad'          => $cant,
                    'precio_compra'     => $precio,
                    'subtotal'          => $sub,
                    'lote'              => 'LC-' . rand(1000, 9999),
                    'fecha_vencimiento' => (clone $hoy)->addMonths(rand(6, 24)),
                ]);
                $detalle->compra_id  = $compra->id;
                $detalle->created_at = $fecha;
                $detalle->updated_at = $fecha;
                $detalle->save();
            }

            $total = round($subtotalCompra, 2);
            $base  = round($total / 1.18, 2);
            $compra->subtotal = $base;
            $compra->igv      = round($total - $base, 2);
            $compra->total    = $total;
            $compra->save();
        }

        // ---------- 10 Ajustes de Inventario ----------
        for ($dia = 0; $dia < 10; $dia++) {
            $fecha  = (clone $hoy)->subDays($dia)->setTime(rand(8, 20), rand(0, 59));
            $prod   = $productos[array_rand($productos)];
            $antes  = (int) $prod->stock;
            $tipo   = ['ingreso', 'salida'][array_rand([0, 1])];
            $cant   = rand(1, 15);
            $nuevo  = $tipo === 'ingreso' ? $antes + $cant : max(0, $antes - $cant);

            $ajuste = new AjusteInventario([
                'producto_id'    => $prod->id,
                'user_id'        => $usuarios[array_rand($usuarios)],
                'tipo'           => $tipo,
                'cantidad'       => $cant,
                'stock_anterior' => $antes,
                'stock_nuevo'    => $nuevo,
                'motivo'         => ['Merma', 'Conteo físico', 'Devolución', 'Donación'][array_rand([0, 1, 2, 3])],
            ]);
            $ajuste->created_at = $fecha;
            $ajuste->updated_at = $fecha;
            $ajuste->save();
        }

        // ---------- 10 Sesiones de Caja (cerradas) + su movimiento de apertura ----------
        for ($dia = 0; $dia < 10; $dia++) {
            $apertura = (clone $hoy)->subDays($dia)->setTime(8, rand(0, 30));
            $cierre   = (clone $apertura)->addHours(rand(8, 12));
            $esperado = 100.00 + rand(100, 800);

            $sesion = new CajaSesion([
                'user_id'        => $usuarios[array_rand($usuarios)],
                'cerrado_por'    => $usuarios[array_rand($usuarios)],
                'monto_inicial'  => 100.00,
                'monto_esperado' => $esperado,
                'monto_final'    => $esperado,
                'diferencia'     => 0,
                'estado'         => 'cerrada',
                'observacion'    => 'Caja Demo ' . ($dia + 1),
                'abierta_at'     => $apertura,
                'cerrada_at'     => $cierre,
            ]);
            $sesion->created_at = $apertura;
            $sesion->updated_at = $cierre;
            $sesion->save();

            $mov = new CajaMovimiento([
                'caja_sesion_id' => $sesion->id,
                'user_id'        => $sesion->user_id,
                'tipo'           => 'ingreso',
                'concepto'       => 'Apertura de caja',
                'monto'          => 100.00,
            ]);
            $mov->created_at = $apertura;
            $mov->updated_at = $apertura;
            $mov->save();
        }

        // ---------- 10 Registros de Auditoría ----------
        $acciones = ['crear', 'actualizar', 'eliminar', 'login', 'anular'];
        $modelos  = ['Producto', 'Venta', 'Compra', 'Cliente', 'CajaSesion'];
        for ($dia = 0; $dia < 10; $dia++) {
            $fecha = (clone $hoy)->subDays($dia)->setTime(rand(8, 20), rand(0, 59));
            $aud = new Auditoria([
                'user_id'     => $usuarios[array_rand($usuarios)],
                'accion'      => $acciones[array_rand($acciones)],
                'modelo'      => $modelos[array_rand($modelos)],
                'modelo_id'   => rand(1, 50),
                'descripcion' => 'Acción de demostración registrada',
                'ip'          => '192.168.1.' . rand(2, 254),
            ]);
            $aud->created_at = $fecha;
            $aud->updated_at = $fecha;
            $aud->save();
        }
    }
}
