<?php
require_once __DIR__ . '/../includes/noticias.php';
require_once __DIR__ . '/includes/auth.php';

$admin = exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfValido($_POST['csrf_token'] ?? null)) {
    header('Location: /admin/index.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id) {
    excluirNoticia($id);
}

header('Location: /admin/index.php?ok=excluida');
exit;
