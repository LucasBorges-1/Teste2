<?php
require_once __DIR__ . '/db.php';

// Garante que o banco de dados e o usuário admin padrão existam
// assim que qualquer página (pública ou do painel) for carregada.
db();

function gerarSlug(string $texto): string
{
    $texto = trim($texto);
    // remove acentos
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
    $texto = strtolower($texto);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
    $texto = trim($texto, '-');
    return $texto !== '' ? $texto : 'noticia';
}

function slugDisponivel(string $slug, ?int $ignorarId = null): bool
{
    $pdo = db();
    if ($ignorarId) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM noticias WHERE slug = ? AND id != ?');
        $stmt->execute([$slug, $ignorarId]);
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM noticias WHERE slug = ?');
        $stmt->execute([$slug]);
    }
    return (int) $stmt->fetchColumn() === 0;
}

function gerarSlugUnico(string $titulo, ?int $ignorarId = null): string
{
    $base = gerarSlug($titulo);
    $slug = $base;
    $i = 2;
    while (!slugDisponivel($slug, $ignorarId)) {
        $slug = $base . '-' . $i;
        $i++;
    }
    return $slug;
}

function listarNoticias(bool $apenasPublicadas = false): array
{
    $pdo = db();
    $sql = 'SELECT * FROM noticias';
    if ($apenasPublicadas) {
        $sql .= " WHERE status = 'publicado'";
    }
    $sql .= ' ORDER BY date(data_publicacao) DESC, id DESC';
    return $pdo->query($sql)->fetchAll();
}

function buscarNoticiaPorId(int $id): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM noticias WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function buscarNoticiaPorSlug(string $slug): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM noticias WHERE slug = ?');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function criarNoticia(array $dados): int
{
    $pdo = db();
    $slug = gerarSlugUnico($dados['titulo']);
    $stmt = $pdo->prepare("
        INSERT INTO noticias (titulo, slug, resumo, conteudo, imagem, status, data_publicacao, created_at, updated_at)
        VALUES (:titulo, :slug, :resumo, :conteudo, :imagem, :status, :data_publicacao, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ");
    $stmt->execute([
        ':titulo' => $dados['titulo'],
        ':slug' => $slug,
        ':resumo' => $dados['resumo'],
        ':conteudo' => $dados['conteudo'],
        ':imagem' => $dados['imagem'] ?? null,
        ':status' => $dados['status'],
        ':data_publicacao' => $dados['data_publicacao'],
    ]);
    return (int) $pdo->lastInsertId();
}

function atualizarNoticia(int $id, array $dados): void
{
    $pdo = db();
    $atual = buscarNoticiaPorId($id);
    if (!$atual) {
        throw new RuntimeException('Notícia não encontrada.');
    }

    // Só regenera o slug se o título mudou
    $slug = $atual['slug'];
    if ($dados['titulo'] !== $atual['titulo']) {
        $slug = gerarSlugUnico($dados['titulo'], $id);
    }

    $stmt = $pdo->prepare("
        UPDATE noticias SET
            titulo = :titulo,
            slug = :slug,
            resumo = :resumo,
            conteudo = :conteudo,
            imagem = :imagem,
            status = :status,
            data_publicacao = :data_publicacao,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $stmt->execute([
        ':titulo' => $dados['titulo'],
        ':slug' => $slug,
        ':resumo' => $dados['resumo'],
        ':conteudo' => $dados['conteudo'],
        ':imagem' => $dados['imagem'] ?? $atual['imagem'],
        ':status' => $dados['status'],
        ':data_publicacao' => $dados['data_publicacao'],
        ':id' => $id,
    ]);
}

function excluirNoticia(int $id): void
{
    $pdo = db();

    $noticia = buscarNoticiaPorId($id);
    if ($noticia && !empty($noticia['imagem'])) {
        $caminhoAbsoluto = BASE_PATH . '/' . $noticia['imagem'];
        // só apaga o arquivo se ele estiver dentro da pasta de uploads
        // (não apaga imagens antigas do tema que estejam em /images)
        if (str_starts_with($noticia['imagem'], UPLOAD_URL . '/') && is_file($caminhoAbsoluto)) {
            @unlink($caminhoAbsoluto);
        }
    }

    $stmt = $pdo->prepare('DELETE FROM noticias WHERE id = ?');
    $stmt->execute([$id]);
}

/**
 * Processa o upload de imagem de uma notícia (campo <input type="file" name="imagem">).
 * Retorna o caminho relativo salvo, ou null se nenhum arquivo foi enviado.
 * Lança RuntimeException em caso de erro de validação.
 */
function processarUploadImagem(array $arquivo): ?string
{
    if (!isset($arquivo['error']) || $arquivo['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Erro ao enviar a imagem. Tente novamente.');
    }

    if ($arquivo['size'] > UPLOAD_MAX_SIZE) {
        throw new RuntimeException('A imagem enviada é maior que 5MB.');
    }

    $ext = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, UPLOAD_ALLOWED_EXT, true)) {
        throw new RuntimeException('Formato de imagem não permitido. Use JPG, PNG ou WEBP.');
    }

    // valida que o arquivo é realmente uma imagem
    $info = @getimagesize($arquivo['tmp_name']);
    if ($info === false) {
        throw new RuntimeException('O arquivo enviado não é uma imagem válida.');
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $nomeArquivo = bin2hex(random_bytes(8)) . '.' . $ext;
    $destino = UPLOAD_DIR . '/' . $nomeArquivo;

    if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
        throw new RuntimeException('Não foi possível salvar a imagem no servidor.');
    }

    return UPLOAD_URL . '/' . $nomeArquivo;
}

function e(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

function formatarDataBr(string $data): string
{
    $ts = strtotime($data);
    return $ts ? date('d/m/Y', $ts) : $data;
}
