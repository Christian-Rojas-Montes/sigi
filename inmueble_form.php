<?php
require __DIR__ . '/includes/bootstrap.php'; require_login();
$id = (int)q('id'); $row = $id ? InmuebleRepo::find($id) : null;
if ($id && (!$row || !in_array($row['estado'], ['disponible', 'reservado'], true))) { flash('error', 'Inmueble no encontrado o ya cerrado.'); redirect('inmuebles.php'); }
$d = $row ?: ['tipo' => 'departamento', 'operacion' => 'alquiler', 'id_propietario' => '', 'direccion' => '', 'distrito' => '', 'area_m2' => '', 'precio' => '', 'partida_registral' => ''];
$errores = [];
$props = db()->query('SELECT id_propietario, nombres, dni_ruc FROM propietario ORDER BY nombres')->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['tipo', 'operacion', 'id_propietario', 'direccion', 'distrito', 'area_m2', 'precio', 'partida_registral'] as $k) { $d[$k] = p($k); }
    if (!in_array($d['tipo'], Inmueble::TIPOS, true)) { $errores[] = 'Seleccione el tipo de inmueble.'; }
    if (!in_array($d['operacion'], Inmueble::OPERACIONES, true)) { $errores[] = 'Seleccione la operación.'; }
    if ($d['direccion'] === '' || $d['distrito'] === '') { $errores[] = 'Dirección y distrito son obligatorios.'; }
    if (!is_numeric($d['area_m2']) || (float)$d['area_m2'] <= 0) { $errores[] = 'El área debe ser mayor que cero.'; }
    if (!is_numeric($d['precio']) || (float)$d['precio'] <= 0) { $errores[] = 'El precio debe ser mayor que cero.'; }
    $st = db()->prepare('SELECT COUNT(*) FROM propietario WHERE id_propietario = ?'); $st->execute([(int)$d['id_propietario']]);
    if (!$st->fetchColumn()) { $errores[] = 'Seleccione un propietario (si no existe, regístrelo en Propietarios).'; }
    if (!$errores) {
        $dup = InmuebleRepo::duplicado($d['direccion'], $d['tipo'], $d['operacion'], $id);
        if ($dup) { $errores[] = 'Ya existe un inmueble igual registrado con el código ' . $dup . '.'; }
    }
    if (!$errores) {
        $part = $d['partida_registral'] === '' ? null : $d['partida_registral'];
        if ($id) {
            db()->prepare('UPDATE inmueble SET tipo=?, operacion=?, id_propietario=?, direccion=?, distrito=?, area_m2=?, precio=?, partida_registral=? WHERE id_inmueble=?')
                ->execute([$d['tipo'], $d['operacion'], (int)$d['id_propietario'], $d['direccion'], $d['distrito'], $d['area_m2'], $d['precio'], $part, $id]);
            audit('inmueble_editar', $row['codigo']);
            flash('ok', 'Inmueble ' . $row['codigo'] . ' actualizado.');
        } else {
            $codigo = InmuebleRepo::siguienteCodigo();
            db()->prepare("INSERT INTO inmueble (codigo, tipo, operacion, id_propietario, direccion, distrito, area_m2, precio, partida_registral, estado) VALUES (?,?,?,?,?,?,?,?,?, 'disponible')")
                ->execute([$codigo, $d['tipo'], $d['operacion'], (int)$d['id_propietario'], $d['direccion'], $d['distrito'], $d['area_m2'], $d['precio'], $part]);
            audit('inmueble_crear', $codigo);
            flash('ok', 'Inmueble registrado con código ' . $codigo . ' (estado: disponible).');
        }
        redirect('inmuebles.php');
    }
}
page_top($id ? 'Editar inmueble ' . $row['codigo'] : 'Registrar inmueble', 'inmuebles.php');
foreach ($errores as $m) { echo '<div class="alert alert-danger py-2">' . e($m) . '</div>'; }
?>
<form method="post" class="card card-body"><?= csrf_field() ?><div class="row g-3">
  <div class="col-md-3"><label class="form-label">Tipo</label><select name="tipo" class="form-select"><?php foreach (Inmueble::TIPOS as $t): ?><option <?= $d['tipo'] === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><label class="form-label">Operación</label><select name="operacion" class="form-select"><?php foreach (Inmueble::OPERACIONES as $t): ?><option <?= $d['operacion'] === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
  <div class="col-md-6"><label class="form-label">Propietario</label><select name="id_propietario" class="form-select"><option value="">-- Seleccione --</option>
    <?php foreach ($props as $pr): ?><option value="<?= $pr['id_propietario'] ?>" <?= (string)$d['id_propietario'] === (string)$pr['id_propietario'] ? 'selected' : '' ?>><?= e($pr['nombres']) ?> (<?= e($pr['dni_ruc']) ?>)</option><?php endforeach; ?></select></div>
  <div class="col-md-8"><label class="form-label">Dirección</label><input name="direccion" class="form-control" value="<?= e($d['direccion']) ?>"></div>
  <div class="col-md-4"><label class="form-label">Distrito</label><input name="distrito" class="form-control" value="<?= e($d['distrito']) ?>"></div>
  <div class="col-md-3"><label class="form-label">Área (m²)</label><input name="area_m2" class="form-control" value="<?= e($d['area_m2']) ?>"></div>
  <div class="col-md-3"><label class="form-label">Precio / renta (S/)</label><input name="precio" class="form-control" value="<?= e($d['precio']) ?>"></div>
  <div class="col-md-3"><label class="form-label">Partida registral</label><input name="partida_registral" class="form-control" value="<?= e($d['partida_registral']) ?>"></div>
  <div class="col-12"><button class="btn btn-primary">Guardar</button> <a href="inmuebles.php" class="btn btn-secondary">Cancelar</a>
    <span class="text-muted small ms-2">El código (INM-####) se genera automáticamente; el estado inicial es «disponible».</span></div>
</div></form>
<?php page_bottom();
