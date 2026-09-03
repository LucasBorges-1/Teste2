<?php
/**
 * Configurações gerais do site / painel administrativo.
 * Não é necessário editar nada aqui para o funcionamento básico.
 */

// Caminho absoluto da raiz do site
define('BASE_PATH', dirname(__DIR__));

// --- Banco de dados (Turso / libSQL) ---
// A Vercel roda funções serverless com sistema de arquivos somente-leitura,
// então não é possível gravar num arquivo .sqlite local (era isso que
// causava o erro "attempt to write a readonly database"). Agora o banco é
// acessado via HTTP no Turso — configure estas duas variáveis de ambiente
// no painel da Vercel (Project Settings > Environment Variables):
//   TURSO_DATABASE_URL  -> ex: libsql://seu-banco-sua-org.turso.io
//   TURSO_AUTH_TOKEN    -> gerado com `turso db tokens create <nome-do-banco>`
// Veja includes/db.php para os detalhes da conexão.

// Pasta onde as imagens das notícias enviadas pelo painel são salvas
define('UPLOAD_DIR', BASE_PATH . '/uploads/noticias');
define('UPLOAD_URL', 'uploads/noticias'); // caminho relativo usado no <img src="">

// Tamanho máximo de upload de imagem (em bytes) - 5MB
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024);

// Extensões de imagem permitidas
define('UPLOAD_ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'webp']);

// --- Autenticação do painel admin ---
// Em ambiente serverless (Vercel), cada página do admin roda em uma função
// isolada, então sessões PHP tradicionais (baseadas em arquivo local) NÃO
// funcionam entre páginas diferentes. Por isso o login usa um cookie
// assinado (HMAC) e autocontido em vez de $_SESSION — veja admin/includes/auth.php.

// Nome do cookie de autenticação do painel
define('AUTH_COOKIE_NAME', 'integral_admin_auth');

// Tempo de validade do login, em segundos (8 horas)
define('AUTH_TTL', 8 * 60 * 60);

// Chave usada para assinar o cookie de login e os tokens CSRF.
// IMPORTANTE: defina a variável de ambiente ADMIN_AUTH_SECRET no painel da
// Vercel (Project Settings > Environment Variables) com um valor aleatório
// e longo. Se não for definida, um valor padrão é usado apenas para não
// quebrar o site — troque isso antes de ir para produção de verdade.
define('AUTH_SECRET', getenv('ADMIN_AUTH_SECRET') ?: 'integral-troque-este-segredo-antes-de-producao');

date_default_timezone_set('America/Sao_Paulo');
