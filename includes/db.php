<?php
require_once __DIR__ . '/config.php';

/**
 * Cliente HTTP para o Turso (libSQL), com uma interface compatível com o
 * subconjunto de PDO usado neste projeto (prepare/execute/fetch/fetchAll/
 * fetchColumn/query/exec/lastInsertId/setAttribute).
 *
 * Por quê: a Vercel roda funções serverless com sistema de arquivos
 * somente-leitura (só /tmp é gravável, e não persiste entre execuções), então
 * um arquivo .sqlite local não pode ser usado para guardar dados de verdade.
 * O Turso é um SQLite hospedado e acessado por HTTP, então o SQL usado no
 * projeto (schema, queries) continua o mesmo — só a forma de conectar muda.
 *
 * Variáveis de ambiente necessárias (definir na Vercel em
 * Project Settings > Environment Variables):
 *   TURSO_DATABASE_URL  -> ex: libsql://seu-banco-sua-org.turso.io
 *   TURSO_AUTH_TOKEN    -> token gerado com `turso db tokens create <db>`
 */

class LibSqlException extends RuntimeException
{
}

class LibSqlStatement
{
    public const FETCH_COLUMN = 'FETCH_COLUMN';

    private LibSqlConnection $conn;
    private string $sql;
    private array $rows = [];
    private bool $executed = false;
    private int $cursor = 0;
    private int $affectedRows = 0;

    public function __construct(LibSqlConnection $conn, string $sql)
    {
        $this->conn = $conn;
        $this->sql = $sql;
    }

    public function execute(array $params = []): bool
    {
        $result = $this->conn->runStatement($this->sql, $params);
        $this->rows = $result['rows'];
        $this->affectedRows = $result['affected_row_count'];
        $this->cursor = 0;
        $this->executed = true;

        if ($result['last_insert_rowid'] !== null) {
            $this->conn->setLastInsertId($result['last_insert_rowid']);
        }

        return true;
    }

    /** @return array<string,mixed>|false */
    public function fetch()
    {
        if (!$this->executed || !isset($this->rows[$this->cursor])) {
            return false;
        }
        return $this->rows[$this->cursor++];
    }

    public function fetchAll(?string $mode = null): array
    {
        if ($mode === self::FETCH_COLUMN) {
            return array_map(static function (array $row) {
                $vals = array_values($row);
                return $vals[0] ?? null;
            }, $this->rows);
        }
        return $this->rows;
    }

    /** @return mixed|false */
    public function fetchColumn(int $index = 0)
    {
        $row = $this->fetch();
        if ($row === false) {
            return false;
        }
        $vals = array_values($row);
        return $vals[$index] ?? null;
    }

    public function rowCount(): int
    {
        return $this->affectedRows;
    }
}

class LibSqlConnection
{
    private string $pipelineUrl;
    private string $token;
    private ?string $lastInsertId = null;

    public function __construct(string $databaseUrl, string $token)
    {
        $httpUrl = preg_replace('#^libsql://#', 'https://', trim($databaseUrl));
        $this->pipelineUrl = rtrim($httpUrl, '/') . '/v2/pipeline';
        $this->token = $token;
    }

    /** Compatibilidade com o código existente (chamadas a $pdo->setAttribute(...)); não faz nada. */
    public function setAttribute(...$args): bool
    {
        return true;
    }

    public function exec(string $sql): int
    {
        $result = $this->runStatement($sql, []);
        return $result['affected_row_count'];
    }

    public function prepare(string $sql): LibSqlStatement
    {
        return new LibSqlStatement($this, $sql);
    }

    public function query(string $sql): LibSqlStatement
    {
        $stmt = new LibSqlStatement($this, $sql);
        $stmt->execute([]);
        return $stmt;
    }

    public function lastInsertId(): string
    {
        return (string) $this->lastInsertId;
    }

    public function setLastInsertId($id): void
    {
        $this->lastInsertId = (string) $id;
    }

