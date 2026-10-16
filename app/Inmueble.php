<?php
/** Entidad de dominio Inmueble (reglas RN1/RN2 del CU02). */
class Inmueble
{
    const TIPOS = ['casa', 'departamento', 'tienda', 'oficina', 'terreno', 'chacra'];
    const OPERACIONES = ['venta', 'alquiler'];
    const ESTADOS = ['disponible', 'reservado', 'vendido', 'alquilado', 'baja'];

    public $codigo;
    public $tipo;
    public $operacion;
    public $areaM2;
    public $precio;
    public $estado;

    /**
     * @throws InvalidArgumentException si área o precio no son mayores que cero, o tipo/operación inválidos.
     */
    public function __construct(string $codigo, string $tipo, string $operacion, float $areaM2, float $precio, string $estado = 'disponible')
    {
        if ($areaM2 <= 0 || $precio <= 0) {
            throw new InvalidArgumentException('El área y el precio deben ser mayores que cero');
        }
        if (!in_array($tipo, self::TIPOS, true) || !in_array($operacion, self::OPERACIONES, true)) {
            throw new InvalidArgumentException('Tipo u operación no válidos');
        }
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new InvalidArgumentException('Estado no válido');
        }
        $this->codigo = $codigo;
        $this->tipo = $tipo;
        $this->operacion = $operacion;
        $this->areaM2 = $areaM2;
        $this->precio = $precio;
        $this->estado = $estado;
    }

    /** Crea el objeto desde una fila de la tabla inmueble. */
    public static function fromRow(array $r): Inmueble
    {
        return new Inmueble($r['codigo'], $r['tipo'], $r['operacion'], (float)$r['area_m2'], (float)$r['precio'], $r['estado']);
    }

    /** Precio por metro cuadrado. */
    public function precioPorM2(): float
    {
        return round($this->precio / $this->areaM2, 2);
    }

    /**
     * Cambia el estado respetando las reglas del negocio.
     * @throws DomainException si el inmueble ya fue vendido, alquilado o dado de baja.
     */
    public function cambiarEstado(string $nuevo): void
    {
        if (in_array($this->estado, ['vendido', 'alquilado', 'baja'], true)) {
            throw new DomainException('El inmueble ya fue cerrado; no puede cambiar de estado');
        }
        if (!in_array($nuevo, self::ESTADOS, true)) {
            throw new InvalidArgumentException('Estado no válido');
        }
        $this->estado = $nuevo;
    }
}
