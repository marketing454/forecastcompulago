<?php require_once __DIR__ . '/auth.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Forecast Compulago</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f4f4; }
        nav { background: #1c2b4a; padding: 12px 20px; }
        nav a { color: #fff; margin-right: 16px; text-decoration: none; }
        main { padding: 20px; max-width: 1100px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .rojo { background: #FF0000; color: #fff; }
        .ambar { background: #FFC000; }
        .verde { background: #00B050; color: #fff; }
        form input, form select, form textarea { display: block; margin-bottom: 10px; width: 100%; max-width: 400px; padding: 6px; }
        button { padding: 8px 16px; cursor: pointer; }
    </style>
</head>
<body>
<nav>
    <?php if (currentUserRol() === 'ejecutivo'): ?>
        <a href="/pipeline.php">Mi Pipeline</a>
        <a href="/reporte.php">Reporte Semanal</a>
        <a href="/dashboard.php">Mi Dashboard</a>
    <?php elseif (currentUserRol() === 'admin'): ?>
        <a href="/admin/dashboard.php">Dashboard Consolidado</a>
        <a href="/admin/usuarios.php">Usuarios</a>
        <a href="/admin/parametros.php">Parámetros</a>
    <?php endif; ?>
    <a href="/logout.php" style="float:right;">Salir</a>
</nav>
<main>
