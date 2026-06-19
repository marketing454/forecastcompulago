<?php
namespace App\Services;

use DateTimeImmutable;

class PipelineCalculator
{
    public function __construct(
        private int $umbralDiasAlta = 15,
        private int $umbralDiasBaja = 30,
        private float $pctConversion = 0.30,
        private float $umbralSemaforoBajo = 0.30,
        private float $umbralSemaforoAlto = 0.80,
    ) {
    }

    public function dias(DateTimeImmutable $fechaCreacion, DateTimeImmutable $hoy): int
    {
        return $hoy->diff($fechaCreacion)->days;
    }

    public function probabilidad(int $dias): string
    {
        if ($dias > $this->umbralDiasBaja) {
            return 'BAJA';
        }
        if ($dias > $this->umbralDiasAlta) {
            return 'MEDIA';
        }
        return 'ALTA';
    }

    public function totalPipeline(array $montos): float
    {
        return array_sum($montos);
    }

    public function pronosticoPonderado(float $totalPipeline): float
    {
        return $totalPipeline * $this->pctConversion;
    }

    public function semaforo(float $pronosticoPonderado, float $metaMes): string
    {
        if ($metaMes <= 0) {
            return 'rojo';
        }
        $fraccion = $pronosticoPonderado / $metaMes;
        if ($fraccion > $this->umbralSemaforoAlto) {
            return 'verde';
        }
        if ($fraccion > $this->umbralSemaforoBajo) {
            return 'ambar';
        }
        return 'rojo';
    }
}
