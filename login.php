<?php
require __DIR__ . '/includes/bootstrap.php';
if (usuario_actual()) { redirect('index.php'); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $st = db()->prepare('SELECT * FROM usuario WHERE usuario = ?');
    $st->execute([p('usuario')]);
    $u = $st->fetch();
    if (!$u) {
        $error = 'Usuario o contraseña incorrectos.';
    } elseif ($u['estado'] === 'bloqueado') {
        $error = 'Usuario bloqueado. Contacte al administrador.';
    } elseif ($u['bloqueado_hasta'] && strtotime($u['bloqueado_hasta']) > time()) {
        $error = 'Demasiados intentos fallidos. Intente de nuevo después de las ' . date('H:i', strtotime($u['bloqueado_hasta'])) . '.';
    } elseif (password_verify(p('clave'), $u['clave_hash'])) {
        db()->prepare('UPDATE usuario SET intentos = 0, bloqueado_hasta = NULL WHERE id_usuario = ?')->execute([$u['id_usuario']]);
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int)$u['id_usuario'], 'usuario' => $u['usuario'], 'nombres' => $u['nombres'], 'rol' => $u['rol']];
        audit('login', $u['usuario']);
        redirect('index.php');
    } else {
        $n = (int)$u['intentos'] + 1;
        if ($n >= 3) {
            db()->prepare('UPDATE usuario SET intentos = 0, bloqueado_hasta = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id_usuario = ?')->execute([$u['id_usuario']]);
            $error = 'Tres intentos fallidos: el usuario queda bloqueado 15 minutos.';
        } else {
            db()->prepare('UPDATE usuario SET intentos = ? WHERE id_usuario = ?')->execute([$n, $u['id_usuario']]);
            $error = 'Usuario o contraseña incorrectos. Intento ' . $n . ' de 3.';
        }
        audit('login_fallido', p('usuario'));
    }
}
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Iniciar sesión – SIGI</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh">
<div class="container" style="max-width:380px">
  <div class="card shadow-sm"><div class="card-body p-4">
    <h1 class="h4 text-center text-primary mb-1">SIGI</h1>
    <p class="text-center text-muted small mb-4">Sistema de Gestión Inmobiliaria</p>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label">Usuario</label><input name="usuario" class="form-control" required autofocus></div>
      <div class="mb-3"><label class="form-label">Contraseña</label><input type="password" name="clave" class="form-control" required></div>
      <button class="btn btn-primary w-100">Ingresar</button>
    </form>
  </div></div>
</div></body></html>
