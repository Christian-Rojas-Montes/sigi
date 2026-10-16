<?php
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): void { header('Location: ' . $url); exit; }
function p(string $k, string $d = ''): string { return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }
function q(string $k, string $d = ''): string { return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function soles($n): string { return 'S/ ' . number_format((float)$n, 2); }
function validar_doc(string $d): bool { return (bool)preg_match('/^(\d{8}|\d{11})$/', $d); }

function flash(string $tipo, string $msg): void { $_SESSION['flash'][] = [$tipo, $msg]; }

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void
{
    $t = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
    if ($t === '' || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        exit('Sesión expirada o formulario inválido. Vuelve atrás y recarga la página.');
    }
}

function usuario_actual() { return $_SESSION['user'] ?? null; }
function require_login(): void { if (!usuario_actual()) { redirect('login.php'); } }
function require_admin(): void
{
    require_login();
    if (usuario_actual()['rol'] !== 'admin') { http_response_code(403); exit('Acceso denegado: solo administrador.'); }
}

/** Registro de auditoría (RNF01). */
function audit(string $accion, string $detalle = ''): void
{
    $u = usuario_actual();
    $st = db()->prepare('INSERT INTO auditoria(id_usuario, accion, detalle, ip) VALUES (?,?,?,?)');
    $st->execute([$u ? $u['id'] : null, $accion, mb_substr($detalle, 0, 250), $_SERVER['REMOTE_ADDR'] ?? '']);
}

function telefono_wa(string $tel): string
{
    $d = preg_replace('/\D/', '', $tel);
    if (strlen($d) === 9) { $d = '51' . $d; }
    return $d;
}

function page_top(string $titulo, string $activo = ''): void
{
    $u = usuario_actual();
    $items = [
        'index.php' => ['Inicio', false], 'inmuebles.php' => ['Inmuebles', false], 'propietarios.php' => ['Propietarios', false],
        'clientes.php' => ['Clientes', false], 'visitas.php' => ['Visitas', false], 'contratos.php' => ['Contratos', false],
        'pagos.php' => ['Pagos', false], 'reportes.php' => ['Reportes', true], 'usuarios.php' => ['Usuarios', true],
    ];
    ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?> – SIGI</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">SIGI Inmobiliaria</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nv"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nv">
      <ul class="navbar-nav me-auto">
        <?php foreach ($items as $file => $it): if ($it[1] && (!$u || $u['rol'] !== 'admin')) { continue; } ?>
          <li class="nav-item"><a class="nav-link <?= $activo === $file ? 'active fw-bold' : '' ?>" href="<?= $file ?>"><?= $it[0] ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($u): ?>
      <form method="post" action="logout.php" class="d-flex align-items-center gap-2">
        <?= csrf_field() ?>
        <span class="text-white small"><?= e($u['nombres']) ?> (<?= e($u['rol']) ?>)</span>
        <button class="btn btn-outline-light btn-sm">Salir</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</nav>
<main class="container pb-5">
<h1 class="h3 mb-3"><?= e($titulo) ?></h1>
<?php
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $cls = $f[0] === 'ok' ? 'success' : 'danger';
        echo '<div class="alert alert-' . $cls . ' py-2">' . e($f[1]) . '</div>';
    }
    unset($_SESSION['flash']);
}

function page_bottom(): void
{
    ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
}

function badge_estado(string $e): string
{
    $m = ['disponible' => 'success', 'reservado' => 'warning', 'vendido' => 'secondary', 'alquilado' => 'info', 'baja' => 'dark',
          'programada' => 'primary', 'realizada' => 'success', 'cancelada' => 'secondary', 'pendiente' => 'warning', 'pagado' => 'success'];
    return '<span class="badge text-bg-' . ($m[$e] ?? 'light') . '">' . e($e) . '</span>';
}
