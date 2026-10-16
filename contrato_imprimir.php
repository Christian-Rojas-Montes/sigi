<?php
require __DIR__ . '/includes/bootstrap.php'; require_login();
$st = db()->prepare('SELECT c.*, i.codigo, i.tipo AS tipo_inm, i.direccion, i.distrito, i.area_m2, i.partida_registral, pr.nombres AS prop, pr.dni_ruc AS prop_doc,
  cl.nombres AS cli, cl.dni_ruc AS cli_doc, u.nombres AS agente FROM contrato c JOIN inmueble i ON i.id_inmueble=c.id_inmueble JOIN propietario pr ON pr.id_propietario=i.id_propietario
  JOIN cliente cl ON cl.id_cliente=c.id_cliente JOIN usuario u ON u.id_usuario=c.id_usuario WHERE c.id_contrato = ?');
$st->execute([(int)q('id')]);
$c = $st->fetch();
if (!$c) { exit('Contrato no encontrado.'); }
audit('contrato_ver', $c['numero']);
$meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$t = strtotime($c['fecha_firma']); $fecha = date('j', $t) . ' de ' . $meses[(int)date('n', $t) - 1] . ' de ' . date('Y', $t);
$alq = $c['tipo'] === 'alquiler';
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Contrato <?= e($c['numero']) ?></title>
<style>body{font-family:'Times New Roman',serif;max-width:780px;margin:30px auto;line-height:1.5;font-size:14px}h1{text-align:center;font-size:18px}.no-print{margin-bottom:20px}
@media print{.no-print{display:none}}.firmas{display:flex;justify-content:space-around;margin-top:80px;text-align:center}</style></head><body>
<div class="no-print"><button onclick="window.print()">Imprimir / Guardar como PDF</button> <a href="contratos.php">Volver</a></div>
<h1>CONTRATO DE <?= $alq ? 'ARRENDAMIENTO' : 'COMPRAVENTA' ?> N.° <?= e($c['numero']) ?></h1>
<p>Conste por el presente documento el contrato de <?= $alq ? 'arrendamiento' : 'compraventa' ?> que celebran, de una parte, <b><?= e($c['prop']) ?></b>, identificado(a) con DNI/RUC N.° <?= e($c['prop_doc']) ?>, en adelante <b>EL <?= $alq ? 'ARRENDADOR' : 'VENDEDOR' ?></b>; y de la otra parte, <b><?= e($c['cli']) ?></b>, identificado(a) con DNI/RUC N.° <?= e($c['cli_doc']) ?>, en adelante <b>EL <?= $alq ? 'ARRENDATARIO' : 'COMPRADOR' ?></b>, con la intermediación de la inmobiliaria, bajo los siguientes términos:</p>
<p><b>PRIMERA (Objeto).</b> El inmueble (<?= e($c['tipo_inm']) ?>, código <?= e($c['codigo']) ?>) está ubicado en <?= e($c['direccion']) ?>, distrito de <?= e($c['distrito']) ?>, con un área de <?= number_format($c['area_m2'], 2) ?> m²<?= $c['partida_registral'] ? ', inscrito en la partida registral N.° ' . e($c['partida_registral']) : '' ?>.</p>
<?php if ($alq): ?>
<p><b>SEGUNDA (Renta y plazo).</b> La renta mensual es de <?= soles($c['monto']) ?>, por un plazo de <?= (int)$c['plazo_meses'] ?> mes(es) contados desde el <?= e($fecha) ?>.</p>
<p><b>TERCERA (Garantía).</b> EL ARRENDATARIO entrega una garantía de <?= soles($c['garantia']) ?>, que responde por daños e incumplimientos y será devuelta al término del contrato, previa verificación del estado del bien.</p>
<?php else: ?>
<p><b>SEGUNDA (Precio).</b> El precio de venta pactado es de <?= soles($c['monto']) ?>, pagadero según el cronograma registrado en el sistema, con fecha de firma el <?= e($fecha) ?>.</p>
<p><b>TERCERA (Transferencia).</b> La transferencia de propiedad se formalizará mediante escritura pública e inscripción ante SUNARP.</p>
<?php endif; ?>
<p><b>CUARTA (Comisión).</b> Por la intermediación, la inmobiliaria percibe una comisión de <?= soles($c['comision']) ?>.</p>
<p><b>QUINTA (Datos personales).</b> Las partes autorizan el tratamiento de sus datos personales para los fines de este contrato, conforme a la Ley N.° 29733.</p>
<p>Arequipa, <?= e($fecha) ?>.</p>
<div class="firmas"><div>______________________<br>EL <?= $alq ? 'ARRENDADOR' : 'VENDEDOR' ?></div><div>______________________<br>EL <?= $alq ? 'ARRENDATARIO' : 'COMPRADOR' ?></div></div>
<p style="margin-top:50px;font-size:11px;color:#666">Documento generado por SIGI – Agente: <?= e($c['agente']) ?>. Modelo referencial: debe ser revisado por un abogado antes de su uso.</p>
</body></html>