    /**
     * Executa um único statement contra o Turso via HTTP e devolve
     * linhas já convertidas para array associativo (nome da coluna => valor).
     */
    public function runStatement(string $sql, array $params): array
    {
        $stmt = ['sql' => $sql];

        if (!empty($params)) {
            if ($this->isAssoc($params)) {
                $namedArgs = [];
                foreach ($params as $name => $value) {
                    $namedArgs[] = [
                        'name' => ltrim((string) $name, ':@$'),
                        'value' => $this->toArg($value),
                    ];
                }
                $stmt['named_args'] = $namedArgs;
            } else {
                $stmt['args'] = array_map([$this, 'toArg'], array_values($params));
            }
        }

        $payload = json_encode([
            'requests' => [
                ['type' => 'execute', 'stmt' => $stmt],
                ['type' => 'close'],
            ],
        ]);

        $ch = curl_init($this->pipelineUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlErrno) {
            throw new LibSqlException('Erro de conexão com o banco de dados (Turso): ' . $curlError);
        }

        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['results'][0])) {
            throw new LibSqlException('Resposta inválida do banco de dados (Turso): ' . substr((string) $body, 0, 500));
        }

        $execResult = $data['results'][0];
        if (($execResult['type'] ?? null) !== 'ok') {
            $message = $execResult['error']['message'] ?? 'Erro desconhecido ao executar SQL no Turso.';
            throw new LibSqlException('Erro ao executar SQL: ' . $message . ' | SQL: ' . $sql);
        }

        $result = $execResult['response']['result'] ?? ['cols' => [], 'rows' => [], 'affected_row_count' => 0, 'last_insert_rowid' => null];

        $colNames = array_map(static fn(array $c) => $c['name'], $result['cols'] ?? []);
        $rows = [];
        foreach ($result['rows'] ?? [] as $rawRow) {
            $row = [];
            foreach ($rawRow as $i => $cell) {
                $row[$colNames[$i] ?? $i] = $this->fromCell($cell);
            }
            $rows[] = $row;
        }

        return [
            'rows' => $rows,
            'affected_row_count' => (int) ($result['affected_row_count'] ?? 0),
            'last_insert_rowid' => $result['last_insert_rowid'] ?? null,
        ];
    }

    private function isAssoc(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private function toArg($value): array
    {
        if ($value === null) {
            return ['type' => 'null'];
        }
        if (is_bool($value)) {
            return ['type' => 'integer', 'value' => $value ? '1' : '0'];
        }
        if (is_int($value)) {
            return ['type' => 'integer', 'value' => (string) $value];
        }
        if (is_float($value)) {
            return ['type' => 'float', 'value' => (string) $value];
        }
        return ['type' => 'text', 'value' => (string) $value];
    }

    /** @return mixed */
    private function fromCell(array $cell)
    {
        $type = $cell['type'] ?? 'null';
        if ($type === 'null') {
            return null;
        }
        if ($type === 'blob') {
            return base64_decode($cell['base64'] ?? '');
        }
        return $cell['value'] ?? null;
    }
}

/**
 * Retorna uma conexão com o Turso, garantindo que as tabelas existam
 * (criadas automaticamente na primeira execução, como antes).
 */
function db(): LibSqlConnection
{
    static $conn = null;

    if ($conn !== null) {
        return $conn;
    }

    $url = getenv('TURSO_DATABASE_URL');
    $token = getenv('TURSO_AUTH_TOKEN');

    if (!$url || !$token) {
        throw new LibSqlException(
            'Configuração do Turso ausente. Defina as variáveis de ambiente ' .
            'TURSO_DATABASE_URL e TURSO_AUTH_TOKEN no painel da Vercel ' .
            '(Project Settings > Environment Variables) e no seu .env local.'
        );
    }

    $conn = new LibSqlConnection($url, $token);
    ensureSchema($conn);

    return $conn;
}

function ensureSchema(LibSqlConnection $pdo): void
{
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")
        ->fetchAll(LibSqlStatement::FETCH_COLUMN);

    if (!in_array('admins', $tables, true) || !in_array('noticias', $tables, true)) {
        bootstrapSchema($pdo);
    }
}

function bootstrapSchema(LibSqlConnection $pdo): void
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

        // Em ambiente serverless (Vercel) isto é somente-leitura e a escrita será
        // silenciosamente ignorada (ou pode falhar) — nesse caso, veja as
        // credenciais direto na tabela `admins` do Turso, ou rode este mesmo
        // bootstrap localmente (fora da Vercel) para gerar o arquivo.
        @file_put_contents(dirname(__DIR__) . '/includes/CREDENCIAIS_INICIAIS.txt', $note);
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
