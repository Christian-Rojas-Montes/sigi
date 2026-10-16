<?php
require __DIR__ . '/includes/bootstrap.php'; require_login();
$rows = db()->query('SELECT c.*, i.codigo, i.direccion, cl.nombres AS cliente FROM contrato c JOIN inmueble i ON i.id_inmueble=c.id_inmueble JOIN cliente cl ON cl.id_cliente=c.id_cliente ORDER BY c.id_contrato DESC LIMIT 200')->fetchAll();
page_top('Contratos', 'contratos.php');
?>
<p><a href="contrato_form.php" class="btn btn-success btn-sm">+ Nuevo contrato</a></p>
<div class="table-responsive"><table class="table table-sm table-striped bg-white align-middle"><thead><tr><th>N.°</th><th>Tipo</th><th>Inmueble</th><th>Cliente</th><th>Firma</th><th class="text-end">Monto</th><th class="text-end">Garantía</th><th class="text-end">Comisión</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['numero']) ?></td><td><?= e($r['tipo']) ?></td><td><?= e($r['codigo']) ?> – <?= e($r['direccion']) ?></td><td><?= e($r['cliente']) ?></td>
<td><?= e(date('d/m/Y', strtotime($r['fecha_firma']))) ?></td><td class="text-end"><?= soles($r['monto']) ?></td><td class="text-end"><?= soles($r['garantia']) ?></td><td class="text-end"><?= soles($r['comision']) ?></td>
<td class="text-nowrap"><a class="btn btn-outline-primary btn-sm" target="_blank" href="contrato_imprimir.php?id=<?= $r['id_contrato'] ?>">Ver / PDF</a>
<a class="btn btn-outline-secondary btn-sm" href="pagos.php?contrato=<?= $r['id_contrato'] ?>">Pagos</a></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="9" class="text-center text-muted">Aún no hay contratos.</td></tr><?php endif; ?></tbody></table></div>
<?php page_bottom();
