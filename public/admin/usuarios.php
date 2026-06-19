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
<h1 class="page-title">Gestión de usuarios</h1>
<?php if ($mensaje): ?>
<div class="alert alert-success" role="status">
    <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M6.5 10.5l2.5 2.5 5-5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span><?= htmlspecialchars($mensaje) ?></span>
</div>
<?php endif; ?>

<h2 class="section-title">Crear ejecutivo</h2>
<div class="card">
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <div class="form-row">
            <div>
                <label for="nombre">Nombre</label>
                <input type="text" id="nombre" name="nombre" required>
            </div>
            <div>
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div>
                <label for="password">Contraseña temporal</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <button type="submit">Crear</button>
            </div>
        </div>
    </form>
</div>

<h2 class="section-title">Ejecutivos</h2>
<?php if (empty($ejecutivos)): ?>
    <div class="empty-state">Todavía no hay ejecutivos registrados.</div>
<?php else: ?>
<div class="table-wrap">
<table>
    <thead>
        <tr><th>Nombre</th><th>Email</th><th>Activo</th><th>Meta del mes</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($ejecutivos as $ej): ?>
        <tr>
            <td><?= htmlspecialchars($ej['nombre']) ?></td>
            <td><?= htmlspecialchars($ej['email']) ?></td>
            <td><span class="badge <?= $ej['activo'] ? 'badge-success' : 'badge-neutral' ?>"><?= $ej['activo'] ? 'Sí' : 'No' ?></span></td>
            <td>
                <form method="post" class="form-row">
                    <input type="hidden" name="accion" value="meta">
                    <input type="hidden" name="ejecutivo_id" value="<?= $ej['id'] ?>">
                    <input type="number" name="monto_meta" step="0.01" placeholder="Monto" style="max-width:140px;">
                    <button type="submit" class="btn-sm">Guardar</button>
                </form>
            </td>
            <td>
                <form method="post">
                    <input type="hidden" name="id" value="<?= $ej['id'] ?>">
                    <input type="hidden" name="accion" value="<?= $ej['activo'] ? 'desactivar' : 'activar' ?>">
                    <button type="submit" class="btn-sm <?= $ej['activo'] ? 'btn-danger' : 'btn-secondary' ?>"><?= $ej['activo'] ? 'Desactivar' : 'Activar' ?></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
