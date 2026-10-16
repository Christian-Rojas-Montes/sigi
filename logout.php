<?php
require __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (usuario_actual()) { audit('logout', usuario_actual()['usuario']); }
    $_SESSION = [];
    session_destroy();
}
redirect('login.php');
