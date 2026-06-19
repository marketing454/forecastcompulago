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
<h1>Parámetros del sistema</h1>
<form method="post">
    <?php foreach ($parametros as $clave => $valor): ?>
        <label><?= htmlspecialchars($clave) ?></label>
        <input type="text" name="parametros[<?= htmlspecialchars($clave) ?>]" value="<?= htmlspecialchars($valor) ?>">
    <?php endforeach; ?>
    <button type="submit">Guardar parámetros</button>
</form>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
