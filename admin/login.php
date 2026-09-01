<?php
require_once __DIR__ . '/../includes/noticias.php';
require_once __DIR__ . '/includes/auth.php';

if (adminLogado()) {
    header('Location: /admin/index.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada, tente novamente.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if ($username === '' || $senha === '') {
            $erro = 'Preencha usuário e senha.';
        } elseif (tentarLogin($username, $senha)) {
            header('Location: /admin/index.php');
            exit;
        } else {
            $erro = 'Usuário ou senha inválidos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | Painel Administrativo | Integral</title>
<link rel="stylesheet" href="assets/admin.css">
</head>
<body class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <img src="../images/logo-integral-white.png" alt="Integral" style="filter: invert(1) brightness(0.4) sepia(1) hue-rotate(140deg) saturate(2);">
      <span>INTEGRAL</span>
    </div>
    <h1>Painel Administrativo de Notícias</h1>

    <?php if ($erro): ?>
      <div class="alert alert-error"><?= e($erro) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <div class="field">
        <label for="username">Usuário</label>
        <input type="text" id="username" name="username" autocomplete="username" required autofocus>
      </div>
      <div class="field">
        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-full">Entrar</button>
    </form>

    <p class="login-hint">Acesso restrito à equipe da Integral Soluções em Engenharia.</p>
  </div>
</body>
</html>
