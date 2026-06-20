<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('ejecutivo');

use App\Models\MetaMensual;
use App\Models\Oportunidad;
use App\Models\Parametro;
use App\Models\ReporteSemanal;
use App\Services\PipelineCalculator;
use App\Services\ReporteSemanalService;

$calculator = PipelineCalculator::fromParametros(Parametro::allAsAssoc());
$ejecutivoId = currentUserId();
$verTodas = ($_GET['ver'] ?? '') === 'todas';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        Oportunidad::create(
            $ejecutivoId,
            $_POST['cuenta'],
            $_POST['nit'],
            $_POST['tipo'],
            $_POST['fecha_creacion'],
            (float) $_POST['monto'],
            $_POST['estado']
        );
    } elseif ($accion === 'actualizar') {
        Oportunidad::update(
            (int) $_POST['id'],
            $_POST['cuenta'],
            $_POST['nit'],
            $_POST['tipo'],
            (float) $_POST['monto'],
            $_POST['estado'],
            $ejecutivoId
        );
    } elseif ($accion === 'desactivar') {
        Oportunidad::setActiva((int) $_POST['id'], false, $ejecutivoId);
    } elseif ($accion === 'reactivar') {
        Oportunidad::setActiva((int) $_POST['id'], true, $ejecutivoId);
    }
    header('Location: /pipeline.php' . ($verTodas ? '?ver=todas' : ''));
    exit;
}

$oportunidades = $verTodas ? Oportunidad::todasByEjecutivo($ejecutivoId) : Oportunidad::activasByEjecutivo($ejecutivoId);
$hoy = new DateTimeImmutable();

$tipos = ['COMPUTO','SERVIDOR','IMPRESION','SOFTWARE','IMAGEN_Y_VIDEO','SERVICIOS','CONECTIVIDAD','COMBINADO'];
$estados = [
    'ES'    => 'Estudio',
    'POC'   => 'Por orden de compra',
    'COC'   => 'Con orden de compra',
    'PF'    => 'Por facturar',
    'OTROS' => 'Otro',
];

$enEdicion = null;
if (isset($_GET['editar'])) {
    $candidata = Oportunidad::find((int) $_GET['editar']);
    if ($candidata !== null && (int) $candidata['ejecutivo_id'] === $ejecutivoId) {
        $enEdicion = $candidata;
    }
}

$montosActivos = array_column(Oportunidad::activasByEjecutivo($ejecutivoId), 'monto');
$totalPipelineLive = $calculator->totalPipeline($montosActivos);
$pronosticoLive = $calculator->pronosticoPonderado($totalPipelineLive);
$metaDelMes = MetaMensual::forEjecutivoMes($ejecutivoId, (int) $hoy->format('Y'), (int) $hoy->format('n'));
$metaMesLive = (float) ($metaDelMes['monto_meta'] ?? 0);
$semaforoLive = $calculator->semaforo($pronosticoLive, $metaMesLive);

$ultimoReporte = ReporteSemanal::historial($ejecutivoId)[0] ?? null;
if ($ultimoReporte !== null) {
    $servicioVenta = new ReporteSemanalService();
    $ventaOtrosUltimo = $servicioVenta->ventaOtros((float) $ultimoReporte['venta_general'], (float) $ultimoReporte['venta_empresas']);
    $pctEmpresasUltimo = $servicioVenta->participacion((float) $ultimoReporte['venta_empresas'], (float) $ultimoReporte['venta_general']) * 100;
    $pctOtrosUltimo = $servicioVenta->participacion($ventaOtrosUltimo, (float) $ultimoReporte['venta_general']) * 100;
}

require __DIR__ . '/../includes/layout_header.php';
?>
<h1 class="page-title">Mi Pipeline</h1>

