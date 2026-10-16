<?php
require_once __DIR__ . '/../config.php';
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../app/Inmueble.php';
require_once __DIR__ . '/../app/ContratoService.php';
require_once __DIR__ . '/../app/Repositories.php';
