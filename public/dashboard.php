<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('ejecutivo');

use App\Models\Parametro;
use App\Models\ReporteSemanal;
use App\Services\PipelineCalculator;
use App\Services\ReporteSemanalService;

$ejecutivoId = currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_reporte') {
    ReporteSemanal::eliminar((int) $_POST['id'], $ejecutivoId);
    header('Location: /dashboard.php');
    exit;
}

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

    $radioGauge = 68;
    $circunferenciaGauge = 2 * M_PI * $radioGauge;
    $metaMesFloat = (float) $actual['meta_mes'];
    $fraccionMeta = $metaMesFloat > 0 ? ((float) $actual['pronostico_ponderado_snapshot'] / $metaMesFloat) : 0;
    $fraccionVisualGauge = min(max($fraccionMeta, 0), 1);
    $longitudVisibleGauge = $circunferenciaGauge * $fraccionVisualGauge;
    $colorGauge = ['rojo' => '#FF0000', 'ambar' => '#FFC000', 'verde' => '#00B050'][$semaforo] ?? '#5b6472';
    $porcentajeGaugeTexto = number_format($fraccionMeta * 100, 0);
    ?>
    <div class="card" style="text-align:center;">
        <h2 class="card-title" style="justify-content:center;"><?= icono('meta') ?> Pronóstico vs meta del mes</h2>
        <svg width="170" height="170" viewBox="0 0 170 170" role="img" aria-label="Pronóstico ponderado: <?= $porcentajeGaugeTexto ?>% de la meta del mes, semáforo <?= strtoupper($semaforo) ?>">
            <circle cx="85" cy="85" r="<?= $radioGauge ?>" fill="none" stroke="#eef1f6" stroke-width="16"/>
            <circle cx="85" cy="85" r="<?= $radioGauge ?>" fill="none" stroke="<?= $colorGauge ?>" stroke-width="16"
                    stroke-linecap="round"
                    stroke-dasharray="<?= $longitudVisibleGauge ?> <?= $circunferenciaGauge ?>"
                    transform="rotate(-90 85 85)"/>
            <text x="85" y="82" text-anchor="middle" font-family="Inter, sans-serif" font-size="28" font-weight="700" fill="#1c2333"><?= $porcentajeGaugeTexto ?>%</text>
            <text x="85" y="103" text-anchor="middle" font-family="Inter, sans-serif" font-size="12" fill="#5b6472">de la meta</text>
        </svg>
        <p style="margin:10px 0 0; font-size:13px; color:var(--color-text-secondary);">
            <?= pesos($actual['pronostico_ponderado_snapshot']) ?> de <?= pesos($actual['meta_mes']) ?> — semáforo
            <span class="<?= $semaforo ?>"><?= strtoupper($semaforo) ?></span>
        </p>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-head"><?= icono('pipeline') ?><span class="stat-label">Total pipeline</span></div>
            <span class="stat-value"><?= pesos($actual['total_pipeline_snapshot']) ?></span>
        </div>
        <div class="stat-card">
            <div class="stat-head"><?= icono('venta') ?><span class="stat-label">Pronóstico ponderado</span></div>
            <span class="stat-value"><?= pesos($actual['pronostico_ponderado_snapshot']) ?></span>
        </div>
    </div>

    <h2 class="section-title">Venta semanal por categoría</h2>
    <div class="card">
        <div style="display:flex; height:10px; border-radius:999px; overflow:hidden; background:#eef1f6;">
            <div style="background:var(--color-primary); width:<?= $pctEmpresas ?>%;"></div>
            <div style="background:#90a4d4; width:<?= $pctOtros ?>%;"></div>
        </div>
        <p style="margin:14px 0 0; font-size:14px; color:var(--color-text-secondary);">
            Empresas: <strong style="color:var(--color-text);"><?= pesos($actual['venta_empresas']) ?></strong> (<?= number_format($pctEmpresas, 1) ?>%) —
            Otros: <strong style="color:var(--color-text);"><?= pesos($ventaOtros) ?></strong> (<?= number_format($pctOtros, 1) ?>%)
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
        <tr><th>Semana</th><th>Meta mes</th><th>Venta general</th><th>Pronóstico</th><th>Semáforo</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($historial as $reporte): ?>
        <?php $s = $calculator->semaforo((float) $reporte['pronostico_ponderado_snapshot'], (float) $reporte['meta_mes']); ?>
        <tr>
            <td><?= $reporte['fecha_reporte'] ?></td>
            <td class="num"><?= pesos($reporte['meta_mes']) ?></td>
            <td class="num"><?= pesos($reporte['venta_general']) ?></td>
            <td class="num"><?= pesos($reporte['pronostico_ponderado_snapshot']) ?></td>
            <td><span class="<?= $s ?>"><?= strtoupper($s) ?></span></td>
            <td class="table-actions-cell">
                <a href="/reporte.php?semana=<?= $reporte['fecha_reporte'] ?>" class="btn btn-secondary btn-sm">Editar</a>
                <form method="post" onsubmit="return confirm('¿Borrar el reporte de la semana del <?= $reporte['fecha_reporte'] ?>? Esta acción no se puede deshacer.');">
                    <input type="hidden" name="accion" value="eliminar_reporte">
                    <input type="hidden" name="id" value="<?= $reporte['id'] ?>">
                    <button type="submit" class="btn-danger btn-sm">Borrar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
