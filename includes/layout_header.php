<?php
require_once __DIR__ . '/auth.php';
$rutaActual = $_SERVER['SCRIPT_NAME'] ?? '';

function icono(string $nombre): string
{
    $trazos = [
        'pipeline' => '<path d="M4 15V9M9 15V5M14 15v-4M17 15V7"/>',
        'meta'     => '<circle cx="10" cy="10" r="6.5"/><circle cx="10" cy="10" r="2.5"/>',
        'venta'    => '<circle cx="10" cy="10" r="7"/><path d="M10 6v8"/><path d="M12.5 8c0-1.1-1.1-2-2.5-2S7.5 6.8 7.5 7.8c0 2 5 .7 5 2.9 0 1-1.1 1.8-2.5 1.8S7.5 11.7 7.5 10.7"/>',
        'ajustes'  => '<line x1="3" y1="5" x2="17" y2="5"/><circle cx="8" cy="5" r="1.6" fill="#ffffff"/><line x1="3" y1="10" x2="17" y2="10"/><circle cx="13" cy="10" r="1.6" fill="#ffffff"/><line x1="3" y1="15" x2="17" y2="15"/><circle cx="6" cy="15" r="1.6" fill="#ffffff"/>',
        'persona'  => '<circle cx="10" cy="7" r="3"/><path d="M4 16.5c0-3 2.7-5 6-5s6 2 6 5"/>',
    ];
    $contenido = $trazos[$nombre] ?? '';
    return '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $contenido . '</svg>';
}

