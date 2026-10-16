<?php
require __DIR__ . '/includes/bootstrap.php'; require_login();
$id = (int)q('id');
$d = ['nombres' => '', 'dni_ruc' => '', 'telefono' => '', 'email' => '', 'tipo' => 'inquilino', 'presupuesto' => '', 'consentimiento' => 0];
if ($id) { $st = db()->prepare('SELECT * FROM cliente WHERE id_cliente = ?'); $st->execute([$id]); $d = $st->fetch() ?: $d; }
$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $consintio = $d['consentimiento'];   // valor previo
    foreach (['nombres', 'dni_ruc', 'telefono', 'email', 'tipo', 'presupuesto'] as $k) { $d[$k] = p($k); }
    $d['consentimiento'] = isset($_POST['consentimiento']) ? 1 : 0;
    if ($d['nombres'] === '') { $err[] = 'El nombre es obligatorio.'; }
    if (!validar_doc($d['dni_ruc'])) { $err[] = 'DNI (8 dígitos) o RUC (11 dígitos) no válido.'; }
    if (!in_array($d['tipo'], ['comprador', 'inquilino'], true)) { $err[] = 'Tipo de cliente no válido.'; }
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) { $err[] = 'Correo electrónico no válido.'; }
    if ($d['presupuesto'] !== '' && (!is_numeric($d['presupuesto']) || (float)$d['presupuesto'] < 0)) { $err[] = 'Presupuesto no válido.'; }
    if (!$d['consentimiento']) { $err[] = 'Debe registrarse el consentimiento de tratamiento de datos personales (Ley N.° 29733).'; }
    if (!$err) {
        $pres = $d['presupuesto'] === '' ? null : $d['presupuesto'];
        try {
            if ($id) {
                db()->prepare('UPDATE cliente SET nombres=?, dni_ruc=?, telefono=?, email=?, tipo=?, presupuesto=?, consentimiento=1, fecha_consentimiento=COALESCE(fecha_consentimiento, NOW()) WHERE id_cliente=?')
                    ->execute([$d['nombres'], $d['dni_ruc'], $d['telefono'], $d['email'], $d['tipo'], $pres, $id]);
                audit('cliente_editar', $d['dni_ruc']);
            } else {
                db()->prepare('INSERT INTO cliente (nombres, dni_ruc, telefono, email, tipo, presupuesto, consentimiento, fecha_consentimiento) VALUES (?,?,?,?,?,?,1,NOW())')
                    ->execute([$d['nombres'], $d['dni_ruc'], $d['telefono'], $d['email'], $d['tipo'], $pres]);
                audit('cliente_crear', $d['dni_ruc']);
            }
            flash('ok', 'Cliente guardado.'); redirect('clientes.php');
        } catch (PDOException $e) { $err[] = ($e->getCode() === '23000') ? 'Ya existe un cliente con ese DNI/RUC.' : 'Error al guardar.'; }
    }
}
$buscar = q('buscar');
$st = db()->prepare('SELECT * FROM cliente WHERE nombres LIKE ? OR dni_ruc LIKE ? ORDER BY nombres');
$st->execute(['%' . $buscar . '%', '%' . $buscar . '%']);
$rows = $st->fetchAll();
page_top('Clientes', 'clientes.php');
foreach ($err as $m) { echo '<div class="alert alert-danger py-2">' . e($m) . '</div>'; }
?>
<form method="post" class="card card-body mb-3"><?= csrf_field() ?><div class="row g-2">
  <div class="col-md-4"><label class="form-label small">Nombres</label><input name="nombres" class="form-control" value="<?= e($d['nombres']) ?>"></div>
  <div class="col-md-2"><label class="form-label small">DNI / RUC</label><input name="dni_ruc" class="form-control" value="<?= e($d['dni_ruc']) ?>"></div>
  <div class="col-md-2"><label class="form-label small">Teléfono</label><input name="telefono" class="form-control" value="<?= e($d['telefono']) ?>"></div>
  <div class="col-md-4"><label class="form-label small">Correo</label><input name="email" class="form-control" value="<?= e($d['email']) ?>"></div>
  <div class="col-md-2"><label class="form-label small">Tipo</label><select name="tipo" class="form-select"><option <?= $d['tipo'] === 'inquilino' ? 'selected' : '' ?>>inquilino</option><option <?= $d['tipo'] === 'comprador' ? 'selected' : '' ?>>comprador</option></select></div>
  <div class="col-md-2"><label class="form-label small">Presupuesto (S/)</label><input name="presupuesto" class="form-control" value="<?= e($d['presupuesto']) ?>"></div>
  <div class="col-md-6 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="consentimiento" id="cons" <?= $d['consentimiento'] ? 'checked' : '' ?>>
    <label class="form-check-label small" for="cons">El cliente acepta el tratamiento de sus datos personales (Ley N.° 29733)</label></div></div>
  <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100"><?= $id ? 'Guardar' : 'Añadir' ?></button></div>
</div></form>
<form class="row g-2 mb-2"><div class="col-auto"><input name="buscar" value="<?= e($buscar) ?>" class="form-control form-control-sm" placeholder="Buscar por nombre o DNI/RUC"></div><div class="col-auto"><button class="btn btn-outline-secondary btn-sm">Buscar</button></div></form>
<table class="table table-sm table-striped bg-white"><thead><tr><th>Nombres</th><th>DNI/RUC</th><th>Teléfono</th><th>Tipo</th><th>Presupuesto</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['nombres']) ?></td><td><?= e($r['dni_ruc']) ?></td><td><?= e($r['telefono']) ?></td><td><?= e($r['tipo']) ?></td><td><?= $r['presupuesto'] !== null ? soles($r['presupuesto']) : '—' ?></td>
<td><a class="btn btn-outline-primary btn-sm" href="clientes.php?id=<?= $r['id_cliente'] ?>">Editar</a></td></tr><?php endforeach; ?>
</tbody></table>
<?php page_bottom();
