<?php
require __DIR__ . '/includes/bootstrap.php'; require_login();
$conteo = InmuebleRepo::contarPorEstado();
$prox = db()->query("SELECT v.fecha_hora, i.codigo, c.nombres FROM visita v JOIN inmueble i ON i.id_inmueble=v.id_inmueble JOIN cliente c ON c.id_cliente=v.id_cliente WHERE v.estado='programada' AND v.fecha_hora >= NOW() ORDER BY v.fecha_hora LIMIT 5")->fetchAll();
$venc = (int)db()->query("SELECT COUNT(*) FROM pago WHERE estado='pendiente' AND fecha_vencimiento < CURDATE()")->fetchColumn();
page_top('Panel principal', 'index.php');
?>
<div class="row g-3 mb-4">
<?php foreach (['disponible' => 'success', 'reservado' => 'warning', 'alquilado' => 'info', 'vendido' => 'secondary'] as $est => $cls): ?>
  <div class="col-6 col-md-3"><div class="card border-<?= $cls ?>"><div class="card-body text-center">
    <div class="display-6"><?= (int)($conteo[$est] ?? 0) ?></div><div class="text-muted text-capitalize"><?= $est ?></div></div></div></div>
<?php endforeach; ?>
</div>
<div class="row g-3">
  <div class="col-md-6"><div class="card"><div class="card-header">Próximas visitas</div>
    <ul class="list-group list-group-flush">
    <?php foreach ($prox as $v): ?><li class="list-group-item"><?= e(date('d/m/Y H:i', strtotime($v['fecha_hora']))) ?> – <?= e($v['codigo']) ?> – <?= e($v['nombres']) ?></li><?php endforeach; ?>
    <?php if (!$prox): ?><li class="list-group-item text-muted">Sin visitas programadas.</li><?php endif; ?>
    </ul></div></div>
  <div class="col-md-6"><div class="card"><div class="card-header">Alertas</div><div class="card-body">
    <?php if ($venc): ?><a href="pagos.php?estado=vencido" class="text-danger fw-bold"><?= $venc ?> pago(s) vencido(s) sin cobrar</a>
    <?php else: ?><span class="text-success">No hay pagos vencidos.</span><?php endif; ?>
  </div></div></div>
</div>
<?php page_bottom();
