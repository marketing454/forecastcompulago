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

    public static function findSemanaActual(int $ejecutivoId): ?array
    {
        $fechaSemana = self::fechaInicioSemana(new DateTimeImmutable());
        $stmt = db()->prepare(
            'SELECT * FROM reportes_semanales WHERE ejecutivo_id = ? AND fecha_reporte = ? LIMIT 1'
        );
        $stmt->execute([$ejecutivoId, $fechaSemana]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function historial(int $ejecutivoId): array
    {
        $stmt = db()->prepare(
            'SELECT * FROM reportes_semanales WHERE ejecutivo_id = ? ORDER BY fecha_reporte DESC'
        );
        $stmt->execute([$ejecutivoId]);
        return $stmt->fetchAll();
    }

    public static function guardar(int $ejecutivoId, float $metaMes, float $ventaEmpresas, float $ventaGeneral, string $comentarios): void
    {
        $fechaSemana = self::fechaInicioSemana(new DateTimeImmutable());
        $montos = array_column(Oportunidad::activasByEjecutivo($ejecutivoId), 'monto');
        $calculator = new PipelineCalculator();
        $totalPipeline = $calculator->totalPipeline($montos);
        $pronostico = $calculator->pronosticoPonderado($totalPipeline);

        $existente = self::findSemanaActual($ejecutivoId);
        if ($existente !== null) {
            $stmt = db()->prepare(
                'UPDATE reportes_semanales
                 SET meta_mes = ?, venta_empresas = ?, venta_general = ?, comentarios = ?,
                     total_pipeline_snapshot = ?, pronostico_ponderado_snapshot = ?
                 WHERE id = ?'
            );
            $stmt->execute([$metaMes, $ventaEmpresas, $ventaGeneral, $comentarios, $totalPipeline, $pronostico, $existente['id']]);
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
