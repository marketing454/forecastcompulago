<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

use App\Models\MetaMensual;
use App\Models\Usuario;

$hoy = new DateTimeImmutable();
$mensaje = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    try {
        if ($accion === 'crear') {
            Usuario::create($_POST['nombre'], $_POST['email'], $_POST['password'], 'ejecutivo');
            $mensaje = 'Ejecutivo creado.';
        } elseif ($accion === 'actualizar_usuario') {
            Usuario::actualizar((int) $_POST['id'], $_POST['nombre'], $_POST['email']);
            $mensaje = 'Ejecutivo actualizado.';
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
    } catch (PDOException $e) {
        $error = $e->getCode() === '23000' ? 'Ese email ya está en uso por otro usuario.' : 'No se pudo guardar el cambio.';
    }
}

$ejecutivos = Usuario::allEjecutivos();
$anioActual = (int) $hoy->format('Y');
$mesActual = (int) $hoy->format('n');

$enEdicion = null;
if (isset($_GET['editar'])) {
    $candidato = Usuario::find((int) $_GET['editar']);
    if ($candidato !== null && $candidato['rol'] === 'ejecutivo') {
        $enEdicion = $candidato;
    }
}

require __DIR__ . '/../../includes/layout_header.php';
?>
<h1 class="page-title">Gestión de usuarios</h1>
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

<div class="card">
    <h2 class="card-title"><?= icono('persona') ?> <?= $enEdicion ? 'Editar ejecutivo' : 'Crear ejecutivo' ?></h2>
    <?php if ($enEdicion): ?>
        <p class="field-hint" style="margin-top:-6px;">Editando <strong style="color:var(--color-text);"><?= htmlspecialchars($enEdicion['nombre']) ?></strong> — <a href="/admin/usuarios.php">Cancelar</a></p>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="accion" value="<?= $enEdicion ? 'actualizar_usuario' : 'crear' ?>">
        <?php if ($enEdicion): ?><input type="hidden" name="id" value="<?= $enEdicion['id'] ?>"><?php endif; ?>
        <div class="form-grid">
            <div>
                <label for="nombre">Nombre</label>
                <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($enEdicion['nombre'] ?? '') ?>" required>
            </div>
            <div>
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($enEdicion['email'] ?? '') ?>" required>
            </div>
            <?php if (!$enEdicion): ?>
            <div>
                <label for="password">Contraseña temporal</label>
                <input type="password" id="password" name="password" required>
                <span class="field-hint">El ejecutivo debe cambiarla en su primer ingreso</span>
            </div>
            <?php endif; ?>
        </div>
        <div class="form-actions">
            <button type="submit"><?= $enEdicion ? 'Guardar cambios' : 'Crear ejecutivo' ?></button>
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
        <?php $metaActual = MetaMensual::forEjecutivoMes((int) $ej['id'], $anioActual, $mesActual); ?>
        <tr>
            <td><?= htmlspecialchars($ej['nombre']) ?></td>
            <td><?= htmlspecialchars($ej['email']) ?></td>
            <td><span class="badge <?= $ej['activo'] ? 'badge-success' : 'badge-neutral' ?>"><?= $ej['activo'] ? 'Sí' : 'No' ?></span></td>
            <td>
                <form method="post" class="form-row">
                    <input type="hidden" name="accion" value="meta">
                    <input type="hidden" name="ejecutivo_id" value="<?= $ej['id'] ?>">
                    <input type="number" name="monto_meta" step="0.01" placeholder="Sin meta asignada" value="<?= $metaActual ? htmlspecialchars((string) $metaActual['monto_meta']) : '' ?>" style="max-width:160px;">
                    <button type="submit" class="btn-sm">Guardar</button>
                </form>
            </td>
            <td class="table-actions-cell">
                <a href="/admin/usuarios.php?editar=<?= $ej['id'] ?>" class="btn btn-secondary btn-sm">Editar</a>
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
