<?php
require __DIR__ . '/includes/bootstrap.php'; require_login();
$u = usuario_actual(); $err = [];
$d = ['id_inmueble' => q('inmueble'), 'id_cliente' => '', 'fecha_firma' => date('Y-m-d'), 'monto' => '', 'garantia' => '0', 'plazo_meses' => '12'];
if ($d['id_inmueble'] !== '' && ($pre = InmuebleRepo::find((int)$d['id_inmueble']))) { $d['monto'] = $pre['precio']; $d['garantia'] = $pre['operacion'] === 'alquiler' ? $pre['precio'] : '0'; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($d as $k => $_) { $d[$k] = p($k); }
    $inm = InmuebleRepo::find((int)$d['id_inmueble']);
    $st = db()->prepare('SELECT * FROM cliente WHERE id_cliente = ?'); $st->execute([(int)$d['id_cliente']]); $cli = $st->fetch();
    $okFecha = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['fecha_firma']) && checkdate((int)substr($d['fecha_firma'], 5, 2), (int)substr($d['fecha_firma'], 8, 2), (int)substr($d['fecha_firma'], 0, 4));
    if (!$inm) { $err[] = 'Seleccione un inmueble.'; }
    if (!$cli) { $err[] = 'Seleccione un cliente.'; }
    if (!$okFecha) { $err[] = 'Fecha de firma no válida.'; }
    if (!is_numeric($d['monto']) || (float)$d['monto'] <= 0) { $err[] = 'El monto debe ser mayor que cero.'; }
    if (!is_numeric($d['garantia']) || (float)$d['garantia'] < 0) { $err[] = 'La garantía no puede ser negativa.'; }
    if ($inm && $inm['operacion'] === 'alquiler' && (!ctype_digit($d['plazo_meses']) || (int)$d['plazo_meses'] < 1 || (int)$d['plazo_meses'] > 120)) { $err[] = 'El plazo debe estar entre 1 y 120 meses.'; }
    if (!$err) {
        try {
            $obj = Inmueble::fromRow($inm);
            $esAlq = $inm['operacion'] === 'alquiler';
            $res = ContratoService::registrar($obj, (bool)$cli['consentimiento'], (float)$d['monto'], $esAlq ? (float)$d['garantia'] : 0.0);
            $idC = ContratoRepo::crear((int)$inm['id_inmueble'], (int)$cli['id_cliente'], $u['id'], $res['tipo'], $d['fecha_firma'],
                (float)$d['monto'], $res['garantia'], $esAlq ? (int)$d['plazo_meses'] : null, $res['comision'], $res['estado']);
            audit('contrato_crear', $inm['codigo'] . ' ' . $res['tipo']);
            flash('ok', 'Contrato registrado. El inmueble ' . $inm['codigo'] . ' pasó a «' . $res['estado'] . '» y se generó el plan de pagos.');
            redirect('contrato_imprimir.php?id=' . $idC);
        } catch (ConsentimientoRequeridoException $e) { $err[] = 'El cliente no tiene registrado el consentimiento de datos personales. Edítelo en Clientes antes de continuar.';
        } catch (Exception $e) { $err[] = $e->getMessage(); }
    }
}
$inmuebles = db()->query("SELECT id_inmueble, codigo, direccion, operacion, precio FROM inmueble WHERE estado IN ('disponible','reservado') ORDER BY codigo")->fetchAll();
$clientes = db()->query('SELECT id_cliente, nombres, dni_ruc FROM cliente ORDER BY nombres')->fetchAll();
page_top('Registrar contrato (venta / alquiler)', 'contratos.php');
foreach ($err as $m) { echo '<div class="alert alert-danger py-2">' . e($m) . '</div>'; }
?>
<form method="post" class="card card-body"><?= csrf_field() ?><div class="row g-3">
  <div class="col-md-6"><label class="form-label">Inmueble</label><select name="id_inmueble" class="form-select"><option value="">-- Seleccione --</option>
    <?php foreach ($inmuebles as $i): ?><option value="<?= $i['id_inmueble'] ?>" <?= (string)$d['id_inmueble'] === (string)$i['id_inmueble'] ? 'selected' : '' ?>><?= e($i['codigo']) ?> – <?= e($i['direccion']) ?> (<?= e($i['operacion']) ?>, <?= soles($i['precio']) ?>)</option><?php endforeach; ?></select></div>
  <div class="col-md-6"><label class="form-label">Cliente</label><select name="id_cliente" class="form-select"><option value="">-- Seleccione --</option>
    <?php foreach ($clientes as $c): ?><option value="<?= $c['id_cliente'] ?>" <?= (string)$d['id_cliente'] === (string)$c['id_cliente'] ? 'selected' : '' ?>><?= e($c['nombres']) ?> (<?= e($c['dni_ruc']) ?>)</option><?php endforeach; ?></select></div>
  <div class="col-md-3"><label class="form-label">Fecha de firma</label><input type="date" name="fecha_firma" class="form-control" value="<?= e($d['fecha_firma']) ?>"></div>
  <div class="col-md-3"><label class="form-label">Monto de venta / renta mensual (S/)</label><input name="monto" class="form-control" value="<?= e($d['monto']) ?>"></div>
  <div class="col-md-3"><label class="form-label">Garantía (solo alquiler, S/)</label><input name="garantia" class="form-control" value="<?= e($d['garantia']) ?>"></div>
  <div class="col-md-3"><label class="form-label">Plazo (meses, solo alquiler)</label><input name="plazo_meses" class="form-control" value="<?= e($d['plazo_meses']) ?>"></div>
  <div class="col-12 text-muted small">Comisión: <?= (PORC_COMISION_VENTA * 100) ?> % en ventas; <?= (MESES_COMISION_ALQUILER * 100) ?> % de la primera renta en alquileres (editable en config.php). El tipo de contrato sale de la operación del inmueble.</div>
  <div class="col-12"><button class="btn btn-primary">Generar contrato</button> <a href="contratos.php" class="btn btn-secondary">Cancelar</a></div>
</div></form>
<?php page_bottom();
