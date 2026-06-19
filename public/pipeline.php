<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('ejecutivo');

use App\Models\Oportunidad;
use App\Models\Parametro;
use App\Services\PipelineCalculator;

$calculator = PipelineCalculator::fromParametros(Parametro::allAsAssoc());
$ejecutivoId = currentUserId();

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
    } elseif ($accion === 'desactivar') {
        Oportunidad::setActiva((int) $_POST['id'], false, $ejecutivoId);
    }
    header('Location: /pipeline.php');
    exit;
}

$oportunidades = Oportunidad::activasByEjecutivo($ejecutivoId);
$hoy = new DateTimeImmutable();

require __DIR__ . '/../includes/layout_header.php';
?>
<h1 class="page-title">Mi Pipeline</h1>

<div class="card">
    <h2 class="card-title"><?= icono('pipeline') ?> Nueva oportunidad</h2>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <div class="form-grid">
            <div>
                <label for="cuenta">Cuenta</label>
                <input type="text" id="cuenta" name="cuenta" required>
            </div>
            <div>
                <label for="nit">NIT</label>
                <input type="text" id="nit" name="nit" placeholder="900123456-7" required>
                <span class="field-hint">Incluye el dígito de verificación</span>
            </div>
            <div>
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo" required>
                    <?php foreach (['COMPUTO','SERVIDOR','IMPRESION','SOFTWARE','IMAGEN_Y_VIDEO','SERVICIOS','CONECTIVIDAD','COMBINADO'] as $tipo): ?>
                        <option value="<?= $tipo ?>"><?= $tipo ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="fecha_creacion">Fecha</label>
                <input type="date" id="fecha_creacion" name="fecha_creacion" value="<?= $hoy->format('Y-m-d') ?>" required>
            </div>
            <div>
                <label for="monto">Monto</label>
                <input type="number" id="monto" name="monto" step="0.01" min="0" placeholder="0" required>
            </div>
            <div>
                <label for="estado">Estado</label>
                <select id="estado" name="estado" required>
                    <?php foreach (['ES','POC','COC','PF','OTROS'] as $estado): ?>
                        <option value="<?= $estado ?>"><?= $estado ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit">Agregar oportunidad</button>
        </div>
    </form>
</div>

<?php if (empty($oportunidades)): ?>
    <div class="empty-state">Aún no tienes oportunidades activas en tu pipeline.</div>
<?php else: ?>
<div class="table-wrap">
<table>
    <thead>
        <tr><th>Cuenta</th><th>NIT</th><th>Tipo</th><th>Monto</th><th>Días</th><th>Probabilidad</th><th>Estado</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($oportunidades as $op): ?>
        <?php $dias = $calculator->dias(new DateTimeImmutable($op['fecha_creacion']), $hoy); ?>
        <tr>
            <td><?= htmlspecialchars($op['cuenta']) ?></td>
            <td><?= htmlspecialchars($op['nit']) ?></td>
            <td><span class="badge badge-tipo"><?= htmlspecialchars($op['tipo']) ?></span></td>
            <td class="num"><?= number_format((float) $op['monto'], 0) ?></td>
            <td class="num"><?= $dias ?></td>
            <td><?= $calculator->probabilidad($dias) ?></td>
            <td><span class="badge badge-estado-<?= htmlspecialchars(strtolower($op['estado'])) ?>"><?= htmlspecialchars($op['estado']) ?></span></td>
            <td>
                <form method="post">
                    <input type="hidden" name="accion" value="desactivar">
                    <input type="hidden" name="id" value="<?= $op['id'] ?>">
                    <button type="submit" class="btn-secondary btn-sm">Desactivar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