function pesos($valor): string
{
    if ($valor === null || $valor === '') {
        return '';
    }
    return number_format((float) $valor, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forecast Compulago</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-primary: #1c2b4a;
            --color-primary-hover: #15213a;
            --color-accent: #3b5fb5;
            --color-bg: #f4f6fa;
            --color-card: #ffffff;
            --color-text: #1c2333;
            --color-text-secondary: #5b6472;
            --color-border: #e3e7ee;
            --color-error-bg: #fef3f2;
            --color-error-text: #b42318;
            --color-error-border: #fda29b;
            --color-success-bg: #ecfdf3;
            --color-success-text: #027a48;
            --color-success-border: #abefc6;
            --radius: 12px;
            --radius-sm: 8px;
            --shadow: 0 4px 24px rgba(28,43,74,0.08), 0 1px 2px rgba(28,43,74,0.06);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--color-bg);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--color-text);
            -webkit-font-smoothing: antialiased;
        }

        a { color: var(--color-accent); }

        .topbar {
            background: var(--color-primary);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            height: 60px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .topbar-brand {
            display: flex;
            align-items: baseline;
            gap: 6px;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            font-size: 17px;
            white-space: nowrap;
        }

        .topbar-brand small {
            font-weight: 500;
            font-size: 12px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.6);
        }

        .topbar-nav {
            display: flex;
            align-items: center;
            gap: 4px;
            flex: 1;
            margin-left: 32px;
            overflow-x: auto;
        }

        .topbar-nav a {
            color: rgba(255,255,255,0.78);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            white-space: nowrap;
            transition: background-color 150ms ease, color 150ms ease;
        }

        .topbar-nav a:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .topbar-nav a.active { background: rgba(255,255,255,0.14); color: #fff; }

        .topbar-user {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-shrink: 0;
        }

        .topbar-user span {
            color: rgba(255,255,255,0.7);
            font-size: 13px;
            white-space: nowrap;
        }

        .topbar-logout {
            color: #fff;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            padding: 7px 14px;
            border-radius: var(--radius-sm);
            background: rgba(255,255,255,0.1);
            transition: background-color 150ms ease;
        }

        .topbar-logout:hover { background: rgba(255,255,255,0.2); }

        main {
            padding: 32px 24px 56px;
            max-width: 1100px;
            margin: 0 auto;
        }

        h1.page-title {
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 24px;
            color: var(--color-text);
        }

        h2.section-title {
            font-size: 15px;
            font-weight: 600;
            margin: 32px 0 14px;
            color: var(--color-text);
        }

        .card {
            background: var(--color-card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 24px;
            margin-bottom: 24px;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--color-card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 18px 20px;
        }

        .stat-card .stat-head {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 10px;
        }

        .stat-card .stat-head svg { color: var(--color-accent); flex-shrink: 0; }

        .stat-card .stat-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--color-text-secondary);
        }

        .stat-card .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--color-text);
            font-variant-numeric: tabular-nums;
        }

        @keyframes rise-in {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card, .stat-card, .table-wrap, .empty-state { animation: rise-in 280ms ease-out; }

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .alert svg { flex-shrink: 0; margin-top: 2px; }
        .alert-error { background: var(--color-error-bg); border: 1px solid var(--color-error-border); color: var(--color-error-text); }
        .alert-success { background: var(--color-success-bg); border: 1px solid var(--color-success-border); color: var(--color-success-text); }

        form > label, .field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--color-text);
        }

        form input, form select, form textarea {
            display: block;
            width: 100%;
            max-width: 420px;
            min-height: 42px;
            padding: 9px 12px;
            margin-bottom: 14px;
            font-size: 14px;
            font-family: inherit;
            color: var(--color-text);
            background: #fff;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            transition: border-color 150ms ease, box-shadow 150ms ease;
        }

        form textarea { min-height: 90px; max-width: 100%; }

        form input:focus, form select:focus, form textarea:focus {
            outline: none;
            border-color: var(--color-accent);
            box-shadow: 0 0 0 3px rgba(59,95,181,0.16);
        }

        .form-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
            margin: 0;
        }

        .form-row > div { flex: 1 1 150px; min-width: 140px; }
        .form-row input, .form-row select { width: 100%; max-width: none; margin-bottom: 0; }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px 16px;
        }

        .form-grid input, .form-grid select { width: 100%; max-width: none; margin-bottom: 0; }
        .form-actions { margin-top: 18px; }

        .field-hint {
            display: block;
            margin: 4px 0 14px;
            font-size: 12px;
            color: var(--color-text-secondary);
        }

        .form-grid .field-hint { margin: 6px 0 0; }

        .field-hint-error { color: var(--color-error-text); }
        .field-hint-success { color: var(--color-success-text); }

        .card-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            font-weight: 600;
            color: var(--color-text);
            margin: 0 0 16px;
        }

        .card-title svg { color: var(--color-accent); flex-shrink: 0; }

        form select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none' stroke='%235b6472' stroke-width='1.5'%3E%3Cpath d='M5 7l5 5 5-5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 14px;
            padding-right: 36px;
        }

        .num { font-variant-numeric: tabular-nums; }

        button, .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 18px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            background: var(--color-primary);
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: background-color 150ms ease, transform 100ms ease;
            text-decoration: none;
        }

        button:hover, .btn:hover { background: var(--color-primary-hover); }
        button:active, .btn:active { transform: scale(0.97); }
        button:focus-visible, .btn:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px; }
        button:disabled, .btn:disabled { opacity: 0.65; cursor: not-allowed; transform: none; }

        .btn-sm { min-height: 34px; padding: 0 12px; font-size: 13px; }
        .btn-danger { background: var(--color-error-text); }
        .btn-danger:hover { background: #8f1b10; }
        .btn-secondary { background: #fff; color: var(--color-text); border: 1px solid var(--color-border); }
        .btn-secondary:hover { background: #f1f3f7; }

        .table-wrap {
            background: var(--color-card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 16px; text-align: left; font-size: 14px; }
        thead th {
            background: #f8f9fb;
            color: var(--color-text-secondary);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            border-bottom: 1px solid var(--color-border);
        }
        tbody tr { border-bottom: 1px solid var(--color-border); }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #fafbfd; }

        .table-actions-cell { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }

        .empty-state {
            background: var(--color-card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 32px;
            text-align: center;
            color: var(--color-text-secondary);
            font-size: 14px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-tipo { background: #eef1f8; color: var(--color-primary); }
        .badge-success { background: #e6f9f4; color: #0f7a5e; }
        .badge-neutral { background: #f1f2f5; color: var(--color-text-secondary); }

        .badge-estado-es { background: #eff4ff; color: #2654b8; }
        .badge-estado-poc { background: #f4f0ff; color: #6b3fc2; }
        .badge-estado-coc { background: #e6f9f4; color: #0f7a5e; }
        .badge-estado-pf { background: #fff4e6; color: #b5650c; }
        .badge-estado-otros { background: #f1f2f5; color: var(--color-text-secondary); }

        /* Semáforo: mismos colores exactos del Excel original (spec §6/§9) */
        .rojo, .ambar, .verde {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }
        .rojo { background: #FF0000; color: #fff; }
        .ambar { background: #FFC000; color: #4a3300; }
        .verde { background: #00B050; color: #fff; }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; animation: none !important; }
        }

        @media (max-width: 720px) {
            .topbar { flex-wrap: wrap; height: auto; padding: 12px 16px; gap: 8px; }
            .topbar-nav { margin-left: 0; order: 3; width: 100%; }
            main { padding: 20px 16px 48px; }
        }
    </style>
</head>
<body>
<header class="topbar">
    <a href="/" class="topbar-brand">Forecast <small>Compulago</small></a>
    <nav class="topbar-nav">
        <?php if (currentUserRol() === 'ejecutivo'): ?>
            <a href="/pipeline.php" class="<?= str_contains($rutaActual, 'pipeline.php') ? 'active' : '' ?>">Mi Pipeline</a>
            <a href="/reporte.php" class="<?= str_contains($rutaActual, 'reporte.php') ? 'active' : '' ?>">Reporte Semanal</a>
            <a href="/dashboard.php" class="<?= str_contains($rutaActual, 'dashboard.php') ? 'active' : '' ?>">Mi Dashboard</a>
        <?php elseif (currentUserRol() === 'admin'): ?>
            <a href="/admin/dashboard.php" class="<?= str_contains($rutaActual, 'admin/dashboard.php') ? 'active' : '' ?>">Dashboard Consolidado</a>
            <a href="/admin/usuarios.php" class="<?= str_contains($rutaActual, 'usuarios.php') ? 'active' : '' ?>">Usuarios</a>
            <a href="/admin/parametros.php" class="<?= str_contains($rutaActual, 'parametros.php') ? 'active' : '' ?>">Parámetros</a>
        <?php endif; ?>
    </nav>
    <div class="topbar-user">
        <span><?= htmlspecialchars(currentUserNombre() ?? '') ?></span>
        <a href="/logout.php" class="topbar-logout">Salir</a>
    </div>
</header>
<main>
