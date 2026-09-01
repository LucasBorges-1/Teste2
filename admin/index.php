<?php
require_once __DIR__ . '/../includes/noticias.php';
require_once __DIR__ . '/includes/auth.php';

$admin = exigirLogin();

$mensagem = '';
if (isset($_GET['ok']) && $_GET['ok'] === 'criada') {
    $mensagem = 'Notícia publicada com sucesso!';
} elseif (isset($_GET['ok']) && $_GET['ok'] === 'atualizada') {
    $mensagem = 'Notícia atualizada com sucesso!';
} elseif (isset($_GET['ok']) && $_GET['ok'] === 'excluida') {
    $mensagem = 'Notícia excluída com sucesso!';
}

$todasNoticias = listarNoticias();
$totalPublicadas = count(array_filter($todasNoticias, fn($n) => $n['status'] === 'publicado'));
$totalRascunhos = count($todasNoticias) - $totalPublicadas;

$pageTitle = 'Notícias';
$activePage = 'noticias';
require __DIR__ . '/includes/layout_header.php';
?>

<div class="topbar">
  <div>
    <h1>Notícias</h1>
    <p>Gerencie as notícias que aparecem na página pública do site.</p>
  </div>
  <a href="noticia-form.php" class="btn btn-primary">➕ Nova notícia</a>
</div>

<?php if ($mensagem): ?>
  <div class="alert alert-success"><?= e($mensagem) ?></div>
<?php endif; ?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="value"><?= count($todasNoticias) ?></div>
    <div class="label">Total de notícias</div>
  </div>
  <div class="stat-card">
    <div class="value"><?= $totalPublicadas ?></div>
    <div class="label">Publicadas no site</div>
  </div>
  <div class="stat-card">
    <div class="value"><?= $totalRascunhos ?></div>
    <div class="label">Rascunhos (não visíveis)</div>
  </div>
</div>

<div class="panel">
  <div class="panel-header">
    <h2>Todas as notícias</h2>
  </div>

  <?php if (empty($todasNoticias)): ?>
    <div class="empty-state">
      <p>Nenhuma notícia cadastrada ainda.</p>
      <p style="margin-top:14px;"><a href="noticia-form.php" class="btn btn-primary">Criar a primeira notícia</a></p>
    </div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th></th>
          <th>Título</th>
          <th>Data</th>
          <th>Status</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($todasNoticias as $n): ?>
          <tr>
            <td>
              <?php if (!empty($n['imagem'])): ?>
                <img class="thumb" src="../<?= e($n['imagem']) ?>" alt="">
              <?php else: ?>
                <div class="thumb"></div>
              <?php endif; ?>
            </td>
            <td>
              <strong><?= e($n['titulo']) ?></strong><br>
              <span style="color:var(--color-text-soft); font-size:12.5px;">/noticias.php?slug=<?= e($n['slug']) ?></span>
            </td>
            <td><?= e(formatarDataBr($n['data_publicacao'])) ?></td>
            <td>
              <span class="badge badge-<?= e($n['status']) ?>">
                <?= $n['status'] === 'publicado' ? 'Publicado' : 'Rascunho' ?>
              </span>
            </td>
            <td>
              <div class="row-actions">
                <a href="noticia-form.php?id=<?= (int) $n['id'] ?>" class="btn btn-secondary">Editar</a>
                <form method="post" action="noticia-delete.php" onsubmit="return confirm('Tem certeza que deseja excluir a notícia \'<?= e(addslashes($n['titulo'])) ?>\'? Essa ação não pode ser desfeita.');" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                  <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                  <button type="submit" class="btn btn-danger">Excluir</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
