<?php
require __DIR__ . '/includes/bootstrap.php'; require_admin();
$r = q('r', 'estado');
$desde = q('desde', date('Y-m-01')); $hasta = q('hasta', date('Y-m-t'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) { $desde = date('Y-m-01'); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) { $hasta = date('Y-m-t'); }
$titulos = ['estado' => 'Inmuebles por estado', 'contratos' => 'Contratos por periodo', 'ingresos' => 'Ingresos cobrados por periodo',
            'morosidad' => 'Deudas pendientes y vencidas', 'uif' => 'Operaciones para revisión UIF'];
if (!isset($titulos[$r])) { $r = 'estado'; }

switch ($r) {
    case 'contratos':
        $cols = ['N.°', 'Tipo', 'Inmueble', 'Cliente', 'Firma', 'Monto', 'Comisión'];
        $st = db()->prepare('SELECT c.numero, c.tipo, i.codigo, cl.nombres, c.fecha_firma, c.monto, c.comision FROM contrato c JOIN inmueble i ON i.id_inmueble=c.id_inmueble JOIN cliente cl ON cl.id_cliente=c.id_cliente WHERE c.fecha_firma BETWEEN ? AND ? ORDER BY c.fecha_firma');
        $st->execute([$desde, $hasta]); break;
    case 'ingresos':
        $cols = ['Fecha de pago', 'Concepto', 'Contrato', 'Monto', 'Comprobante'];
        $st = db()->prepare("SELECT p.fecha_pago, p.concepto, c.numero, p.monto, p.nro_comprobante FROM pago p JOIN contrato c ON c.id_contrato=p.id_contrato WHERE p.estado='pagado' AND p.fecha_pago BETWEEN ? AND ? ORDER BY p.fecha_pago");
        $st->execute([$desde, $hasta]); break;
    case 'morosidad':
        $cols = ['Contrato', 'Cliente', 'Teléfono', 'Concepto', 'Monto', 'Vencimiento', 'Días de atraso'];
        $st = db()->query("SELECT c.numero, cl.nombres, cl.telefono, p.concepto, p.monto, p.fecha_vencimiento, DATEDIFF(CURDATE(), p.fecha_vencimiento) AS dias FROM pago p JOIN contrato c ON c.id_contrato=p.id_contrato JOIN cliente cl ON cl.id_cliente=c.id_cliente WHERE p.estado='pendiente' AND p.fecha_vencimiento < CURDATE() ORDER BY p.fecha_vencimiento"); break;
    case 'uif':
        $cols = ['N.°', 'Tipo', 'Cliente', 'DNI/RUC', 'Propietario', 'Monto', 'Firma'];
        $st = db()->prepare('SELECT c.numero, c.tipo, cl.nombres, cl.dni_ruc, pr.nombres AS prop, c.monto, c.fecha_firma FROM contrato c JOIN cliente cl ON cl.id_cliente=c.id_cliente JOIN inmueble i ON i.id_inmueble=c.id_inmueble JOIN propietario pr ON pr.id_propietario=i.id_propietario WHERE c.monto >= ? AND c.fecha_firma BETWEEN ? AND ? ORDER BY c.fecha_firma');
        $st->execute([UMBRAL_UIF_SOLES, $desde, $hasta]); break;
    default:
        $cols = ['Estado', 'Cantidad'];
        $st = db()->query('SELECT estado, COUNT(*) AS total FROM inmueble GROUP BY estado ORDER BY estado');
}
$data = $st->fetchAll(PDO::FETCH_NUM);

if (q('csv') === '1') {
    audit('reporte_csv', $r);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_' . $r . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, $cols, ';'); foreach ($data as $row) { fputcsv($o, $row, ';'); }
    fclose($o); exit;
}
page_top('Reportes', 'reportes.php');
?>
<ul class="nav nav-pills mb-3">
<?php foreach ($titulos as $k => $t): ?><li class="nav-item"><a class="nav-link <?= $r === $k ? 'active' : '' ?>" href="reportes.php?r=<?= $k ?>&desde=<?= e($desde) ?>&hasta=<?= e($hasta) ?>"><?= e($t) ?></a></li><?php endforeach; ?>
</ul>
<?php if (in_array($r, ['contratos', 'ingresos', 'uif'], true)): ?>
<form class="row g-2 mb-3"><input type="hidden" name="r" value="<?= e($r) ?>">
  <div class="col-auto"><label class="form-label small mb-0">Desde</label><input type="date" name="desde" value="<?= e($desde) ?>" class="form-control form-control-sm"></div>
  <div class="col-auto"><label class="form-label small mb-0">Hasta</label><input type="date" name="hasta" value="<?= e($hasta) ?>" class="form-control form-control-sm"></div>
  <div class="col-auto d-flex align-items-end"><button class="btn btn-primary btn-sm">Aplicar</button></div></form>
<?php endif; ?>
<?php if ($r === 'uif'): ?><div class="alert alert-info py-2 small">Se listan contratos con monto ≥ <?= soles(UMBRAL_UIF_SOLES) ?> (parámetro <code>UMBRAL_UIF_SOLES</code> en config.php). Confirme el umbral y el procedimiento vigentes con su oficial de cumplimiento.</div><?php endif; ?>
<h2 class="h5"><?= e($titulos[$r]) ?></h2>
<table class="table table-sm table-striped bg-white"><thead><tr><?php foreach ($cols as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php $tot = 0; foreach ($data as $row): ?><tr><?php foreach ($row as $i => $v): $num = is_numeric($v) && in_array($cols[$i], ['Monto', 'Comisión'], true); ?>
<td class="<?= $num ? 'text-end' : '' ?>"><?= $num ? soles($v) : e($v) ?></td><?php endforeach; ?></tr>
<?php if ($r === 'ingresos') { $tot += (float)$row[3]; } endforeach; ?>
<?php if (!$data): ?><tr><td colspan="<?= count($cols) ?>" class="text-center text-muted">Sin datos.</td></tr><?php endif; ?>
<?php if ($r === 'ingresos' && $data): ?><tr class="fw-bold"><td colspan="3">Total cobrado</td><td class="text-end"><?= soles($tot) ?></td><td></td></tr><?php endif; ?>
</tbody></table>
<a class="btn btn-outline-success btn-sm" href="reportes.php?r=<?= e($r) ?>&desde=<?= e($desde) ?>&hasta=<?= e($hasta) ?>&csv=1">Exportar a CSV (abre en Excel)</a>
<?php page_bottom();
