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
    <form method="post">
        <?php foreach ($parametros as $clave => $valor): ?>
            <label for="param-<?= htmlspecialchars($clave) ?>"><?= htmlspecialchars($clave) ?></label>
            <input type="text" id="param-<?= htmlspecialchars($clave) ?>" name="parametros[<?= htmlspecialchars($clave) ?>]" value="<?= htmlspecialchars($valor) ?>">
        <?php endforeach; ?>
        <button type="submit">Guardar parámetros</button>
    </form>
</div>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
