<?php
require_once __DIR__ . '/config.php';

/**
 * Retorna uma conexão PDO com o banco SQLite, criando o banco
 * e as tabelas na primeira execução (não precisa configurar nada).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dataDir = dirname(DB_PATH);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }

    $isNew = !file_exists(DB_PATH);

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    if ($isNew) {
        bootstrapSchema($pdo);
    } else {
        // garante que as tabelas existam mesmo se o arquivo foi criado vazio
        ensureSchema($pdo);
    }

    return $pdo;
}

function ensureSchema(PDO $pdo): void
{
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('admins', $tables, true) || !in_array('noticias', $tables, true)) {
        bootstrapSchema($pdo);
    }
}

function bootstrapSchema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS noticias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            titulo TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            resumo TEXT NOT NULL,
            conteudo TEXT NOT NULL,
            imagem TEXT,
            status TEXT NOT NULL DEFAULT 'publicado', -- 'publicado' | 'rascunho'
            data_publicacao TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Cria um admin padrão apenas na primeira instalação.
    // Usuário: admin | Senha: gerada aleatoriamente e salva em includes/CREDENCIAIS_INICIAIS.txt
    $stmt = $pdo->query('SELECT COUNT(*) FROM admins');
    if ((int) $stmt->fetchColumn() === 0) {
        $defaultUser = 'admin';
        $defaultPass = bin2hex(random_bytes(5)); // senha aleatória de 10 caracteres

        $hash = password_hash($defaultPass, PASSWORD_DEFAULT);
        $ins = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
        $ins->execute([$defaultUser, $hash]);

        $note = "Credenciais iniciais do painel administrativo\n"
              . "==============================================\n"
              . "URL: /admin\n"
              . "Usuário: {$defaultUser}\n"
              . "Senha:   {$defaultPass}\n\n"
              . "IMPORTANTE: troque a senha assim que possível em Admin > Meu Perfil,\n"
              . "e depois apague este arquivo por segurança.\n";
        file_put_contents(dirname(__DIR__) . '/includes/CREDENCIAIS_INICIAIS.txt', $note);
    }

    // Notícias de exemplo (as duas que já existiam no site estático),
    // só inseridas na primeira instalação.
    $stmt = $pdo->query('SELECT COUNT(*) FROM noticias');
    if ((int) $stmt->fetchColumn() === 0) {
        $seed = $pdo->prepare("
            INSERT INTO noticias (titulo, slug, resumo, conteudo, imagem, status, data_publicacao)
            VALUES (:titulo, :slug, :resumo, :conteudo, :imagem, 'publicado', :data_publicacao)
        ");

        $seed->execute([
            ':titulo' => 'Reunião de acompanhamento em Major Vieira - SC',
            ':slug' => 'reuniao-major-vieira',
            ':resumo' => 'No dia 02/07/2026 a Integral esteve no munícipio de Major Vieira para uma reunião com a prefeitura do município para tratar sobre os processos de regularização.',
            ':conteudo' => 'No dia 02/07/2026 a Integral esteve no munícipio de Major Vieira para uma reunião com a prefeitura do município para tratar sobre os processos de regularização.',
            ':imagem' => 'images/noticia-major-vieira.jpg',
            ':data_publicacao' => '2026-07-02',
        ]);

        $seed->execute([
            ':titulo' => 'REURB iniciada em Benedito Novo - SC',
            ':slug' => 'reurb-benedito-novo',
            ':resumo' => 'A Integral Soluções em Engenharia da início aos trabalhos de Regularização Fundiária em Benedito Novo - SC, saiba como participar.',
            ':conteudo' => 'A Integral Soluções em Engenharia da início aos trabalhos de Regularização Fundiária em Benedito Novo - SC, saiba como participar.',
            ':imagem' => 'images/noticia-benedito-novo.jpg',
            ':data_publicacao' => '2026-06-20',
        ]);
    }
}
