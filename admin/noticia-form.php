<?php
require_once __DIR__ . '/../includes/noticias.php';
require_once __DIR__ . '/includes/auth.php';

$admin = exigirLogin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : null);
$noticiaExistente = $id ? buscarNoticiaPorId($id) : null;

if ($id && !$noticiaExistente) {
    header('Location: /admin/index.php');
    exit;
}

$ehEdicao = $noticiaExistente !== null;

$erro = '';
$valores = [
    'titulo' => $noticiaExistente['titulo'] ?? '',
    'resumo' => $noticiaExistente['resumo'] ?? '',
    'conteudo' => $noticiaExistente['conteudo'] ?? '',
    'status' => $noticiaExistente['status'] ?? 'publicado',
    'data_publicacao' => $noticiaExistente['data_publicacao'] ?? date('Y-m-d'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada, atualize a página e tente novamente.';
    } else {
        $valores['titulo'] = trim($_POST['titulo'] ?? '');
        $valores['resumo'] = trim($_POST['resumo'] ?? '');
        $valores['conteudo'] = trim($_POST['conteudo'] ?? '');
        $valores['status'] = ($_POST['status'] ?? 'publicado') === 'rascunho' ? 'rascunho' : 'publicado';
        $valores['data_publicacao'] = $_POST['data_publicacao'] ?? date('Y-m-d');

        if ($valores['titulo'] === '' || $valores['resumo'] === '' || $valores['conteudo'] === '') {
            $erro = 'Preencha título, resumo e conteúdo.';
        } elseif (!strtotime($valores['data_publicacao'])) {
            $erro = 'Data de publicação inválida.';
        }

        $caminhoImagem = null;
        if (!$erro) {
            try {
                $caminhoImagem = processarUploadImagem($_FILES['imagem'] ?? []);
            } catch (RuntimeException $ex) {
                $erro = $ex->getMessage();
            }
        }

        if (!$erro) {
            $dados = [
                'titulo' => $valores['titulo'],
                'resumo' => $valores['resumo'],
                'conteudo' => $valores['conteudo'],
                'status' => $valores['status'],
                'data_publicacao' => date('Y-m-d', strtotime($valores['data_publicacao'])),
            ];
            if ($caminhoImagem) {
                $dados['imagem'] = $caminhoImagem;
            }

            if ($ehEdicao) {
                atualizarNoticia($id, $dados);
                header('Location: /admin/index.php?ok=atualizada');
            } else {
                criarNoticia($dados);
                header('Location: /admin/index.php?ok=criada');
            }
            exit;
        }
    }
}

$pageTitle = $ehEdicao ? 'Editar notícia' : 'Nova notícia';
$activePage = $ehEdicao ? 'noticias' : 'nova';
require __DIR__ . '/includes/layout_header.php';
?>

<div class="topbar">
  <div>
    <h1><?= $ehEdicao ? 'Editar notícia' : 'Nova notícia' ?></h1>
    <p><?= $ehEdicao ? 'Altere as informações e salve para atualizar o site.' : 'Preencha os campos abaixo para publicar uma nova notícia.' ?></p>
  </div>
  <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<?php if ($erro): ?>
  <div class="alert alert-error"><?= e($erro) ?></div>
<?php endif; ?>

<div class="panel">
  <div class="panel-body">
    <form method="post" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <?php if ($ehEdicao): ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
      <?php endif; ?>

      <div class="form-grid">
        <div class="field full">
          <label for="titulo">Título</label>
          <input type="text" id="titulo" name="titulo" required value="<?= e($valores['titulo']) ?>" placeholder="Ex.: REURB iniciada em Benedito Novo - SC">
        </div>

        <div class="field full">
          <label for="resumo">Resumo (aparece na listagem de notícias)</label>
          <textarea id="resumo" name="resumo" rows="3" required placeholder="Um resumo curto de 1 a 2 frases."><?= e($valores['resumo']) ?></textarea>
        </div>

        <div class="field full">
          <label for="conteudo">Conteúdo completo (aparece na página da notícia)</label>
          <textarea id="conteudo" name="conteudo" rows="8" required placeholder="Texto completo da notícia."><?= e($valores['conteudo']) ?></textarea>
        </div>

        <div class="field">
          <label for="data_publicacao">Data de publicação</label>
          <input type="date" id="data_publicacao" name="data_publicacao" required value="<?= e($valores['data_publicacao']) ?>">
        </div>

        <div class="field">
          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="publicado" <?= $valores['status'] === 'publicado' ? 'selected' : '' ?>>Publicado (visível no site)</option>
            <option value="rascunho" <?= $valores['status'] === 'rascunho' ? 'selected' : '' ?>>Rascunho (oculto do site)</option>
          </select>
        </div>

        <div class="field full">
          <label for="imagem">Imagem da notícia</label>
          <?php if ($ehEdicao && !empty($noticiaExistente['imagem'])): ?>
            <div class="current-image">
              <img src="../<?= e($noticiaExistente['imagem']) ?>" alt="">
              <span class="help-text">Imagem atual. Envie um novo arquivo abaixo somente se quiser substituí-la.</span>
            </div>
          <?php endif; ?>
          <input type="file" id="imagem" name="imagem" accept=".jpg,.jpeg,.png,.webp">
          <p class="help-text">Formatos aceitos: JPG, PNG ou WEBP. Tamanho máximo: 5MB.</p>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $ehEdicao ? 'Salvar alterações' : 'Publicar notícia' ?></button>
        <a href="index.php" class="btn btn-secondary">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
