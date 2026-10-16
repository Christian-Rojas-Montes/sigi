<?php
require __DIR__ . '/includes/bootstrap.php'; require_login();
$u = usuario_actual(); $err = [];
$d = ['id_inmueble' => q('inmueble'), 'id_cliente' => '', 'fecha_hora' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (p('accion') === 'crear') {
        $d = ['id_inmueble' => p('id_inmueble'), 'id_cliente' => p('id_cliente'), 'fecha_hora' => p('fecha_hora')];
        $inm = InmuebleRepo::find((int)$d['id_inmueble']);
        $ts = strtotime(str_replace('T', ' ', $d['fecha_hora']));
        if (!$inm || !in_array($inm['estado'], ['disponible', 'reservado'], true)) { $err[] = 'Seleccione un inmueble disponible.'; }
        if (!$d['id_cliente']) { $err[] = 'Seleccione un cliente.'; }
        if (!$ts || $ts < time()) { $err[] = 'Indique una fecha y hora futuras.'; }
        if (!$err) {
            $fh = date('Y-m-d H:i:s', $ts);
            if (VisitaRepo::ocupado((int)$inm['id_inmueble'], $u['id'], $fh)) {
                $err[] = 'Horario ocupado (inmueble o agente). Primer horario libre sugerido: ' . date('d/m/Y H:i', strtotime(VisitaRepo::siguienteLibre((int)$inm['id_inmueble'], $u['id'], $fh))) . '.';
            } else {
                db()->prepare('INSERT INTO visita (id_inmueble, id_cliente, id_usuario, fecha_hora) VALUES (?,?,?,?)')->execute([(int)$d['id_inmueble'], (int)$d['id_cliente'], $u['id'], $fh]);
                audit('visita_crear', $inm['codigo'] . ' ' . $fh);
                flash('ok', 'Visita programada. Puede avisar al cliente con el botón de WhatsApp de la lista.');
                redirect('visitas.php');
            }
        }
    } else {
        $nuevo = p('accion') === 'cancelar' ? 'cancelada' : 'realizada';
        db()->prepare("UPDATE visita SET estado = ? WHERE id_visita = ? AND estado = 'programada'")->execute([$nuevo, (int)p('id')]);
        audit('visita_' . $nuevo, (string)(int)p('id'));
        flash('ok', 'Visita ' . $nuevo . '.'); redirect('visitas.php');
    }
}
$inmuebles = db()->query("SELECT id_inmueble, codigo, direccion FROM inmueble WHERE estado IN ('disponible','reservado') ORDER BY codigo")->fetchAll();
$clientes = db()->query('SELECT id_cliente, nombres FROM cliente ORDER BY nombres')->fetchAll();
$rows = db()->query('SELECT v.*, i.codigo, i.direccion, c.nombres, c.telefono, u.usuario FROM visita v JOIN inmueble i ON i.id_inmueble=v.id_inmueble JOIN cliente c ON c.id_cliente=v.id_cliente JOIN usuario u ON u.id_usuario=v.id_usuario ORDER BY v.fecha_hora DESC LIMIT 100')->fetchAll();
page_top('Visitas', 'visitas.php');
foreach ($err as $m) { echo '<div class="alert alert-danger py-2">' . e($m) . '</div>'; }
?>
<form method="post" class="card card-body mb-3"><?= csrf_field() ?><input type="hidden" name="accion" value="crear"><div class="row g-2">
  <div class="col-md-4"><label class="form-label small">Inmueble</label><select name="id_inmueble" class="form-select"><option value="">-- Seleccione --</option>
    <?php foreach ($inmuebles as $i): ?><option value="<?= $i['id_inmueble'] ?>" <?= (string)$d['id_inmueble'] === (string)$i['id_inmueble'] ? 'selected' : '' ?>><?= e($i['codigo']) ?> – <?= e($i['direccion']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><label class="form-label small">Cliente</label><select name="id_cliente" class="form-select"><option value="">-- Seleccione --</option>
    <?php foreach ($clientes as $c): ?><option value="<?= $c['id_cliente'] ?>" <?= (string)$d['id_cliente'] === (string)$c['id_cliente'] ? 'selected' : '' ?>><?= e($c['nombres']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><label class="form-label small">Fecha y hora</label><input type="datetime-local" name="fecha_hora" class="form-control" value="<?= e($d['fecha_hora']) ?>"></div>
  <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Agendar</button></div>
</div></form>
<table class="table table-sm table-striped bg-white align-middle"><thead><tr><th>Fecha</th><th>Inmueble</th><th>Cliente</th><th>Agente</th><th>Estado</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r):
  $msg = rawurlencode('Hola ' . $r['nombres'] . ', le recordamos su visita al inmueble ' . $r['codigo'] . ' (' . $r['direccion'] . ') el ' . date('d/m/Y \a \l\a\s H:i', strtotime($r['fecha_hora'])) . '.'); ?>
<tr><td><?= e(date('d/m/Y H:i', strtotime($r['fecha_hora']))) ?></td><td><?= e($r['codigo']) ?> – <?= e($r['direccion']) ?></td><td><?= e($r['nombres']) ?></td><td><?= e($r['usuario']) ?></td><td><?= badge_estado($r['estado']) ?></td>
<td class="text-nowrap"><?php if ($r['estado'] === 'programada'): ?>
  <?php if ($r['telefono']): ?><a class="btn btn-outline-success btn-sm" target="_blank" rel="noopener" href="https://wa.me/<?= e(telefono_wa($r['telefono'])) ?>?text=<?= $msg ?>">Avisar (WhatsApp)</a><?php endif; ?>
  <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id_visita'] ?>">
    <button name="accion" value="realizar" class="btn btn-outline-primary btn-sm">Realizada</button>
    <button name="accion" value="cancelar" class="btn btn-outline-danger btn-sm">Cancelar</button></form><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table>
<?php page_bottom();
