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
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Iniciar sesión - Forecast Compulago</title>
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
        --color-border: #d8dee8;
        --color-error-bg: #fef3f2;
        --color-error-text: #b42318;
        --color-error-border: #fda29b;
        --radius: 12px;
        --shadow: 0 4px 24px rgba(28,43,74,0.08), 0 1px 2px rgba(28,43,74,0.06);
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--color-bg);
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        color: var(--color-text);
        padding: 24px;
    }

    .login-card {
        width: 100%;
        max-width: 400px;
        background: var(--color-card);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 40px 32px;
    }

    .brand { text-align: center; margin-bottom: 32px; }

    .brand-eyebrow {
        display: block;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--color-text-secondary);
        margin-bottom: 6px;
    }

    .brand-title {
        font-size: 24px;
        font-weight: 700;
        color: var(--color-primary);
        margin: 0;
    }

    .alert {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        background: var(--color-error-bg);
        border: 1px solid var(--color-error-border);
        color: var(--color-error-text);
        border-radius: 8px;
        padding: 12px 14px;
        font-size: 14px;
        margin-bottom: 20px;
    }

    .alert svg { flex-shrink: 0; margin-top: 2px; }

    .field { margin-bottom: 20px; }

    .field label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 6px;
        color: var(--color-text);
    }

    .field-input-wrap { position: relative; }

    .field input {
        width: 100%;
        min-height: 44px;
        padding: 10px 14px;
        font-size: 15px;
        font-family: inherit;
        color: var(--color-text);
        background: #fff;
        border: 1px solid var(--color-border);
        border-radius: 8px;
        transition: border-color 150ms ease, box-shadow 150ms ease;
    }

    .field input:focus {
        outline: none;
        border-color: var(--color-accent);
        box-shadow: 0 0 0 3px rgba(59,95,181,0.18);
    }

    .field input[data-password-field] { padding-right: 44px; }

    .toggle-password {
        position: absolute;
        right: 6px;
        top: 50%;
        transform: translateY(-50%);
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        border: none;
        border-radius: 6px;
        color: var(--color-text-secondary);
        cursor: pointer;
    }

    .toggle-password:hover { background: #f1f3f7; color: var(--color-text); }
    .toggle-password:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px; }

    .btn-submit {
        width: 100%;
        min-height: 44px;
        background: var(--color-primary);
        color: #fff;
        font-family: inherit;
        font-size: 15px;
        font-weight: 600;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: background-color 150ms ease;
        margin-top: 4px;
    }

    .btn-submit:hover { background: var(--color-primary-hover); }
    .btn-submit:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px; }

    .footnote {
        margin-top: 24px;
        text-align: center;
        font-size: 12px;
        color: var(--color-text-secondary);
    }

    @media (prefers-reduced-motion: reduce) {
        .field input, .btn-submit, .toggle-password { transition: none; }
    }
</style>
</head>
<body>
<main class="login-card" role="main">
    <div class="brand">
        <span class="brand-eyebrow">Compulago</span>
        <h1 class="brand-title">Forecast</h1>
    </div>

    <?php if ($error): ?>
    <div class="alert" role="alert" aria-live="assertive">
        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="M10 6.5v4M10 13.5h.01M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>

    <form method="post">
        <div class="field">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" autocomplete="username" required>
        </div>

        <div class="field">
            <label for="password">Contraseña</label>
            <div class="field-input-wrap">
                <input type="password" id="password" name="password" autocomplete="current-password" data-password-field required>
                <button type="button" class="toggle-password" aria-label="Mostrar contraseña" aria-pressed="false" data-password-toggle>
                    <svg data-icon-show width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M1.5 10S4.5 4 10 4s8.5 6 8.5 6-3 6-8.5 6-8.5-6-8.5-6Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                        <circle cx="10" cy="10" r="2.25" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                    <svg data-icon-hide width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true" hidden>
                        <path d="M2.5 2.5l15 15M8.4 8.5a2.25 2.25 0 0 0 3.1 3.1M6.2 5.1C7.4 4.5 8.7 4 10 4c5.5 0 8.5 6 8.5 6a14.8 14.8 0 0 1-2.6 3.4M11.9 14.6c-.6.2-1.3.4-1.9.4-5.5 0-8.5-6-8.5-6a14.6 14.6 0 0 1 2.9-3.7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-submit">Entrar</button>
    </form>

    <p class="footnote">Acceso interno · Equipo comercial Compulago</p>
</main>

<script>
(function () {
    var toggle = document.querySelector('[data-password-toggle]');
    var input = document.querySelector('[data-password-field]');
    if (!toggle || !input) return;
    var showIcon = toggle.querySelector('[data-icon-show]');
    var hideIcon = toggle.querySelector('[data-icon-hide]');
    toggle.addEventListener('click', function () {
        var isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(isPassword));
        toggle.setAttribute('aria-label', isPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');
        showIcon.hidden = isPassword;
        hideIcon.hidden = !isPassword;
    });
})();
</script>
</body>
</html>
