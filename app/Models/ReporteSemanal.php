<?php
namespace App\Models;

use App\Services\PipelineCalculator;
use DateTimeImmutable;

class ReporteSemanal
{
    public static function fechaInicioSemana(DateTimeImmutable $fecha): string
    {
        $diasDesdeLunes = (int) $fecha->format('N') - 1;
        return $fecha->modify("-{$diasDesdeLunes} days")->format('Y-m-d');
    }

    public static function findPorFecha(int $ejecutivoId, string $fechaReporte): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM reportes_semanales WHERE ejecutivo_id = ? AND fecha_reporte = ? LIMIT 1'
        );
        $stmt->execute([$ejecutivoId, $fechaReporte]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findSemanaActual(int $ejecutivoId): ?array
    {
        return self::findPorFecha($ejecutivoId, self::fechaInicioSemana(new DateTimeImmutable()));
    }

    public static function historial(int $ejecutivoId): array
    {
        $stmt = db()->prepare(
            'SELECT * FROM reportes_semanales WHERE ejecutivo_id = ? ORDER BY fecha_reporte DESC'
        );
        $stmt->execute([$ejecutivoId]);
        return $stmt->fetchAll();
    }

    public static function guardar(int $ejecutivoId, float $metaMes, float $ventaEmpresas, float $ventaGeneral, string $comentarios, ?string $fechaSemana = null): void
    {
        $fechaSemana = $fechaSemana ?? self::fechaInicioSemana(new DateTimeImmutable());
        $esSemanaActual = $fechaSemana === self::fechaInicioSemana(new DateTimeImmutable());
        $existente = self::findPorFecha($ejecutivoId, $fechaSemana);

        if ($esSemanaActual) {
            $montos = array_column(Oportunidad::activasByEjecutivo($ejecutivoId), 'monto');
            $calculator = PipelineCalculator::fromParametros(Parametro::allAsAssoc());
            $totalPipeline = $calculator->totalPipeline($montos);
            $pronostico = $calculator->pronosticoPonderado($totalPipeline);
        } else {
            // Semana ya pasada: se conserva el snapshot original del pipeline,
            // no se recalcula con el pipeline de hoy (evita reescribir el histórico).
            $totalPipeline = (float) ($existente['total_pipeline_snapshot'] ?? 0);
            $pronostico = (float) ($existente['pronostico_ponderado_snapshot'] ?? 0);
        }

        if ($existente !== null) {
            $stmt = db()->prepare(
                'UPDATE reportes_semanales
                 SET meta_mes = ?, venta_empresas = ?, venta_general = ?, comentarios = ?,
                     total_pipeline_snapshot = ?, pronostico_ponderado_snapshot = ?
                 WHERE id = ? AND ejecutivo_id = ?'
            );
            $stmt->execute([$metaMes, $ventaEmpresas, $ventaGeneral, $comentarios, $totalPipeline, $pronostico, $existente['id'], $ejecutivoId]);
            return;
        }

        $stmt = db()->prepare(
            'INSERT INTO reportes_semanales
                (ejecutivo_id, fecha_reporte, meta_mes, venta_empresas, venta_general, comentarios,
                 total_pipeline_snapshot, pronostico_ponderado_snapshot)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$ejecutivoId, $fechaSemana, $metaMes, $ventaEmpresas, $ventaGeneral, $comentarios, $totalPipeline, $pronostico]);
    }

    public static function eliminar(int $id, int $ejecutivoId): void
    {
        $stmt = db()->prepare('DELETE FROM reportes_semanales WHERE id = ? AND ejecutivo_id = ?');
        $stmt->execute([$id, $ejecutivoId]);
    }

    public static function ultimoDeTodos(): array
    {
        $stmt = db()->query(
            "SELECT r.*, u.nombre AS ejecutivo_nombre FROM reportes_semanales r
             INNER JOIN (
                 SELECT ejecutivo_id, MAX(fecha_reporte) AS max_fecha
                 FROM reportes_semanales
                 GROUP BY ejecutivo_id
             ) ultimo ON ultimo.ejecutivo_id = r.ejecutivo_id AND ultimo.max_fecha = r.fecha_reporte
             INNER JOIN usuarios u ON u.id = r.ejecutivo_id"
        );
        return $stmt->fetchAll();
    }
}
