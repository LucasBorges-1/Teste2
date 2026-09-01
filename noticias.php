<?php
require_once __DIR__ . '/includes/noticias.php';

$listaNoticias = listarNoticias(true); // apenas publicadas

$slugSelecionado = isset($_GET['slug']) ? trim($_GET['slug']) : null;
$noticiaDetalhe = null;

if ($slugSelecionado) {
    $candidata = buscarNoticiaPorSlug($slugSelecionado);
    if ($candidata && $candidata['status'] === 'publicado') {
        $noticiaDetalhe = $candidata;
    }
}

// se nenhuma notícia válida foi pedida via ?slug=, mostra a mais recente como destaque
if (!$noticiaDetalhe && !empty($listaNoticias)) {
    $noticiaDetalhe = $listaNoticias[0];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notícias | Integral Soluções em Engenharia</title>
<meta name="description" content="Integral Soluções em Engenharia - Regularizando terrenos desde 2017. REURB, Estudo Técnico Sócioambiental e Planos Setoriais.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/responsive.css">
<link rel="stylesheet" href="css/animations.css">
<link rel="stylesheet" href="css/redesign.css">
</head>
<body>
<div class="bg-lines">
  <svg class="top-left" viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg" fill="none">
    <path d="M0 200 C 100 50, 300 50, 400 200" stroke="white" stroke-width="1.5"/>
    <path d="M0 260 C 120 110, 300 110, 400 260" stroke="white" stroke-width="1.5"/>
  </svg>
  <svg class="bottom-right" viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg" fill="none">
    <path d="M0 200 C 100 350, 300 350, 400 200" stroke="white" stroke-width="1.5"/>
    <path d="M0 140 C 120 290, 300 290, 400 140" stroke="white" stroke-width="1.5"/>
  </svg>
</div>
<header class="site-header">
  <div class="container header-inner">
    <a href="index.html" class="logo">
      <img src="images/mcl/logo-icon-white.png" alt="Símbolo Integral" class="logo-mark-small" loading="lazy">
      <span class="logo-text">INTEGRAL</span>
    </a>
    <nav class="main-nav">
      <a href="index.html#home" class="">Home</a>
<a href="index.html#empresa" class="">A Empresa</a>
<a href="index.html#servicos" class="">Serviços</a>
<a href="noticias.php" class="active">Notícias</a>
<a href="gestor-reurb.html" class="">Gestor REURB</a>
<a href="conhecimento.html" class="">Central do Conhecimento</a>

      <a href="contato.html" class="btn-contato">Contato</a>
    </nav>
    <button class="menu-toggle" aria-label="Abrir menu"><span></span><span></span><span></span></button>
  </div>
</header>
<main>

<section class="section container">
  <?php if (empty($listaNoticias)): ?>
    <p style="text-align:center; padding: 40px 0;">Nenhuma notícia publicada no momento.</p>
  <?php else: ?>
    <div class="noticias-list">
      <?php foreach ($listaNoticias as $noticia): ?>
        <a href="noticias.php?slug=<?= e($noticia['slug']) ?>#noticia-detalhe" class="noticia-item reveal">
          <img src="<?= e($noticia['imagem'] ?: 'images/noticia-major-vieira.jpg') ?>" alt="<?= e($noticia['titulo']) ?>" loading="lazy">
          <div>
            <h3><?= nl2br(e($noticia['titulo'])) ?></h3>
            <p><?= e($noticia['resumo']) ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($noticiaDetalhe): ?>
<section id="noticia-detalhe" class="section container noticia-detail">
  <div class="noticia-detail-image reveal">
    <img src="<?= e($noticiaDetalhe['imagem'] ?: 'images/noticia-major-vieira-full.jpg') ?>" alt="<?= e($noticiaDetalhe['titulo']) ?>" loading="lazy">
  </div>
  <div class="noticia-detail-body reveal">
    <h1><?= nl2br(e($noticiaDetalhe['titulo'])) ?></h1>
    <p><?= nl2br(e($noticiaDetalhe['conteudo'])) ?></p>
    <p style="margin-top:16px; opacity:.75; font-size:14px;">Publicado em <?= e(formatarDataBr($noticiaDetalhe['data_publicacao'])) ?></p>
  </div>
</section>
<?php endif; ?>

</main>
<footer class="site-footer">
  <div class="container">
    <div class="footer-social">
      <a href="https://instagram.com" target="_blank" rel="noopener" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg></a>
      <a href="https://facebook.com" target="_blank" rel="noopener" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h-2a4 4 0 0 0-4 4v3H6v4h3v7h4v-7h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg></a>
    </div>
  </div>
</footer>
<a href="https://wa.me/554733100136" class="whatsapp-float" target="_blank" rel="noopener" aria-label="WhatsApp"><svg viewBox="0 0 32 32" fill="currentColor"><path d="M16 2C8.3 2 2 8.3 2 16c0 2.6.7 5.1 2.1 7.3L2 30l6.9-2c2.1 1.2 4.5 1.8 7.1 1.8 7.7 0 14-6.3 14-14S23.7 2 16 2zm0 25.5c-2.3 0-4.6-.6-6.5-1.8l-.5-.3-4.1 1.2 1.2-4-.3-.5C4.5 20.1 4 18.1 4 16 4 9.4 9.4 4 16 4s12 5.4 12 12-5.4 11.5-12 11.5zm6.4-8.9c-.3-.2-2-1-2.3-1.1-.3-.1-.5-.2-.8.2s-.9 1.1-1.1 1.3c-.2.2-.4.2-.8.1-.3-.2-1.4-.5-2.7-1.7-1-.9-1.7-2-1.9-2.3-.2-.3 0-.5.2-.7.2-.2.3-.4.5-.6.2-.2.2-.4.3-.6.1-.2 0-.5 0-.7-.1-.2-.8-1.9-1-2.6-.3-.7-.6-.6-.8-.6h-.7c-.2 0-.6.1-.9.4-.3.3-1.2 1.1-1.2 2.8s1.2 3.3 1.4 3.5c.2.2 2.4 3.7 5.9 5.1.8.3 1.4.5 1.9.7.8.3 1.5.2 2.1.1.6-.1 2-.8 2.2-1.5.3-.7.3-1.4.2-1.5-.1-.2-.3-.3-.6-.4z"/></svg></a>
<button class="back-to-top" aria-label="Voltar ao topo">&uarr;</button>
<script src="js/menu.js"></script>
<script src="js/animations.js"></script>
<script src="js/main.js"></script>

</body>
</html>
