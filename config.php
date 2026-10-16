<?php
// ===== Configuración de SIGI (XAMPP por defecto: usuario root sin contraseña) =====
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');            // cámbialo si tu MySQL de XAMPP usa otro puerto (p. ej. 3307)
define('DB_NAME', 'sigi');
define('DB_USER', 'root');
define('DB_PASS', '');

// Reglas de negocio referenciales (la inmobiliaria debe ajustarlas)
define('PORC_COMISION_VENTA', 0.03);       // 3 % del monto de venta
define('MESES_COMISION_ALQUILER', 0.5);    // media renta mensual
// Monto (S/) desde el cual se marca la operación para revisión UIF. Verificar umbral vigente con el asesor legal.
define('UMBRAL_UIF_SOLES', 35000);

date_default_timezone_set('America/Lima');
