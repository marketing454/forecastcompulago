<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

use App\Models\MetaMensual;
use App\Models\Usuario;

$hoy = new DateTimeImmutable();
$mensaje = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        Usuario::create($_POST['nombre'], $_POST['email'], $_POST['password'], 'ejecutivo');
        $mensaje = 'Ejecutivo creado.';
    } elseif ($accion === 'desactivar') {
        Usuario::setActivo((int) $_POST['id'], false);
        $mensaje = 'Ejecutivo desactivado.';
    } elseif ($accion === 'activar') {
        Usuario::setActivo((int) $_POST['id'], true);
        $mensaje = 'Ejecutivo activado.';
    } elseif ($accion === 'meta') {
        MetaMensual::upsert((int) $_POST['ejecutivo_id'], (int) $hoy->format('Y'), (int) $hoy->format('n'), (float) $_POST['monto_meta']);
        $mensaje = 'Meta del mes actualizada.';
    }
}

$ejecutivos = Usuario::allEjecutivos();

require __DIR__ . '/../../includes/layout_header.php';
?>
<h1>Gestión de usuarios</h1>
<?php if ($mensaje): ?><p style="color:green;"><?= htmlspecialchars($mensaje) ?></p><?php endif; ?>

<h2>Crear ejecutivo</h2>
<form method="post">
    <input type="hidden" name="accion" value="crear">
    <input type="text" name="nombre" placeholder="Nombre" required>
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Contraseña temporal" required>
    <button type="submit">Crear</button>
</form>

<h2>Ejecutivos</h2>
<table>
    <tr><th>Nombre</th><th>Email</th><th>Activo</th><th>Meta del mes</th><th>Acciones</th></tr>
    <?php foreach ($ejecutivos as $ej): ?>
        <tr>
            <td><?= htmlspecialchars($ej['nombre']) ?></td>
            <td><?= htmlspecialchars($ej['email']) ?></td>
            <td><?= $ej['activo'] ? 'Sí' : 'No' ?></td>
            <td>
                <form method="post" style="display:flex; gap:6px;">
                    <input type="hidden" name="accion" value="meta">
                    <input type="hidden" name="ejecutivo_id" value="<?= $ej['id'] ?>">
                    <input type="number" name="monto_meta" step="0.01" placeholder="Monto">
                    <button type="submit">Guardar</button>
                </form>
            </td>
            <td>
                <form method="post">
                    <input type="hidden" name="id" value="<?= $ej['id'] ?>">
                    <input type="hidden" name="accion" value="<?= $ej['activo'] ? 'desactivar' : 'activar' ?>">
                    <button type="submit"><?= $ej['activo'] ? 'Desactivar' : 'Activar' ?></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
