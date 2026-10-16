<?php
/** Falta el consentimiento de tratamiento de datos personales (Ley N.° 29733). */
class ConsentimientoRequeridoException extends Exception {}

/** Lógica de negocio pura (sin base de datos) para contratos – CU06. */
class ContratoService
{
    /**
     * Calcula la comisión de la inmobiliaria según la operación (patrón Strategy simplificado).
     * @param float $monto Precio de venta, o renta mensual si es alquiler.
     */
    public static function calcularComision(Inmueble $inmueble, float $monto): float
    {
        if ($inmueble->operacion === 'venta') {
            return round($monto * PORC_COMISION_VENTA, 2);
        }
        return round($monto * MESES_COMISION_ALQUILER, 2);
    }

    /**
     * Valida y "registra" un contrato en memoria: calcula la comisión y cambia el estado del inmueble.
     * La persistencia la hace ContratoRepo::crear().
     *
     * @throws ConsentimientoRequeridoException si el cliente no aceptó el tratamiento de datos.
     * @throws DomainException si el inmueble no está disponible/reservado.
     * @throws InvalidArgumentException si el monto o la garantía son inválidos.
     */
    public static function registrar(Inmueble $inmueble, bool $clienteConsintio, float $monto, float $garantia = 0.0): array
    {
        if (!$clienteConsintio) {
            throw new ConsentimientoRequeridoException('Falta el consentimiento de tratamiento de datos del cliente');
        }
        if (!in_array($inmueble->estado, ['disponible', 'reservado'], true)) {
            throw new DomainException('El inmueble no está disponible');
        }
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor que cero');
        }
        if ($garantia < 0) {
            throw new InvalidArgumentException('La garantía no puede ser negativa');
        }
        $comision = self::calcularComision($inmueble, $monto);
        $nuevo = $inmueble->operacion === 'venta' ? 'vendido' : 'alquilado';
        $inmueble->cambiarEstado($nuevo);
        return [
            'inmueble' => $inmueble->codigo,
            'tipo' => $nuevo === 'vendido' ? 'compraventa' : 'alquiler',
            'monto' => $monto,
            'garantia' => $garantia,
            'comision' => $comision,
            'estado' => $nuevo,
        ];
    }
}
