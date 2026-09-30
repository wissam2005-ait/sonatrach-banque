<?php
require __DIR__ . '/bootstrap.php';

// Déjà connecté : direction son espace
if (!empty($_SESSION['user_id']) && isset(ROLE_HOME[$_SESSION['role'] ?? ''])) {
    redirect(ROLE_HOME[$_SESSION['role']]);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);           // évite la fixation de session
        $_SESSION['user_id']     = (int)$user['id'];
        $_SESSION['role']        = $user['role'];
        $_SESSION['nom_complet'] = $user['nom_complet'];
        redirect(ROLE_HOME[$user['role']]);
    }

    usleep(400000);                            // ralentit le brute-force
    $error = 'Identifiants incorrects.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion · Sonatrach Finance</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login">
    <div class="login-card">
        <div class="brand-logo">S</div>
        <h1>SONATRACH</h1>
        <p class="lead">Direction Finance · Béjaïa</p>

        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

        <form method="POST" autocomplete="off">
            <?= csrf_field() ?>
            <div class="field">
                <label for="username">Nom d'utilisateur</label>
                <input id="username" type="text" name="username" required autofocus>
            </div>
            <div class="field">
                <label for="password">Mot de passe</label>
                <input id="password" type="password" name="password" required>
            </div>
            <button class="btn" type="submit">Se connecter</button>
        </form>

    </div>
</body>
</html>
