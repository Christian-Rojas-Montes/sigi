<?php
require __DIR__ . '/includes/bootstrap.php'; require_admin();
$me = usuario_actual(); $err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $acc = p('accion'); $id = (int)p('id');
    if ($acc === 'crear') {
        $usr = p('usuario'); $nom = p('nombres'); $clave = p('clave'); $rol = p('rol');
        if (!preg_match('/^[a-z0-9_.]{3,30}$/', $usr)) { $err[] = 'Usuario: 3 a 30 caracteres en minúsculas, números, punto o guion bajo.'; }
        if ($nom === '') { $err[] = 'El nombre es obligatorio.'; }
        if (strlen($clave) < 8) { $err[] = 'La contraseña debe tener al menos 8 caracteres.'; }
        if (!in_array($rol, ['admin', 'agente'], true)) { $err[] = 'Rol no válido.'; }
        if (!$err) {
            try {
                db()->prepare('INSERT INTO usuario (usuario, nombres, clave_hash, rol) VALUES (?,?,?,?)')->execute([$usr, $nom, password_hash($clave, PASSWORD_DEFAULT), $rol]);
                audit('usuario_crear', $usr); flash('ok', 'Usuario creado.'); redirect('usuarios.php');
            } catch (PDOException $e) { $err[] = ($e->getCode() === '23000') ? 'Ese usuario ya existe.' : 'Error al guardar.'; }
        }
    } elseif ($acc === 'estado' && $id !== $me['id']) {
        $nuevoEstado = p('estado_nuevo');
        if (!in_array($nuevoEstado, ['activo', 'bloqueado'], true)) { $err[] = 'Estado no válido.'; }
        else {
            db()->prepare('UPDATE usuario SET estado = ?, intentos = 0, bloqueado_hasta = NULL WHERE id_usuario = ?')->execute([$nuevoEstado, $id]);
            audit('usuario_estado', $nuevoEstado . ' ' . $id); flash('ok', 'Estado actualizado.'); redirect('usuarios.php');
        }
    } elseif ($acc === 'clave') {
        if (strlen(p('clave')) < 8) { $err[] = 'La nueva contraseña debe tener al menos 8 caracteres.'; }
        else {
            db()->prepare('UPDATE usuario SET clave_hash = ?, intentos = 0, bloqueado_hasta = NULL WHERE id_usuario = ?')->execute([password_hash(p('clave'), PASSWORD_DEFAULT), $id]);
            audit('usuario_clave', (string)$id); flash('ok', 'Contraseña restablecida.'); redirect('usuarios.php');
        }
    }
}
$rows = db()->query('SELECT * FROM usuario ORDER BY usuario')->fetchAll();
$aud = db()->query('SELECT a.*, u.usuario FROM auditoria a LEFT JOIN usuario u ON u.id_usuario=a.id_usuario ORDER BY a.id_auditoria DESC LIMIT 30')->fetchAll();
page_top('Usuarios y auditoría', 'usuarios.php');
foreach ($err as $m) { echo '<div class="alert alert-danger py-2">' . e($m) . '</div>'; }
?>
<form method="post" class="card card-body mb-3"><?= csrf_field() ?><input type="hidden" name="accion" value="crear"><div class="row g-2">
  <div class="col-md-2"><label class="form-label small">Usuario</label><input name="usuario" class="form-control"></div>
  <div class="col-md-3"><label class="form-label small">Nombres</label><input name="nombres" class="form-control"></div>
  <div class="col-md-3"><label class="form-label small">Contraseña (mín. 8)</label><input type="password" name="clave" class="form-control"></div>
  <div class="col-md-2"><label class="form-label small">Rol</label><select name="rol" class="form-select"><option>agente</option><option>admin</option></select></div>
  <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Crear usuario</button></div></div></form>
<table class="table table-sm table-striped bg-white align-middle"><thead><tr><th>Usuario</th><th>Nombres</th><th>Rol</th><th>Estado</th><th>Restablecer contraseña</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $u): $bloqueoTemporal = $u['estado'] === 'activo' && $u['bloqueado_hasta'] && strtotime($u['bloqueado_hasta']) > time(); $estaBloqueado = $u['estado'] === 'bloqueado' || $bloqueoTemporal; $estadoTexto = $u['estado'] === 'bloqueado' ? 'bloqueado' : ($bloqueoTemporal ? 'bloqueado temporal' : 'activo'); ?><tr><td><?= e($u['usuario']) ?></td><td><?= e($u['nombres']) ?></td><td><?= e($u['rol']) ?></td><td><span class="badge text-bg-<?= $estaBloqueado ? ($bloqueoTemporal ? 'warning' : 'dark') : 'success' ?>"><?= e($estadoTexto) ?></span></td>
<td><form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="accion" value="clave"><input type="hidden" name="id" value="<?= $u['id_usuario'] ?>"><input type="password" name="clave" class="form-control form-control-sm" placeholder="Nueva contraseña"><button class="btn btn-outline-primary btn-sm">Cambiar</button></form></td>
<td><?php if ((int)$u['id_usuario'] !== $me['id']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= $u['id_usuario'] ?>"><input type="hidden" name="estado_nuevo" value="<?= $estaBloqueado ? 'activo' : 'bloqueado' ?>"><button class="btn btn-outline-warning btn-sm"><?= $estaBloqueado ? 'Desbloquear' : 'Bloquear' ?></button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table>
<h2 class="h5 mt-4">Últimos movimientos (auditoría)</h2>
<table class="table table-sm bg-white small"><thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Detalle</th><th>IP</th></tr></thead><tbody>
<?php foreach ($aud as $a): ?><tr><td><?= e($a['fecha']) ?></td><td><?= e($a['usuario'] ?? '—') ?></td><td><?= e($a['accion']) ?></td><td><?= e($a['detalle']) ?></td><td><?= e($a['ip']) ?></td></tr><?php endforeach; ?></tbody></table>
<?php page_bottom();
