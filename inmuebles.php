<?php
require __DIR__ . '/includes/bootstrap.php'; require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)p('id'); $r = InmuebleRepo::find($id);
    if ($r) {
        try {
            $obj = Inmueble::fromRow($r);
            $acc = p('accion');
            if ($acc === 'baja') { $obj->cambiarEstado('baja'); }
            elseif ($acc === 'reservar') { $obj->cambiarEstado('reservado'); }
            elseif ($acc === 'liberar') { $obj->cambiarEstado('disponible'); }
            InmuebleRepo::cambiarEstado($id, $obj->estado);
            audit('inmueble_' . $acc, $r['codigo']);
            flash('ok', 'Inmueble ' . $r['codigo'] . ' actualizado.');
        } catch (Exception $e) { flash('error', $e->getMessage()); }
    }
    redirect('inmuebles.php');
}
$f = ['tipo' => q('tipo'), 'operacion' => q('operacion'), 'distrito' => q('distrito'), 'precio_max' => q('precio_max'),
      'area_min' => q('area_min'), 'estado' => isset($_GET['estado']) ? q('estado') : 'disponible'];
$rows = InmuebleRepo::buscar($f);
page_top('Inmuebles', 'inmuebles.php');
?>
<form method="get" class="card card-body mb-3"><div class="row g-2 align-items-end">
  <div class="col-6 col-md-2"><label class="form-label small">Tipo</label><select name="tipo" class="form-select form-select-sm"><option value="">Todos</option>
    <?php foreach (Inmueble::TIPOS as $t): ?><option <?= $f['tipo'] === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small">Operación</label><select name="operacion" class="form-select form-select-sm"><option value="">Todas</option>
    <?php foreach (Inmueble::OPERACIONES as $t): ?><option <?= $f['operacion'] === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small">Distrito</label><input name="distrito" value="<?= e($f['distrito']) ?>" class="form-control form-control-sm"></div>
  <div class="col-6 col-md-2"><label class="form-label small">Precio máx. (S/)</label><input name="precio_max" value="<?= e($f['precio_max']) ?>" class="form-control form-control-sm"></div>
  <div class="col-6 col-md-2"><label class="form-label small">Área mín. (m²)</label><input name="area_min" value="<?= e($f['area_min']) ?>" class="form-control form-control-sm"></div>
  <div class="col-6 col-md-2"><label class="form-label small">Estado</label><select name="estado" class="form-select form-select-sm"><option value="">Todos</option>
    <?php foreach (['disponible', 'reservado', 'vendido', 'alquilado', 'baja'] as $t): ?><option <?= $f['estado'] === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
  <div class="col-12"><button class="btn btn-primary btn-sm">Buscar</button> <a href="inmuebles.php" class="btn btn-outline-secondary btn-sm">Limpiar</a>
    <a href="inmueble_form.php" class="btn btn-success btn-sm float-end">+ Nuevo inmueble</a></div>
</div></form>
<div class="table-responsive"><table class="table table-sm table-striped bg-white align-middle">
<thead><tr><th>Código</th><th>Tipo</th><th>Operación</th><th>Dirección</th><th>Distrito</th><th class="text-end">m²</th><th class="text-end">Precio</th><th>Estado</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): $abierto = in_array($r['estado'], ['disponible', 'reservado'], true); ?>
<tr><td><?= e($r['codigo']) ?></td><td><?= e($r['tipo']) ?></td><td><?= e($r['operacion']) ?></td><td><?= e($r['direccion']) ?></td><td><?= e($r['distrito']) ?></td>
<td class="text-end"><?= number_format($r['area_m2'], 2) ?></td><td class="text-end"><?= soles($r['precio']) ?></td><td><?= badge_estado($r['estado']) ?></td>
<td class="text-nowrap">
<?php if ($abierto): ?>
  <a class="btn btn-outline-primary btn-sm" href="inmueble_form.php?id=<?= $r['id_inmueble'] ?>">Editar</a>
  <a class="btn btn-outline-secondary btn-sm" href="visitas.php?inmueble=<?= $r['id_inmueble'] ?>">Visita</a>
  <a class="btn btn-outline-success btn-sm" href="contrato_form.php?inmueble=<?= $r['id_inmueble'] ?>">Contrato</a>
  <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id_inmueble'] ?>">
    <?php if ($r['estado'] === 'disponible'): ?><button name="accion" value="reservar" class="btn btn-outline-warning btn-sm">Reservar</button>
    <?php else: ?><button name="accion" value="liberar" class="btn btn-outline-warning btn-sm">Liberar</button><?php endif; ?>
    <button name="accion" value="baja" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Dar de baja este inmueble?')">Baja</button></form>
<?php endif; ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="9" class="text-center text-muted">Sin resultados. Pruebe ampliando los filtros.</td></tr><?php endif; ?>
</tbody></table></div>
<?php page_bottom();
