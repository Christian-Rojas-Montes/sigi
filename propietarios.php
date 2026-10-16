<?php
require __DIR__ . '/includes/bootstrap.php'; require_login();
$id = (int)q('id');
$d = ['nombres' => '', 'dni_ruc' => '', 'telefono' => '', 'email' => ''];
if ($id) { $st = db()->prepare('SELECT * FROM propietario WHERE id_propietario = ?'); $st->execute([$id]); $d = $st->fetch() ?: $d; }
$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($d as $k => $_) { $d[$k] = p($k); }
    if ($d['nombres'] === '') { $err[] = 'El nombre o razón social es obligatorio.'; }
    if (!validar_doc($d['dni_ruc'])) { $err[] = 'DNI (8 dígitos) o RUC (11 dígitos) no válido.'; }
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) { $err[] = 'Correo electrónico no válido.'; }
    if (!$err) {
        try {
            if ($id) {
                db()->prepare('UPDATE propietario SET nombres=?, dni_ruc=?, telefono=?, email=? WHERE id_propietario=?')->execute([$d['nombres'], $d['dni_ruc'], $d['telefono'], $d['email'], $id]);
                audit('propietario_editar', $d['dni_ruc']);
            } else {
                db()->prepare('INSERT INTO propietario (nombres, dni_ruc, telefono, email) VALUES (?,?,?,?)')->execute([$d['nombres'], $d['dni_ruc'], $d['telefono'], $d['email']]);
                audit('propietario_crear', $d['dni_ruc']);
            }
            flash('ok', 'Propietario guardado.'); redirect('propietarios.php');
        } catch (PDOException $e) { $err[] = ($e->getCode() === '23000') ? 'Ya existe un propietario con ese DNI/RUC.' : 'Error al guardar.'; }
    }
}
$rows = db()->query('SELECT p.*, (SELECT COUNT(*) FROM inmueble i WHERE i.id_propietario = p.id_propietario) AS n FROM propietario p ORDER BY nombres')->fetchAll();
page_top('Propietarios', 'propietarios.php');
foreach ($err as $m) { echo '<div class="alert alert-danger py-2">' . e($m) . '</div>'; }
?>
<form method="post" class="card card-body mb-3"><?= csrf_field() ?><div class="row g-2">
  <div class="col-md-4"><label class="form-label small">Nombres / razón social</label><input name="nombres" class="form-control" value="<?= e($d['nombres']) ?>"></div>
  <div class="col-md-2"><label class="form-label small">DNI / RUC</label><input name="dni_ruc" class="form-control" value="<?= e($d['dni_ruc']) ?>"></div>
  <div class="col-md-2"><label class="form-label small">Teléfono</label><input name="telefono" class="form-control" value="<?= e($d['telefono']) ?>"></div>
  <div class="col-md-3"><label class="form-label small">Correo</label><input name="email" class="form-control" value="<?= e($d['email']) ?>"></div>
  <div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary w-100"><?= $id ? 'Guardar' : 'Añadir' ?></button></div>
</div></form>
<table class="table table-sm table-striped bg-white"><thead><tr><th>Nombres</th><th>DNI/RUC</th><th>Teléfono</th><th>Correo</th><th>Inmuebles</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['nombres']) ?></td><td><?= e($r['dni_ruc']) ?></td><td><?= e($r['telefono']) ?></td><td><?= e($r['email']) ?></td><td><?= (int)$r['n'] ?></td>
<td><a class="btn btn-outline-primary btn-sm" href="propietarios.php?id=<?= $r['id_propietario'] ?>">Editar</a></td></tr><?php endforeach; ?>
</tbody></table>
<?php page_bottom();