<div class="card">
    <h2 class="card-title"><?= icono('meta') ?> Pronóstico vs meta del mes</h2>
    <div style="display:flex; align-items:center; justify-content:center; gap:32px; flex-wrap:wrap;">
        <div style="flex-shrink:0;"><?= gaugeSvg($semaforoLive, $pronosticoLive, $metaMesLive) ?></div>
        <div style="text-align:left; min-width:220px;">
            <span class="stat-label" style="display:block; margin-bottom:8px;">Pronóstico ponderado</span>
            <span class="stat-value" style="font-size:30px;"><?= pesos($pronosticoLive) ?></span>
            <p style="margin:12px 0 0; font-size:16px; color:var(--color-text-secondary);">
                de <strong style="color:var(--color-text);"><?= $metaMesLive > 0 ? pesos($metaMesLive) : 'sin meta asignada' ?></strong>
            </p>
            <p style="margin:6px 0 0; font-size:16px; color:var(--color-text-secondary);">
                Pipeline activo: <strong style="color:var(--color-text);"><?= pesos($totalPipelineLive) ?></strong>
            </p>
            <?php if ($metaMesLive <= 0): ?>
                <p class="field-hint" style="margin-top:10px;">Pídele a tu admin que te asigne la meta del mes para ver tu avance real.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($ultimoReporte !== null): ?>
<div class="card">
    <h2 class="card-title"><?= icono('venta') ?> Venta semanal por categoría</h2>
    <div style="display:flex; align-items:center; justify-content:center; gap:32px; flex-wrap:wrap;">
        <?= donutSvg($pctEmpresasUltimo, $pctOtrosUltimo) ?>
        <div style="text-align:left; min-width:200px;">
            <p style="display:flex; align-items:center; gap:8px; margin:0 0 10px; font-size:15px; color:var(--color-text-secondary);">
                <span style="width:12px; height:12px; border-radius:3px; background:#1c2b4a; flex-shrink:0;"></span>
                Empresas: <strong style="color:var(--color-text);"><?= pesos($ultimoReporte['venta_empresas']) ?></strong> (<?= number_format($pctEmpresasUltimo, 1) ?>%)
            </p>
            <p style="display:flex; align-items:center; gap:8px; margin:0; font-size:15px; color:var(--color-text-secondary);">
                <span style="width:12px; height:12px; border-radius:3px; background:#90a4d4; flex-shrink:0;"></span>
                Otros: <strong style="color:var(--color-text);"><?= pesos($ventaOtrosUltimo) ?></strong> (<?= number_format($pctOtrosUltimo, 1) ?>%)
            </p>
            <p style="margin:14px 0 0; font-size:13px; color:var(--color-text-secondary);">Semana del <?= htmlspecialchars($ultimoReporte['fecha_reporte']) ?> — Total: <strong style="color:var(--color-text);"><?= pesos($ultimoReporte['venta_general']) ?></strong></p>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <h2 class="card-title"><?= icono('pipeline') ?> <?= $enEdicion ? 'Editar oportunidad' : 'Nueva oportunidad' ?></h2>
    <?php if ($enEdicion): ?>
        <p class="field-hint" style="margin-top:-6px;">Editando <strong style="color:var(--color-text);"><?= htmlspecialchars($enEdicion['cuenta']) ?></strong> — <a href="/pipeline.php<?= $verTodas ? '?ver=todas' : '' ?>">Cancelar</a></p>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="accion" value="<?= $enEdicion ? 'actualizar' : 'crear' ?>">
        <?php if ($enEdicion): ?><input type="hidden" name="id" value="<?= $enEdicion['id'] ?>"><?php endif; ?>
        <div class="form-grid">
            <div>
                <label for="cuenta">Cuenta</label>
                <input type="text" id="cuenta" name="cuenta" value="<?= htmlspecialchars($enEdicion['cuenta'] ?? '') ?>" required>
            </div>
            <div>
                <label for="nit">NIT</label>
                <input type="text" id="nit" name="nit" placeholder="900123456-7" value="<?= htmlspecialchars($enEdicion['nit'] ?? '') ?>" required>
                <span class="field-hint">Incluye el dígito de verificación</span>
            </div>
            <div>
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo" required>
                    <?php foreach ($tipos as $tipo): ?>
                        <option value="<?= $tipo ?>" <?= ($enEdicion['tipo'] ?? '') === $tipo ? 'selected' : '' ?>><?= $tipo ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="fecha_creacion">Fecha</label>
                <input type="date" id="fecha_creacion" name="fecha_creacion" value="<?= htmlspecialchars($enEdicion['fecha_creacion'] ?? $hoy->format('Y-m-d')) ?>" <?= $enEdicion ? 'disabled' : '' ?> required>
                <?php if ($enEdicion): ?><span class="field-hint">La fecha de creación no se puede modificar</span><?php endif; ?>
            </div>
            <div>
                <label for="monto">Monto</label>
                <input type="text" id="monto" name="monto" inputmode="numeric" data-moneda placeholder="0" value="<?= pesos($enEdicion['monto'] ?? '') ?>" required>
            </div>
            <div>
                <label for="estado">Estado</label>
                <select id="estado" name="estado" required>
                    <?php foreach ($estados as $valor => $etiqueta): ?>
                        <option value="<?= $valor ?>" <?= ($enEdicion['estado'] ?? '') === $valor ? 'selected' : '' ?>><?= $valor ?> - <?= $etiqueta ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit"><?= $enEdicion ? 'Guardar cambios' : 'Agregar oportunidad' ?></button>
        </div>
    </form>
