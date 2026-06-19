<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

use App\Models\Parametro;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['parametros'] as $clave => $valor) {
        Parametro::set($clave, $valor);
    }
}

$parametros = Parametro::allAsAssoc();

require __DIR__ . '/../../includes/layout_header.php';
?>
<h1 class="page-title">Parámetros del sistema</h1>
<div class="card">
    <h2 class="card-title"><?= icono('ajustes') ?> Reglas de cálculo del pronóstico</h2>
    <form method="post">
        <?php
        $ayudaParametros = [
            'pct_conversion_pipeline' => ['label' => 'Conversión del pipeline', 'hint' => 'Fracción del pipeline total usada como pronóstico ponderado. Ej: 0.30 equivale a 30%.', 'type' => 'number', 'step' => '0.01'],
            'umbral_dias_alta'        => ['label' => 'Antigüedad máxima — probabilidad ALTA', 'hint' => 'Oportunidades con esta cantidad de días o menos se marcan ALTA.', 'type' => 'number', 'step' => '1'],
            'umbral_dias_baja'        => ['label' => 'Antigüedad mínima — probabilidad BAJA', 'hint' => 'Oportunidades con más de estos días se marcan BAJA.', 'type' => 'number', 'step' => '1'],
            'umbral_semaforo_bajo'    => ['label' => 'Umbral del semáforo rojo', 'hint' => 'Por debajo de esta fracción de la meta, el semáforo se pone en rojo. Ej: 0.30 equivale a 30%.', 'type' => 'number', 'step' => '0.01'],
            'umbral_semaforo_alto'    => ['label' => 'Umbral del semáforo verde', 'hint' => 'Por encima de esta fracción de la meta, el semáforo se pone en verde. Ej: 0.80 equivale a 80%.', 'type' => 'number', 'step' => '0.01'],
        ];
        ?>
        <div class="form-grid">
        <?php foreach ($parametros as $clave => $valor): ?>
            <?php $info = $ayudaParametros[$clave] ?? ['label' => $clave, 'hint' => '', 'type' => 'text', 'step' => null]; ?>
            <div>
                <label for="param-<?= htmlspecialchars($clave) ?>"><?= htmlspecialchars($info['label']) ?></label>
                <input
                    type="<?= htmlspecialchars($info['type']) ?>"
                    <?= $info['step'] !== null ? 'step="' . htmlspecialchars($info['step']) . '"' : '' ?>
                    id="param-<?= htmlspecialchars($clave) ?>"
                    name="parametros[<?= htmlspecialchars($clave) ?>]"
                    value="<?= htmlspecialchars($valor) ?>"
                >
                <?php if ($info['hint'] !== ''): ?><span class="field-hint"><?= htmlspecialchars($info['hint']) ?></span><?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
        <div class="form-actions">
            <button type="submit">Guardar parámetros</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
