<?php
require_once __DIR__ . '/../includes/noticias.php';
require_once __DIR__ . '/includes/auth.php';

$admin = exigirLogin();

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada, atualize a página e tente novamente.';
    } else {
        $novoUsername = trim($_POST['username'] ?? '');
        $senhaAtual = $_POST['senha_atual'] ?? '';
        $novaSenha = $_POST['nova_senha'] ?? '';
        $confirmarSenha = $_POST['confirmar_senha'] ?? '';

        $pdo = db();
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE id = ?');
        $stmt->execute([$admin['id']]);
        $dadosAtuais = $stmt->fetch();

        if ($novoUsername === '') {
            $erro = 'O usuário não pode ficar em branco.';
        } elseif (!password_verify($senhaAtual, $dadosAtuais['password_hash'])) {
            $erro = 'Senha atual incorreta.';
        } elseif ($novaSenha !== '' && strlen($novaSenha) < 6) {
            $erro = 'A nova senha deve ter pelo menos 6 caracteres.';
        } elseif ($novaSenha !== '' && $novaSenha !== $confirmarSenha) {
            $erro = 'A confirmação da nova senha não confere.';
        } else {
            // verifica se o novo username já está em uso por outro admin
            $check = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE username = ? AND id != ?');
            $check->execute([$novoUsername, $admin['id']]);
            if ((int) $check->fetchColumn() > 0) {
                $erro = 'Esse nome de usuário já está em uso.';
            } else {
                if ($novaSenha !== '') {
                    $upd = $pdo->prepare('UPDATE admins SET username = ?, password_hash = ? WHERE id = ?');
                    $upd->execute([$novoUsername, password_hash($novaSenha, PASSWORD_DEFAULT), $admin['id']]);
                } else {
                    $upd = $pdo->prepare('UPDATE admins SET username = ? WHERE id = ?');
                    $upd->execute([$novoUsername, $admin['id']]);
                }
                authSetCookie($admin['id'], $novoUsername);
                $admin['username'] = $novoUsername;
                $sucesso = 'Dados atualizados com sucesso!';
            }
        }
    }
}

$pageTitle = 'Meu perfil';
$activePage = 'perfil';
require __DIR__ . '/includes/layout_header.php';
?>

<div class="topbar">
  <div>
    <h1>Meu perfil</h1>
    <p>Altere seu usuário e/ou senha de acesso ao painel.</p>
  </div>
</div>

<?php if ($erro): ?>
  <div class="alert alert-error"><?= e($erro) ?></div>
<?php endif; ?>
<?php if ($sucesso): ?>
  <div class="alert alert-success"><?= e($sucesso) ?></div>
<?php endif; ?>

<div class="panel">
  <div class="panel-body">
    <form method="post" style="max-width:420px;" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

      <div class="field">
        <label for="username">Usuário</label>
        <input type="text" id="username" name="username" required value="<?= e($admin['username']) ?>">
      </div>

      <div class="field">
        <label for="nova_senha">Nova senha (deixe em branco para não alterar)</label>
        <input type="password" id="nova_senha" name="nova_senha" autocomplete="new-password">
      </div>

      <div class="field">
        <label for="confirmar_senha">Confirmar nova senha</label>
        <input type="password" id="confirmar_senha" name="confirmar_senha" autocomplete="new-password">
      </div>

      <div class="field">
        <label for="senha_atual">Senha atual (obrigatória para confirmar as mudanças)</label>
        <input type="password" id="senha_atual" name="senha_atual" autocomplete="current-password" required>
      </div>

      <button type="submit" class="btn btn-primary">Salvar alterações</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
