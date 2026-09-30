<?php
/**
 * Gabarit commun : en-tête + barre latérale (selon le rôle) et pied de page.
 */
function render_header(string $title, string $active = ''): void
{
    $role = $_SESSION['role'] ?? '';

    $menus = [
        'agent' => [
            ['agent.php',         'mouvements',    '📝', 'Mouvements'],
            ['beneficiaires.php', 'beneficiaires', '👤', 'Bénéficiaires'],
            ['ventilations.php',  'ventilations',  '🏷️', 'Ventilations'],
            ['commissions.php',   'commissions',   '💰', 'Commissions'],
        ],
        'responsable' => [
            ['responsable.php', 'brouillards', '📊', 'Brouillards'],
        ],
        'admin' => [
            ['admin.php', 'utilisateurs', '🛡️', 'Utilisateurs'],
        ],
    ];
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · Sonatrach Finance</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand">
            <span class="brand-logo">S</span>
            <div>
                <strong>SONATRACH</strong>
                <small>Direction Finance · Béjaïa</small>
            </div>
        </div>
        <nav>
            <?php foreach ($menus[$role] ?? [] as [$href, $key, $icon, $label]): ?>
                <a href="<?= $href ?>" class="<?= $active === $key ? 'active' : '' ?>">
                    <span><?= $icon ?></span> <?= $label ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-user">
            <div class="avatar"><?= e(mb_strtoupper(mb_substr($_SESSION['nom_complet'] ?? '?', 0, 1))) ?></div>
            <div>
                <strong><?= e($_SESSION['nom_complet'] ?? '') ?></strong>
                <small><?= e(ROLE_LABEL[$role] ?? '') ?></small>
            </div>
            <a href="logout.php" class="logout" title="Se déconnecter">⏻</a>
        </div>
    </aside>

    <main class="content">
        <div class="page-head">
            <h1><?= e($title) ?></h1>
        </div>
        <?php foreach (pull_flashes() as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
        <?php endforeach; ?>
<?php
}

function render_footer(): void
{
    ?>
    </main>
</div>
</body>
</html>
<?php
}
