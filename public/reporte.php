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
<h1>Reporte Semanal</h1>
<?php if ($mensaje): ?><p style="color:green;"><?= htmlspecialchars($mensaje) ?></p><?php endif; ?>
<?php if ($error): ?><p style="color:red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

<p>Total pipeline activo: <?= number_format($totalPipeline, 0) ?> — Pronóstico ponderado: <?= number_format($pronostico, 0) ?></p>

<form method="post">
    <label>Meta del mes</label>
    <input type="number" name="meta_mes" step="0.01" value="<?= htmlspecialchars((string) $metaMesDefault) ?>" required>
    <label>Venta empresas (esta semana)</label>
    <input type="number" name="venta_empresas" step="0.01" value="<?= htmlspecialchars((string) ($reporteActual['venta_empresas'] ?? '')) ?>" required>
    <label>Venta general (esta semana)</label>
    <input type="number" name="venta_general" step="0.01" value="<?= htmlspecialchars((string) ($reporteActual['venta_general'] ?? '')) ?>" required>
    <label>Comentarios</label>
    <textarea name="comentarios" rows="4"><?= htmlspecialchars($reporteActual['comentarios'] ?? '') ?></textarea>
    <button type="submit">Guardar reporte de esta semana</button>
</form>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
