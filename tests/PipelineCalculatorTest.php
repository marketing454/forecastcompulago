<?php
namespace Tests;

use App\Services\PipelineCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class PipelineCalculatorTest extends TestCase
{
    private PipelineCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new PipelineCalculator();
    }

    public function test_dias_counts_days_between_creation_and_today(): void
    {
        $creada = new DateTimeImmutable('2026-06-01');
        $hoy = new DateTimeImmutable('2026-06-10');
        $this->assertSame(9, $this->calculator->dias($creada, $hoy));
    }

    public function test_probabilidad_is_alta_within_15_days(): void
    {
        $this->assertSame('ALTA', $this->calculator->probabilidad(15));
    }

    public function test_probabilidad_is_media_between_16_and_30_days(): void
    {
        $this->assertSame('MEDIA', $this->calculator->probabilidad(16));
        $this->assertSame('MEDIA', $this->calculator->probabilidad(30));
    }

    public function test_probabilidad_is_baja_after_30_days(): void
    {
        $this->assertSame('BAJA', $this->calculator->probabilidad(31));
    }

    public function test_total_pipeline_sums_all_montos(): void
    {
        $this->assertSame(450.0, $this->calculator->totalPipeline([100, 150, 200]));
    }

    public function test_pronostico_ponderado_applies_flat_30_percent(): void
    {
        $this->assertSame(97350000.0, $this->calculator->pronosticoPonderado(324500000));
    }

    public function test_semaforo_rojo_at_or_below_30_percent_of_meta(): void
    {
        $this->assertSame('rojo', $this->calculator->semaforo(20_000_000, 150_000_000));
    }

    public function test_semaforo_ambar_between_30_and_80_percent_of_meta(): void
    {
        $this->assertSame('ambar', $this->calculator->semaforo(97_350_000, 150_000_000));
    }

    public function test_semaforo_verde_above_80_percent_of_meta(): void
    {
        $this->assertSame('verde', $this->calculator->semaforo(130_000_000, 150_000_000));
    }

    public function test_semaforo_rojo_when_meta_is_zero(): void
    {
        $this->assertSame('rojo', $this->calculator->semaforo(10_000, 0));
    }

    public function test_semaforo_is_rojo_at_exactly_30_percent_of_meta(): void
    {
        $this->assertSame('rojo', $this->calculator->semaforo(30, 100));
    }

    public function test_semaforo_is_ambar_at_exactly_80_percent_of_meta(): void
    {
        $this->assertSame('ambar', $this->calculator->semaforo(80, 100));
    }

    public function test_dias_is_unaffected_by_argument_order(): void
    {
        $a = new DateTimeImmutable('2026-06-01');
        $b = new DateTimeImmutable('2026-06-10');
        $this->assertSame(9, $this->calculator->dias($a, $b));
        $this->assertSame(9, $this->calculator->dias($b, $a));
    }

    public function test_from_parametros_builds_calculator_from_string_values(): void
    {
        $calculator = PipelineCalculator::fromParametros([
            'umbral_dias_alta' => '15',
            'umbral_dias_baja' => '30',
            'pct_conversion_pipeline' => '0.30',
            'umbral_semaforo_bajo' => '0.30',
            'umbral_semaforo_alto' => '0.80',
        ]);
        $this->assertSame('ALTA', $calculator->probabilidad(15));
        $this->assertSame('BAJA', $calculator->probabilidad(31));
        $this->assertSame(97350000.0, $calculator->pronosticoPonderado(324500000));
    }

    public function test_from_parametros_falls_back_to_defaults_when_keys_missing(): void
    {
        $calculator = PipelineCalculator::fromParametros([]);
        $this->assertSame('ALTA', $calculator->probabilidad(15));
        $this->assertSame('rojo', $calculator->semaforo(20_000_000, 150_000_000));
    }
}
