<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

use App\Models\Parametro;
use App\Models\ReporteSemanal;
use App\Services\PipelineCalculator;

$calculator = PipelineCalculator::fromParametros(Parametro::allAsAssoc());
$ultimos = ReporteSemanal::ultimoDeTodos();

$totalGeneral = array_sum(array_column($ultimos, 'venta_general'));
$totalPipeline = array_sum(array_column($ultimos, 'total_pipeline_snapshot'));

require __DIR__ . '/../../includes/layout_header.php';
?>
<h1 class="page-title">Dashboard consolidado</h1>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-head"><?= icono('venta') ?><span class="stat-label">Venta general (última semana por ejecutivo)</span></div>
        <span class="stat-value"><?= number_format((float) $totalGeneral, 0) ?></span>
    </div>
    <div class="stat-card">
        <div class="stat-head"><?= icono('pipeline') ?><span class="stat-label">Pipeline total de la empresa</span></div>
        <span class="stat-value"><?= number_format((float) $totalPipeline, 0) ?></span>
    </div>
</div>

<?php if (empty($ultimos)): ?>
    <div class="empty-state">Todavía no hay reportes semanales de ningún ejecutivo.</div>
<?php else: ?>
<div class="table-wrap">
<table>
    <thead>
        <tr><th>Ejecutivo</th><th>Semana</th><th>Meta mes</th><th>Venta general</th><th>Pronóstico</th><th>Semáforo</th></tr>
    </thead>
    <tbody>
    <?php foreach ($ultimos as $r): ?>
        <?php $s = $calculator->semaforo((float) $r['pronostico_ponderado_snapshot'], (float) $r['meta_mes']); ?>
        <tr>
            <td><?= htmlspecialchars($r['ejecutivo_nombre']) ?></td>
            <td><?= $r['fecha_reporte'] ?></td>
            <td class="num"><?= number_format((float) $r['meta_mes'], 0) ?></td>
            <td class="num"><?= number_format((float) $r['venta_general'], 0) ?></td>
            <td class="num"><?= number_format((float) $r['pronostico_ponderado_snapshot'], 0) ?></td>
            <td><span class="<?= $s ?>"><?= strtoupper($s) ?></span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
