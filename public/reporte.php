<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('ejecutivo');

use App\Models\MetaMensual;
use App\Models\Oportunidad;
use App\Models\Parametro;
use App\Models\ReporteSemanal;
use App\Services\PipelineCalculator;
use App\Services\ReporteSemanalService;

$ejecutivoId = currentUserId();
$hoy = new DateTimeImmutable();
$reporteActual = ReporteSemanal::findSemanaActual($ejecutivoId);
$metaDelMes = MetaMensual::forEjecutivoMes($ejecutivoId, (int) $hoy->format('Y'), (int) $hoy->format('n'));
$metaMesDefault = $reporteActual['meta_mes'] ?? ($metaDelMes['monto_meta'] ?? 0);

$mensaje = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $servicio = new ReporteSemanalService();
        $ventaGeneral = (float) $_POST['venta_general'];
        $ventaEmpresas = (float) $_POST['venta_empresas'];
        $servicio->ventaOtros($ventaGeneral, $ventaEmpresas);
        ReporteSemanal::guardar($ejecutivoId, (float) $_POST['meta_mes'], $ventaEmpresas, $ventaGeneral, $_POST['comentarios']);
        $mensaje = 'Reporte guardado.';
        $reporteActual = ReporteSemanal::findSemanaActual($ejecutivoId);
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}

$calculator = PipelineCalculator::fromParametros(Parametro::allAsAssoc());
$montos = array_column(Oportunidad::activasByEjecutivo($ejecutivoId), 'monto');
$totalPipeline = $calculator->totalPipeline($montos);
$pronostico = $calculator->pronosticoPonderado($totalPipeline);

require __DIR__ . '/../includes/layout_header.php';
?>
<h1 class="page-title">Reporte Semanal</h1>
<?php if ($mensaje): ?>
<div class="alert alert-success" role="status">
    <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M6.5 10.5l2.5 2.5 5-5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span><?= htmlspecialchars($mensaje) ?></span>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error" role="alert">
    <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 6.5v4M10 13.5h.01M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span><?= htmlspecialchars($error) ?></span>
</div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat-card">
        <span class="stat-label">Total pipeline activo</span>
        <span class="stat-value"><?= number_format($totalPipeline, 0) ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Pronóstico ponderado</span>
        <span class="stat-value"><?= number_format($pronostico, 0) ?></span>
    </div>
</div>

<div class="card">
    <form method="post">
        <label for="meta_mes">Meta del mes</label>
        <input type="number" id="meta_mes" name="meta_mes" step="0.01" value="<?= htmlspecialchars((string) $metaMesDefault) ?>" required>
        <label for="venta_empresas">Venta empresas (esta semana)</label>
        <input type="number" id="venta_empresas" name="venta_empresas" step="0.01" value="<?= htmlspecialchars((string) ($reporteActual['venta_empresas'] ?? '')) ?>" required>
        <label for="venta_general">Venta general (esta semana)</label>
        <input type="number" id="venta_general" name="venta_general" step="0.01" value="<?= htmlspecialchars((string) ($reporteActual['venta_general'] ?? '')) ?>" required>
        <label for="comentarios">Comentarios</label>
        <textarea id="comentarios" name="comentarios" rows="4"><?= htmlspecialchars($reporteActual['comentarios'] ?? '') ?></textarea>
        <button type="submit">Guardar reporte de esta semana</button>
    </form>
</div>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
