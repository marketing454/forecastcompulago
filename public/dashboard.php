<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('ejecutivo');

use App\Models\Parametro;
use App\Models\ReporteSemanal;
use App\Services\PipelineCalculator;
use App\Services\ReporteSemanalService;

$ejecutivoId = currentUserId();
$historial = ReporteSemanal::historial($ejecutivoId);
$actual = $historial[0] ?? null;
$calculator = PipelineCalculator::fromParametros(Parametro::allAsAssoc());
$servicio = new ReporteSemanalService();

require __DIR__ . '/../includes/layout_header.php';
?>
<h1 class="page-title">Mi Dashboard</h1>

<?php if ($actual === null): ?>
    <div class="empty-state">Aún no has guardado ningún reporte semanal.</div>
<?php else: ?>
    <?php
    $semaforo = $calculator->semaforo((float) $actual['pronostico_ponderado_snapshot'], (float) $actual['meta_mes']);
    $ventaOtros = $servicio->ventaOtros((float) $actual['venta_general'], (float) $actual['venta_empresas']);
    $pctEmpresas = $servicio->participacion((float) $actual['venta_empresas'], (float) $actual['venta_general']) * 100;
    $pctOtros = $servicio->participacion($ventaOtros, (float) $actual['venta_general']) * 100;
    ?>
    <div class="stat-grid">
        <div class="stat-card">
            <span class="stat-label">Semáforo actual</span>
            <span class="<?= $semaforo ?>"><?= strtoupper($semaforo) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Total pipeline</span>
            <span class="stat-value"><?= number_format((float) $actual['total_pipeline_snapshot'], 0) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Pronóstico ponderado</span>
            <span class="stat-value"><?= number_format((float) $actual['pronostico_ponderado_snapshot'], 0) ?></span>
        </div>
    </div>

    <h2 class="section-title">Venta semanal por categoría</h2>
    <div class="card">
        <div style="display:flex; height:10px; border-radius:999px; overflow:hidden; background:#eef1f6;">
            <div style="background:var(--color-primary); width:<?= $pctEmpresas ?>%;"></div>
            <div style="background:#90a4d4; width:<?= $pctOtros ?>%;"></div>
        </div>
        <p style="margin:14px 0 0; font-size:14px; color:var(--color-text-secondary);">
            Empresas: <strong style="color:var(--color-text);"><?= number_format((float) $actual['venta_empresas'], 0) ?></strong> (<?= number_format($pctEmpresas, 1) ?>%) —
            Otros: <strong style="color:var(--color-text);"><?= number_format($ventaOtros, 0) ?></strong> (<?= number_format($pctOtros, 1) ?>%)
        </p>
    </div>
<?php endif; ?>

<h2 class="section-title">Histórico de reportes</h2>
<?php if (empty($historial)): ?>
    <div class="empty-state">No hay reportes guardados todavía.</div>
<?php else: ?>
<div class="table-wrap">
<table>
    <thead>
        <tr><th>Semana</th><th>Meta mes</th><th>Venta general</th><th>Pronóstico</th><th>Semáforo</th></tr>
    </thead>
    <tbody>
    <?php foreach ($historial as $reporte): ?>
        <?php $s = $calculator->semaforo((float) $reporte['pronostico_ponderado_snapshot'], (float) $reporte['meta_mes']); ?>
        <tr>
            <td><?= $reporte['fecha_reporte'] ?></td>
            <td><?= number_format((float) $reporte['meta_mes'], 0) ?></td>
            <td><?= number_format((float) $reporte['venta_general'], 0) ?></td>
            <td><?= number_format((float) $reporte['pronostico_ponderado_snapshot'], 0) ?></td>
            <td><span class="<?= $s ?>"><?= strtoupper($s) ?></span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
