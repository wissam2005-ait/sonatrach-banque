<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $nom      = trim($_POST['nom_complet'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? '';
        $email    = trim($_POST['email'] ?? '');

        if ($nom === '' || $username === '' || strlen($password) < 8) {
            flash('warning', 'Nom, identifiant et mot de passe (8 caractères minimum) sont obligatoires.');
        } elseif (!in_array($role, ['agent', 'responsable'], true)) {
            flash('error', 'Rôle invalide.');
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('warning', 'Adresse e-mail invalide.');
        } else {
            try {
                $pdo->prepare('INSERT INTO utilisateurs (username, password, nom_complet, role, email) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $nom, $role, $email ?: null]);
                flash('success', 'Utilisateur ajouté.');
            } catch (PDOException $e) {
                flash('error', $e->getCode() === '23000' ? 'Cet identifiant existe déjà.' : 'Erreur lors de l\'ajout.');
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$_SESSION['user_id']) {
            flash('warning', 'Vous ne pouvez pas supprimer votre propre compte.');
        } else {
            $pdo->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$id]);
            flash('success', 'Utilisateur supprimé.');
        }
    }
    redirect('admin.php');
}

$users = $pdo->query('SELECT * FROM utilisateurs ORDER BY role, nom_complet')->fetchAll();
$badge = ['admin' => 'b-red', 'agent' => 'b-blue', 'responsable' => 'b-green'];

render_header('Gestion des utilisateurs', 'utilisateurs');
?>
<div class="two-col">
    <div class="card">
        <h2>➕ Nouvel utilisateur</h2>
        <form method="POST" class="form-grid stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div><label>Nom complet</label><input type="text" name="nom_complet" required></div>
            <div><label>Nom d'utilisateur</label><input type="text" name="username" required></div>
            <div><label>Mot de passe (8 caractères min.)</label><input type="password" name="password" minlength="8" required></div>
            <div><label>E-mail</label><input type="email" name="email"></div>
            <div>
                <label>Rôle</label>
                <select name="role" required>
                    <option value="agent">Agent</option>
                    <option value="responsable">Responsable</option>
                </select>
            </div>
            <button class="btn" type="submit">Ajouter</button>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h2>📋 Utilisateurs</h2><span class="badge b-gray"><?= count($users) ?> comptes</span></div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Nom</th><th>Identifiant</th><th>Rôle</th><th>E-mail</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?= e($u['nom_complet']) ?></strong></td>
                    <td><?= e($u['username']) ?></td>
                    <td><span class="badge <?= $badge[$u['role']] ?>"><?= e(ROLE_LABEL[$u['role']]) ?></span></td>
                    <td><?= e($u['email'] ?: '-') ?></td>
                    <td class="num">
                        <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                        <form method="POST" class="inline" onsubmit="return confirm('Supprimer cet utilisateur ?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Supprimer</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php render_footer();
