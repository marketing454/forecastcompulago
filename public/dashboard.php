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
<h1>Mi Dashboard</h1>

<?php if ($actual === null): ?>
    <p>Aún no has guardado ningún reporte semanal.</p>
<?php else: ?>
    <?php
    $semaforo = $calculator->semaforo((float) $actual['pronostico_ponderado_snapshot'], (float) $actual['meta_mes']);
    $ventaOtros = $servicio->ventaOtros((float) $actual['venta_general'], (float) $actual['venta_empresas']);
    $pctEmpresas = $servicio->participacion((float) $actual['venta_empresas'], (float) $actual['venta_general']) * 100;
    $pctOtros = $servicio->participacion($ventaOtros, (float) $actual['venta_general']) * 100;
    ?>
    <p class="<?= $semaforo ?>">Semáforo actual: <?= strtoupper($semaforo) ?></p>
    <p>Total pipeline: <?= number_format((float) $actual['total_pipeline_snapshot'], 0) ?></p>
    <p>Pronóstico ponderado: <?= number_format((float) $actual['pronostico_ponderado_snapshot'], 0) ?></p>

    <h2>Venta semanal por categoría</h2>
    <div style="display:flex; height:24px; width:100%; max-width:600px; border:1px solid #ccc;">
        <div style="background:#1c2b4a; width:<?= $pctEmpresas ?>%;"></div>
        <div style="background:#90a4d4; width:<?= $pctOtros ?>%;"></div>
    </div>
    <p>
        Empresas: <?= number_format((float) $actual['venta_empresas'], 0) ?> (<?= number_format($pctEmpresas, 1) ?>%) —
        Otros: <?= number_format($ventaOtros, 0) ?> (<?= number_format($pctOtros, 1) ?>%)
    </p>
<?php endif; ?>

<h2>Histórico de reportes</h2>
<table>
    <tr><th>Semana</th><th>Meta mes</th><th>Venta general</th><th>Pronóstico</th><th>Semáforo</th></tr>
    <?php foreach ($historial as $reporte): ?>
        <?php $s = $calculator->semaforo((float) $reporte['pronostico_ponderado_snapshot'], (float) $reporte['meta_mes']); ?>
        <tr>
            <td><?= $reporte['fecha_reporte'] ?></td>
            <td><?= number_format((float) $reporte['meta_mes'], 0) ?></td>
            <td><?= number_format((float) $reporte['venta_general'], 0) ?></td>
            <td><?= number_format((float) $reporte['pronostico_ponderado_snapshot'], 0) ?></td>
            <td class="<?= $s ?>"><?= strtoupper($s) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
