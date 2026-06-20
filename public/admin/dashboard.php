<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

use App\Models\Parametro;
use App\Models\ReporteSemanal;
use App\Services\PipelineCalculator;
use App\Services\ReporteSemanalService;

$calculator = PipelineCalculator::fromParametros(Parametro::allAsAssoc());
$servicio = new ReporteSemanalService();
$ultimos = ReporteSemanal::ultimoDeTodos();

$totalGeneral = array_sum(array_column($ultimos, 'venta_general'));
$totalPipeline = array_sum(array_column($ultimos, 'total_pipeline_snapshot'));
$totalVentaEmpresas = array_sum(array_column($ultimos, 'venta_empresas'));
$totalVentaOtros = $servicio->ventaOtros($totalGeneral, $totalVentaEmpresas);
$pctEmpresasEmpresa = $servicio->participacion($totalVentaEmpresas, $totalGeneral) * 100;
$pctOtrosEmpresa = $servicio->participacion($totalVentaOtros, $totalGeneral) * 100;
$totalPronostico = array_sum(array_column($ultimos, 'pronostico_ponderado_snapshot'));
$totalMeta = array_sum(array_column($ultimos, 'meta_mes'));
$semaforoEmpresa = $calculator->semaforo($totalPronostico, $totalMeta);

require __DIR__ . '/../../includes/layout_header.php';
?>
<h1 class="page-title">Dashboard consolidado</h1>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-head"><?= icono('venta') ?><span class="stat-label">Venta general (última semana por ejecutivo)</span></div>
        <span class="stat-value"><?= pesos($totalGeneral) ?></span>
    </div>
    <div class="stat-card">
        <div class="stat-head"><?= icono('pipeline') ?><span class="stat-label">Pipeline total de la empresa</span></div>
        <span class="stat-value"><?= pesos($totalPipeline) ?></span>
    </div>
</div>

<?php if (empty($ultimos)): ?>
    <div class="empty-state">Todavía no hay reportes semanales de ningún ejecutivo.</div>
<?php else: ?>

<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(360px, 1fr)); gap:24px; margin-bottom:24px;">
    <div class="card" style="margin-bottom:0;">
        <h2 class="card-title"><?= icono('meta') ?> Pronóstico vs meta — toda la empresa</h2>
        <div style="display:flex; align-items:center; justify-content:center; gap:32px; flex-wrap:wrap;">
            <div style="flex-shrink:0;"><?= gaugeSvg($semaforoEmpresa, $totalPronostico, $totalMeta) ?></div>
            <div style="text-align:left; min-width:220px;">
                <span class="stat-label" style="display:block; margin-bottom:8px;">Pronóstico ponderado</span>
                <span class="stat-value" style="font-size:30px;"><?= pesos($totalPronostico) ?></span>
                <p style="margin:12px 0 0; font-size:16px; color:var(--color-text-secondary);">
                    de <strong style="color:var(--color-text);"><?= pesos($totalMeta) ?></strong>
                </p>
                <p style="margin:6px 0 0; font-size:16px; color:var(--color-text-secondary);">
                    Semáforo: <span class="<?= $semaforoEmpresa ?>"><?= strtoupper($semaforoEmpresa) ?></span>
                </p>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:0;">
        <h2 class="card-title"><?= icono('venta') ?> Venta semanal por categoría — toda la empresa</h2>
        <div style="display:flex; align-items:center; justify-content:center; gap:32px; flex-wrap:wrap;">
            <?= donutSvg($pctEmpresasEmpresa, $pctOtrosEmpresa) ?>
            <div style="text-align:left; min-width:200px;">
                <p style="display:flex; align-items:center; gap:8px; margin:0 0 10px; font-size:15px; color:var(--color-text-secondary);">
                    <span style="width:12px; height:12px; border-radius:3px; background:#1c2b4a; flex-shrink:0;"></span>
                    Empresas: <strong style="color:var(--color-text);"><?= pesos($totalVentaEmpresas) ?></strong> (<?= number_format($pctEmpresasEmpresa, 1) ?>%)
                </p>
                <p style="display:flex; align-items:center; gap:8px; margin:0; font-size:15px; color:var(--color-text-secondary);">
                    <span style="width:12px; height:12px; border-radius:3px; background:#90a4d4; flex-shrink:0;"></span>
                    Otros: <strong style="color:var(--color-text);"><?= pesos($totalVentaOtros) ?></strong> (<?= number_format($pctOtrosEmpresa, 1) ?>%)
                </p>
                <p style="margin:14px 0 0; font-size:13px; color:var(--color-text-secondary);">Total general: <strong style="color:var(--color-text);"><?= pesos($totalGeneral) ?></strong></p>
            </div>
        </div>
    </div>
</div>

<h2 class="section-title">Por ejecutivo</h2>
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:20px; margin-bottom:24px;">
    <?php foreach ($ultimos as $r): ?>
        <?php $sEj = $calculator->semaforo((float) $r['pronostico_ponderado_snapshot'], (float) $r['meta_mes']); ?>
        <div class="card" style="text-align:center; margin-bottom:0;">
            <h2 class="card-title" style="justify-content:center;"><?= icono('persona') ?> <?= htmlspecialchars($r['ejecutivo_nombre']) ?></h2>
            <?= gaugeSvg($sEj, (float) $r['pronostico_ponderado_snapshot'], (float) $r['meta_mes']) ?>
            <p style="margin:12px 0 0; font-size:14px; color:var(--color-text-secondary);">
                <?= pesos($r['pronostico_ponderado_snapshot']) ?> de <?= pesos($r['meta_mes']) ?>
            </p>
            <p style="margin:6px 0 0;"><span class="<?= $sEj ?>"><?= strtoupper($sEj) ?></span></p>
        </div>
    <?php endforeach; ?>
</div>

<h2 class="section-title">Detalle por reporte</h2>
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
            <td class="num"><?= pesos($r['meta_mes']) ?></td>
            <td class="num"><?= pesos($r['venta_general']) ?></td>
            <td class="num"><?= pesos($r['pronostico_ponderado_snapshot']) ?></td>
            <td><span class="<?= $s ?>"><?= strtoupper($s) ?></span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
