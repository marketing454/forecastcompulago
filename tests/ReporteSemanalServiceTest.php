<?php
namespace Tests;

use App\Services\ReporteSemanalService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ReporteSemanalServiceTest extends TestCase
{
    private ReporteSemanalService $service;

    protected function setUp(): void
    {
        $this->service = new ReporteSemanalService();
    }

    public function test_venta_otros_is_the_difference_between_general_and_empresas(): void
    {
        $this->assertSame(350000.0, $this->service->ventaOtros(24660000, 24310000));
    }

    public function test_venta_otros_rejects_empresas_greater_than_general(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->ventaOtros(1000, 2000);
    }

    public function test_participacion_divides_parte_by_general(): void
    {
        $this->assertEqualsWithDelta(0.9858, $this->service->participacion(24310000, 24660000), 0.0001);
    }

    public function test_participacion_is_zero_when_general_is_zero(): void
    {
        $this->assertSame(0.0, $this->service->participacion(100, 0));
    }
}
