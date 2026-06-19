<?php
require_once __DIR__ . '/../includes/auth.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    if (attemptLogin($email, $password)) {
        header('Location: ' . (currentUserRol() === 'admin' ? '/admin/dashboard.php' : '/dashboard.php'));
        exit;
    }
    $error = 'Email o contraseña incorrectos.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Iniciar sesión - Forecast Compulago</title></head>
<body>
<h1>Forecast Compulago</h1>
<?php if ($error): ?><p style="color:red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Contraseña" required>
    <button type="submit">Entrar</button>
</form>
</body>
</html>