</div>

<p style="margin: -8px 0 20px;">
    <a href="/pipeline.php<?= $verTodas ? '' : '?ver=todas' ?>"><?= $verTodas ? 'Ver solo activas' : 'Ver también inactivas' ?></a>
</p>

<?php if (empty($oportunidades)): ?>
    <div class="empty-state"><?= $verTodas ? 'Todavía no tienes ninguna oportunidad registrada.' : 'Aún no tienes oportunidades activas en tu pipeline.' ?></div>
<?php else: ?>
<div class="table-wrap">
<table>
    <thead>
        <tr><th>Cuenta</th><th>NIT</th><th>Tipo</th><th>Monto</th><th>Días</th><th>Probabilidad</th><th>Estado</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($oportunidades as $op): ?>
        <?php $dias = $calculator->dias(new DateTimeImmutable($op['fecha_creacion']), $hoy); ?>
        <tr<?= $op['activa'] ? '' : ' style="opacity:0.55;"' ?>>
            <td><?= htmlspecialchars($op['cuenta']) ?><?= $op['activa'] ? '' : ' <span class="badge badge-neutral">Inactiva</span>' ?></td>
            <td><?= htmlspecialchars($op['nit']) ?></td>
            <td><span class="badge badge-tipo"><?= htmlspecialchars($op['tipo']) ?></span></td>
            <td class="num"><?= pesos($op['monto']) ?></td>
            <td class="num"><?= $dias ?></td>
            <td><?= $calculator->probabilidad($dias) ?></td>
            <td><span class="badge badge-estado-<?= htmlspecialchars(strtolower($op['estado'])) ?>" title="<?= htmlspecialchars($estados[$op['estado']] ?? '') ?>"><?= htmlspecialchars($op['estado']) ?></span></td>
            <td class="table-actions-cell">
                <a href="/pipeline.php?editar=<?= $op['id'] ?><?= $verTodas ? '&ver=todas' : '' ?>" class="btn btn-secondary btn-sm">Editar</a>
                <form method="post">
                    <input type="hidden" name="accion" value="<?= $op['activa'] ? 'desactivar' : 'reactivar' ?>">
                    <input type="hidden" name="id" value="<?= $op['id'] ?>">
                    <button type="submit" class="btn-sm <?= $op['activa'] ? 'btn-secondary' : '' ?>"><?= $op['activa'] ? 'Desactivar' : 'Reactivar' ?></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
