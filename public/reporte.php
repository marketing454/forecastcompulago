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
$fechaSemanaActual = ReporteSemanal::fechaInicioSemana($hoy);
$fechaSemana = $_GET['semana'] ?? $fechaSemanaActual;
$esSemanaActual = $fechaSemana === $fechaSemanaActual;

$reporteActual = ReporteSemanal::findPorFecha($ejecutivoId, $fechaSemana);
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
        ReporteSemanal::guardar($ejecutivoId, (float) $_POST['meta_mes'], $ventaEmpresas, $ventaGeneral, $_POST['comentarios'], $_POST['fecha_semana']);
        $mensaje = 'Reporte guardado.';
        $reporteActual = ReporteSemanal::findPorFecha($ejecutivoId, $fechaSemana);
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
<?php if (!$esSemanaActual): ?>
<div class="alert" style="background:#eff4ff; border:1px solid #c7d9fb; color:#2654b8;">
    <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 6.5v4M10 13.5h.01M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Estás editando la semana del <?= htmlspecialchars($fechaSemana) ?> (no es la semana actual). <a href="/reporte.php">Ir a la semana actual</a>.</span>
</div>
<?php endif; ?>
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
        <div class="stat-head"><?= icono('pipeline') ?><span class="stat-label">Total pipeline activo</span></div>
        <span class="stat-value"><?= number_format($totalPipeline, 0) ?></span>
    </div>
    <div class="stat-card">
        <div class="stat-head"><?= icono('meta') ?><span class="stat-label">Pronóstico ponderado</span></div>
        <span class="stat-value"><?= number_format($pronostico, 0) ?></span>
    </div>
</div>

<div class="card">
    <h2 class="card-title"><?= icono('venta') ?> Venta de la semana del <?= htmlspecialchars($fechaSemana) ?></h2>
    <form method="post" id="form-reporte">
        <input type="hidden" name="fecha_semana" value="<?= htmlspecialchars($fechaSemana) ?>">
        <label for="meta_mes">Meta del mes</label>
        <input type="number" id="meta_mes" name="meta_mes" step="0.01" min="0" value="<?= htmlspecialchars((string) $metaMesDefault) ?>" required>

        <label for="venta_empresas">Venta empresas (esta semana)</label>
        <input type="number" id="venta_empresas" name="venta_empresas" step="0.01" min="0" value="<?= htmlspecialchars((string) ($reporteActual['venta_empresas'] ?? '')) ?>" required>

        <label for="venta_general">Venta general (esta semana)</label>
        <input type="number" id="venta_general" name="venta_general" step="0.01" min="0" value="<?= htmlspecialchars((string) ($reporteActual['venta_general'] ?? '')) ?>" required>
        <span class="field-hint" id="preview-otros">Escribe ambos montos para ver el desglose por categoría.</span>

        <label for="comentarios">Comentarios</label>
        <textarea id="comentarios" name="comentarios" rows="4" placeholder="Novedades, seguimientos pendientes, contexto de la semana..."><?= htmlspecialchars($reporteActual['comentarios'] ?? '') ?></textarea>

        <div class="form-actions">
            <button type="submit"><?= $esSemanaActual ? 'Guardar reporte de esta semana' : 'Guardar cambios' ?></button>
        </div>
    </form>
</div>

<script>
(function () {
    var general = document.getElementById('venta_general');
    var empresas = document.getElementById('venta_empresas');
    var preview = document.getElementById('preview-otros');
    if (!general || !empresas || !preview) return;
    var formato = new Intl.NumberFormat('es-CO');

    function actualizar() {
        var g = parseFloat(general.value) || 0;
        var e = parseFloat(empresas.value) || 0;
        preview.classList.remove('field-hint-error', 'field-hint-success');

        if (g <= 0) {
            preview.textContent = 'Escribe ambos montos para ver el desglose por categoría.';
            return;
        }
        if (e > g) {
            preview.textContent = 'La venta de empresas no puede superar la venta general.';
            preview.classList.add('field-hint-error');
            return;
        }
        var otros = g - e;
        var pctEmpresas = (e / g * 100).toFixed(1);
        var pctOtros = (otros / g * 100).toFixed(1);
        preview.textContent = 'Otros: ' + formato.format(otros) + ' (' + pctOtros + '%) · Empresas: ' + pctEmpresas + '%';
        preview.classList.add('field-hint-success');
    }

    general.addEventListener('input', actualizar);
    empresas.addEventListener('input', actualizar);
    actualizar();
})();
</script>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
