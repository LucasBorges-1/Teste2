<?php
/** @var array $admin */
/** @var string $activePage */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' | ' : '' ?>Painel Administrativo | Integral</title>
<link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<div class="admin-shell">
  <aside class="sidebar">
    <div class="brand">
      <img src="../images/logo-integral-white.png" alt="Integral">
      <span>INTEGRAL</span>
    </div>
    <nav>
      <a href="index.php" class="<?= $activePage === 'noticias' ? 'active' : '' ?>">📰 Notícias</a>
      <a href="noticia-form.php" class="<?= $activePage === 'nova' ? 'active' : '' ?>">➕ Nova notícia</a>
      <a href="perfil.php" class="<?= $activePage === 'perfil' ? 'active' : '' ?>">👤 Meu perfil</a>
      <a href="../noticias.php" target="_blank">🔗 Ver site</a>
    </nav>
    <div class="user-box">
      <div class="name">Olá, <?= e($admin['username']) ?></div>
      <a href="logout.php">Sair do painel</a>
    </div>
  </aside>
  <main class="main">
