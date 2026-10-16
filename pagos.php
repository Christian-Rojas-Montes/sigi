<?php
require __DIR__ . '/includes/bootstrap.php'; require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)p('id_pago');
    $st = db()->prepare("UPDATE pago SET estado='pagado', fecha_pago=CURDATE(), nro_comprobante=? WHERE id_pago=? AND estado='pendiente'");
    $st->execute([sprintf('REC-%06d', $id), $id]);
    if ($st->rowCount()) { audit('pago_cobrar', 'id_pago ' . $id); flash('ok', 'Pago registrado. Recibo interno REC-' . sprintf('%06d', $id) . '.'); }
    redirect('pagos.php?' . http_build_query(['contrato' => p('contrato'), 'estado' => p('estado')]));
}
$estado = q('estado'); $contrato = (int)q('contrato');
$sql = 'SELECT p.*, c.numero, cl.nombres AS cliente, i.codigo FROM pago p JOIN contrato c ON c.id_contrato=p.id_contrato JOIN cliente cl ON cl.id_cliente=c.id_cliente JOIN inmueble i ON i.id_inmueble=c.id_inmueble WHERE 1=1';
$par = [];
if ($contrato) { $sql .= ' AND p.id_contrato = ?'; $par[] = $contrato; }
if ($estado === 'vencido') { $sql .= " AND p.estado='pendiente' AND p.fecha_vencimiento < CURDATE()"; }
elseif (in_array($estado, ['pendiente', 'pagado'], true)) { $sql .= ' AND p.estado = ?'; $par[] = $estado; }
$sql .= ' ORDER BY p.fecha_vencimiento, p.id_pago LIMIT 300';
$st = db()->prepare($sql); $st->execute($par); $rows = $st->fetchAll();
page_top('Pagos y cobros', 'pagos.php');
?>
<form class="row g-2 mb-3"><input type="hidden" name="contrato" value="<?= $contrato ?: '' ?>">
  <div class="col-auto"><select name="estado" class="form-select form-select-sm"><option value="">Todos</option>
    <?php foreach (['pendiente' => 'Pendientes', 'vencido' => 'Vencidos', 'pagado' => 'Pagados'] as $k => $lab): ?><option value="<?= $k ?>" <?= $estado === $k ? 'selected' : '' ?>><?= $lab ?></option><?php endforeach; ?></select></div>
  <div class="col-auto"><button class="btn btn-outline-secondary btn-sm">Filtrar</button> <?php if ($contrato): ?><a class="btn btn-link btn-sm" href="pagos.php">Quitar filtro de contrato</a><?php endif; ?></div></form>
<div class="table-responsive"><table class="table table-sm bg-white align-middle"><thead><tr><th>Contrato</th><th>Inmueble</th><th>Cliente</th><th>Concepto</th><th class="text-end">Monto</th><th>Vence</th><th>Estado</th><th>Comprobante</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): $venc = $r['estado'] === 'pendiente' && $r['fecha_vencimiento'] < date('Y-m-d'); ?>
<tr class="<?= $venc ? 'table-danger' : '' ?>"><td><?= e($r['numero']) ?></td><td><?= e($r['codigo']) ?></td><td><?= e($r['cliente']) ?></td><td><?= e($r['concepto']) ?></td><td class="text-end"><?= soles($r['monto']) ?></td>
<td><?= e(date('d/m/Y', strtotime($r['fecha_vencimiento']))) ?><?= $venc ? ' <b>(vencido)</b>' : '' ?></td><td><?= badge_estado($r['estado']) ?></td><td><?= e($r['nro_comprobante']) ?></td>
<td><?php if ($r['estado'] === 'pendiente'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id_pago" value="<?= $r['id_pago'] ?>"><input type="hidden" name="contrato" value="<?= $contrato ?: '' ?>"><input type="hidden" name="estado" value="<?= e($estado) ?>">
<button class="btn btn-success btn-sm" onclick="return confirm('¿Registrar el cobro de <?= e(soles($r['monto'])) ?>?')">Cobrar</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="9" class="text-center text-muted">Sin pagos para mostrar.</td></tr><?php endif; ?></tbody></table></div>
<p class="text-muted small">El número «REC-» es un recibo interno. Los comprobantes electrónicos (boleta/factura) deben emitirse con el sistema de SUNAT o un OSE autorizado.</p>
<?php page_bottom();
