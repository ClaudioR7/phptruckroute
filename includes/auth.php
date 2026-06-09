<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function exigir_login(): void {
    if (empty($_SESSION['usuario_id'])) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            json_response(['erro' => 'Não autenticado', 'redirect' => APP_URL . '/index.php'], 401);
        }
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

function exigir_admin(): void {
    exigir_login();
    if (($_SESSION['perfil'] ?? '') !== 'admin') {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            json_response(['erro' => 'Acesso negado'], 403);
        }
        header('Location: ' . APP_URL . '/pages/dashboard.php');
        exit;
    }
}

function usuario_atual(): array {
    return [
        'id'     => $_SESSION['usuario_id'] ?? 0,
        'nome'   => $_SESSION['nome']        ?? '',
        'perfil' => $_SESSION['perfil']      ?? '',
        'email'  => $_SESSION['email']       ?? '',
    ];
}

function is_admin(): bool {
    return ($_SESSION['perfil'] ?? '') === 'admin';
}

/** Verifica se a viagem pertence ao usuário ou se é admin */
function pode_ver_viagem(int $viagem_id): bool {
    if (is_admin()) return true;
    $pdo = get_db();
    $s = $pdo->prepare("SELECT id FROM viagens WHERE id=? AND motorista_id=?");
    $s->execute([$viagem_id, $_SESSION['usuario_id']]);
    return (bool) $s->fetch();
}
