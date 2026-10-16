<?php
// Pruebas unitarias de la lógica de negocio (equivalentes a las de pytest del informe).
// Ejecutar:  C:\xampp\php\php.exe tests\run_tests.php
define('PORC_COMISION_VENTA', 0.03);
define('MESES_COMISION_ALQUILER', 0.5);
require __DIR__ . '/../app/Inmueble.php';
require __DIR__ . '/../app/ContratoService.php';

$ok = 0; $fail = 0;
function check(string $nombre, callable $fn): void
{
    global $ok, $fail;
    try { $fn(); echo "  OK   $nombre\n"; $ok++; } catch (Throwable $e) { echo "  FALLA $nombre -> " . $e->getMessage() . "\n"; $fail++; }
}
function assertTrue($c, string $m = 'aserción falsa'): void { if (!$c) { throw new Exception($m); } }
function assertThrows(string $cls, callable $fn): void
{
    try { $fn(); } catch (Throwable $e) { if ($e instanceof $cls) { return; } throw new Exception("se esperaba $cls y llegó " . get_class($e)); }
    throw new Exception("se esperaba $cls pero no hubo excepción");
}
function depto(): Inmueble { return new Inmueble('INM-0012', 'departamento', 'alquiler', 85, 1800); }

check('comisión de alquiler = media renta', function () { assertTrue(ContratoService::calcularComision(depto(), 1800) === 900.0); });
check('comisión de venta = 3 %', function () { $c = new Inmueble('INM-0045', 'casa', 'venta', 180, 420000); assertTrue(ContratoService::calcularComision($c, 420000) === 12600.0); });
check('contrato cambia el estado a alquilado', function () { $i = depto(); $r = ContratoService::registrar($i, true, 1800, 1800); assertTrue($r['estado'] === 'alquilado' && $i->estado === 'alquilado'); });
check('contrato de venta cambia a vendido', function () { $i = new Inmueble('INM-0045', 'casa', 'venta', 180, 420000); $r = ContratoService::registrar($i, true, 420000); assertTrue($r['estado'] === 'vendido' && $r['tipo'] === 'compraventa'); });
check('sin consentimiento falla', function () { assertThrows('ConsentimientoRequeridoException', function () { ContratoService::registrar(depto(), false, 1800); }); });
check('inmueble cerrado no se contrata otra vez', function () { $i = depto(); ContratoService::registrar($i, true, 1800); assertThrows('DomainException', function () use ($i) { ContratoService::registrar($i, true, 1800); }); });
check('garantía negativa es inválida', function () { assertThrows('InvalidArgumentException', function () { ContratoService::registrar(depto(), true, 1800, -1); }); });
check('precio cero es inválido', function () { assertThrows('InvalidArgumentException', function () { new Inmueble('INM-0001', 'casa', 'venta', 100, 0); }); });
check('precio por m²', function () { assertTrue(depto()->precioPorM2() === 21.18); });

echo "\nResultado: $ok aprobadas, $fail fallidas\n";
exit($fail ? 1 : 0);
