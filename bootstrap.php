<?php
declare(strict_types=1);

/**
 * Bootstrap : configuration, session sécurisée, connexion PDO et fonctions utilitaires.
 */
$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    $configFile = __DIR__ . '/config.example.php';
}
$config = require $configFile;

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Connexion à la base de données impossible. Vérifiez config.php et l\'import de schema.sql.');
}

/* ---------- Helpers ---------- */

/** Échappe une valeur pour l'affichage HTML (protection XSS). */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Formate un montant en dinars algériens : 12 345,50 DA */
function money($value): string
{
    return number_format((float)$value, 2, ',', ' ') . ' DA';
}

function fdate(?string $date): string
{
    return $date ? date('d/m/Y', strtotime($date)) : '-';
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)$sent)) {
        http_response_code(419);
        exit('Session expirée ou requête invalide. Rechargez la page et réessayez.');
    }
}

/* ---------- Authentification ---------- */

const ROLE_HOME = [
    'admin'       => 'admin.php',
    'agent'       => 'agent.php',
    'responsable' => 'responsable.php',
];

const ROLE_LABEL = [
    'admin'       => 'Administrateur',
    'agent'       => 'Agent financier',
    'responsable' => 'Responsable finance',
];

function require_role(string $role): void
{
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== $role) {
        redirect('index.php');
    }
}
