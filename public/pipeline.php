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
<h1>Mi Pipeline</h1>

<form method="post">
    <input type="hidden" name="accion" value="crear">
    <input type="text" name="cuenta" placeholder="Cuenta" required>
    <input type="text" name="nit" placeholder="NIT" required>
    <select name="tipo" required>
        <?php foreach (['COMPUTO','SERVIDOR','IMPRESION','SOFTWARE','IMAGEN_Y_VIDEO','SERVICIOS','CONECTIVIDAD','COMBINADO'] as $tipo): ?>
            <option value="<?= $tipo ?>"><?= $tipo ?></option>
        <?php endforeach; ?>
    </select>
    <input type="date" name="fecha_creacion" value="<?= $hoy->format('Y-m-d') ?>" required>
    <input type="number" name="monto" placeholder="Monto" step="0.01" required>
    <select name="estado" required>
        <?php foreach (['ES','POC','COC','PF','OTROS'] as $estado): ?>
            <option value="<?= $estado ?>"><?= $estado ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Agregar oportunidad</button>
</form>

<table>
    <tr><th>Cuenta</th><th>NIT</th><th>Tipo</th><th>Monto</th><th>Días</th><th>Probabilidad</th><th>Estado</th><th>Acciones</th></tr>
    <?php foreach ($oportunidades as $op): ?>
        <?php $dias = $calculator->dias(new DateTimeImmutable($op['fecha_creacion']), $hoy); ?>
        <tr>
            <td><?= htmlspecialchars($op['cuenta']) ?></td>
            <td><?= htmlspecialchars($op['nit']) ?></td>
            <td><?= htmlspecialchars($op['tipo']) ?></td>
            <td><?= number_format((float) $op['monto'], 0) ?></td>
            <td><?= $dias ?></td>
            <td><?= $calculator->probabilidad($dias) ?></td>
            <td><?= htmlspecialchars($op['estado']) ?></td>
            <td>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="accion" value="desactivar">
                    <input type="hidden" name="id" value="<?= $op['id'] ?>">
                    <button type="submit">Desactivar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
