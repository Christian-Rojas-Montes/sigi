<?php
/** Repositorios: único punto de acceso a la base de datos (patrón Repository). */

class InmuebleRepo
{
    public static function find(int $id)
    {
        $st = db()->prepare('SELECT i.*, p.nombres AS propietario FROM inmueble i JOIN propietario p ON p.id_propietario = i.id_propietario WHERE i.id_inmueble = ?');
        $st->execute([$id]);
        return $st->fetch();
    }

    /** CU03: búsqueda con filtros opcionales, ordenada por precio ascendente. */
    public static function buscar(array $f): array
    {
        $sql = 'SELECT i.*, p.nombres AS propietario FROM inmueble i JOIN propietario p ON p.id_propietario = i.id_propietario WHERE 1=1';
        $par = [];
        if (!empty($f['estado'])) { $sql .= ' AND i.estado = ?'; $par[] = $f['estado']; } else { $sql .= " AND i.estado <> 'baja'"; }
        if (!empty($f['tipo'])) { $sql .= ' AND i.tipo = ?'; $par[] = $f['tipo']; }
        if (!empty($f['operacion'])) { $sql .= ' AND i.operacion = ?'; $par[] = $f['operacion']; }
        if (!empty($f['distrito'])) { $sql .= ' AND i.distrito LIKE ?'; $par[] = '%' . $f['distrito'] . '%'; }
        if (isset($f['precio_max']) && is_numeric($f['precio_max'])) { $sql .= ' AND i.precio <= ?'; $par[] = $f['precio_max']; }
        if (isset($f['area_min']) && is_numeric($f['area_min'])) { $sql .= ' AND i.area_m2 >= ?'; $par[] = $f['area_min']; }
        $sql .= ' ORDER BY i.precio ASC';
        $st = db()->prepare($sql);
        $st->execute($par);
        return $st->fetchAll();
    }

    public static function siguienteCodigo(): string
    {
        $n = (int)db()->query('SELECT COALESCE(MAX(id_inmueble), 0) + 1 FROM inmueble')->fetchColumn();
        return sprintf('INM-%04d', $n);
    }

    /** Devuelve el código del inmueble duplicado (misma dirección, tipo y operación) o null. */
    public static function duplicado(string $direccion, string $tipo, string $operacion, int $excluirId = 0)
    {
        $st = db()->prepare("SELECT codigo FROM inmueble WHERE direccion = ? AND tipo = ? AND operacion = ? AND estado <> 'baja' AND id_inmueble <> ? LIMIT 1");
        $st->execute([$direccion, $tipo, $operacion, $excluirId]);
        $c = $st->fetchColumn();
        return $c === false ? null : $c;
    }

    public static function contarPorEstado(): array
    {
        return db()->query("SELECT estado, COUNT(*) AS total FROM inmueble GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function cambiarEstado(int $id, string $estado): void
    {
        db()->prepare('UPDATE inmueble SET estado = ? WHERE id_inmueble = ?')->execute([$estado, $id]);
    }
}

class VisitaRepo
{
    /** ¿Hay una visita programada a esa hora para el inmueble o para el agente? */
    public static function ocupado(int $idInmueble, int $idUsuario, string $fechaHora): bool
    {
        $st = db()->prepare("SELECT COUNT(*) FROM visita WHERE estado = 'programada' AND fecha_hora = ? AND (id_inmueble = ? OR id_usuario = ?)");
        $st->execute([$fechaHora, $idInmueble, $idUsuario]);
        return (int)$st->fetchColumn() > 0;
    }

    /** Propone el siguiente horario libre (bloques de 30 minutos). */
    public static function siguienteLibre(int $idInmueble, int $idUsuario, string $fechaHora): string
    {
        $t = strtotime($fechaHora);
        for ($i = 0; $i < 48; $i++) {
            $t += 1800;
            $f = date('Y-m-d H:i:s', $t);
            if (!self::ocupado($idInmueble, $idUsuario, $f)) { return $f; }
        }
        return date('Y-m-d H:i:s', $t);
    }
}

class ContratoRepo
{
    /**
     * Guarda contrato + cambio de estado del inmueble + plan de pagos en UNA transacción (escenario CU06).
     * @return int id del contrato creado
     */
    public static function crear(int $idInmueble, int $idCliente, int $idUsuario, string $tipo, string $fecha,
                                 float $monto, float $garantia, $plazo, float $comision, string $nuevoEstado): int
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT estado FROM inmueble WHERE id_inmueble = ? FOR UPDATE');
            $st->execute([$idInmueble]);
            $estado = $st->fetchColumn();
            if (!in_array($estado, ['disponible', 'reservado'], true)) {
                throw new DomainException('El inmueble ya no está disponible.');
            }
            $ins = $pdo->prepare('INSERT INTO contrato (numero, id_inmueble, id_cliente, id_usuario, tipo, fecha_firma, monto, garantia, plazo_meses, comision) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $ins->execute(['TMP' . uniqid(), $idInmueble, $idCliente, $idUsuario, $tipo, $fecha, $monto, $garantia, $plazo, $comision]);
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE contrato SET numero = ? WHERE id_contrato = ?')->execute([sprintf('CT-%s-%04d', date('Y'), $id), $id]);
            $pdo->prepare('UPDATE inmueble SET estado = ? WHERE id_inmueble = ?')->execute([$nuevoEstado, $idInmueble]);

            // Plan de pagos
            $pg = $pdo->prepare('INSERT INTO pago (id_contrato, concepto, monto, fecha_vencimiento) VALUES (?,?,?,?)');
            if ($tipo === 'alquiler') {
                if ($garantia > 0) { $pg->execute([$id, 'garantia', $garantia, $fecha]); }
                for ($i = 0; $i < (int)$plazo; $i++) {
                    $d = new DateTime($fecha);
                    $d->modify('+' . $i . ' month');
                    $pg->execute([$id, 'renta', $monto, $d->format('Y-m-d')]);
                }
            } else {
                $pg->execute([$id, 'cuota', $monto, $fecha]);
            }
            if ($comision > 0) { $pg->execute([$id, 'comision', $comision, $fecha]); }
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
