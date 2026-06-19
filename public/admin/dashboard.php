<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

use App\Models\ReporteSemanal;
use App\Services\PipelineCalculator;

$calculator = new PipelineCalculator();
$ultimos = ReporteSemanal::ultimoDeTodos();

$totalGeneral = array_sum(array_column($ultimos, 'venta_general'));
$totalPipeline = array_sum(array_column($ultimos, 'total_pipeline_snapshot'));

require __DIR__ . '/../../includes/layout_header.php';
?>
<h1>Dashboard consolidado</h1>
<p>Venta general (última semana reportada por cada ejecutivo): <?= number_format((float) $totalGeneral, 0) ?></p>
<p>Pipeline total de la empresa: <?= number_format((float) $totalPipeline, 0) ?></p>

<table>
    <tr><th>Ejecutivo</th><th>Semana</th><th>Meta mes</th><th>Venta general</th><th>Pronóstico</th><th>Semáforo</th></tr>
    <?php foreach ($ultimos as $r): ?>
        <?php $s = $calculator->semaforo((float) $r['pronostico_ponderado_snapshot'], (float) $r['meta_mes']); ?>
        <tr>
            <td><?= htmlspecialchars($r['ejecutivo_nombre']) ?></td>
            <td><?= $r['fecha_reporte'] ?></td>
            <td><?= number_format((float) $r['meta_mes'], 0) ?></td>
            <td><?= number_format((float) $r['venta_general'], 0) ?></td>
            <td><?= number_format((float) $r['pronostico_ponderado_snapshot'], 0) ?></td>
            <td class="<?= $s ?>"><?= strtoupper($s) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
