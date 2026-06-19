<?php
namespace App\Services;

use InvalidArgumentException;

class ReporteSemanalService
{
    public function ventaOtros(float $ventaGeneral, float $ventaEmpresas): float
    {
        if ($ventaEmpresas > $ventaGeneral) {
            throw new InvalidArgumentException('La venta de empresas no puede ser mayor que la venta general.');
        }
        return $ventaGeneral - $ventaEmpresas;
    }

    public function participacion(float $parte, float $ventaGeneral): float
    {
        if ($ventaGeneral <= 0) {
            return 0.0;
        }
        return $parte / $ventaGeneral;
    }
}
