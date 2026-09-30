<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';
require_role('agent');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $nom    = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $adr    = trim($_POST['adresse'] ?? '');
        $compte = trim($_POST['numero_compte'] ?? '');

        if ($nom === '' || $prenom === '' || $compte === '') {
            flash('warning', 'Nom, prénom et numéro de compte sont obligatoires.');
        } else {
            $pdo->prepare('INSERT INTO beneficiaires (nom, prenom, adresse, numero_compte) VALUES (?, ?, ?, ?)')
                ->execute([mb_strtoupper($nom), $prenom, $adr ?: null, $compte]);
            flash('success', 'Bénéficiaire ajouté.');
        }
    }

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM beneficiaires WHERE id_beneficiaire = ?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('success', 'Bénéficiaire supprimé (ses mouvements sont conservés).');
    }
    redirect('beneficiaires.php');
}

$q = trim($_GET['q'] ?? '');
$sql = 'SELECT b.*, (SELECT COUNT(*) FROM movements m WHERE m.id_beneficiaire = b.id_beneficiaire) AS nb_mouvements
        FROM beneficiaires b';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE b.nom LIKE ? OR b.prenom LIKE ? OR b.numero_compte LIKE ?';
    $params = ["%$q%", "%$q%", "%$q%"];
}
$stmt = $pdo->prepare($sql . ' ORDER BY b.nom, b.prenom');
$stmt->execute($params);
$beneficiaires = $stmt->fetchAll();

render_header('Bénéficiaires', 'beneficiaires');
?>
<div class="two-col">
    <div class="card">
        <h2>👤 Nouveau bénéficiaire</h2>
        <form method="POST" class="form-grid stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div><label>Nom *</label><input type="text" name="nom" placeholder="BENALI" required></div>
            <div><label>Prénom *</label><input type="text" name="prenom" placeholder="Ahmed" required></div>
            <div><label>Numéro de compte *</label><input type="text" name="numero_compte" placeholder="001-2345678901-12" required></div>
            <div><label>Adresse</label><textarea name="adresse" rows="2" placeholder="12 Rue des Palmiers, Béjaïa"></textarea></div>
            <button class="btn" type="submit">Enregistrer</button>
        </form>
    </div>

    <div class="card">
        <div class="card-head">
            <h2>📋 Liste (<?= count($beneficiaires) ?>)</h2>
            <form method="GET" class="toolbar">
                <input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher nom, compte…">
                <button class="btn btn-secondary btn-sm" type="submit">🔍</button>
            </form>
        </div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Bénéficiaire</th><th>Compte</th><th>Adresse</th><th class="num">Mouvements</th><th></th></tr></thead>
            <tbody>
            <?php if (!$beneficiaires): ?>
                <tr><td colspan="5" class="empty">Aucun bénéficiaire trouvé</td></tr>
            <?php endif; ?>
            <?php foreach ($beneficiaires as $b): ?>
                <tr>
                    <td><strong><?= e($b['nom']) ?></strong> <?= e($b['prenom']) ?></td>
                    <td><?= e($b['numero_compte']) ?></td>
                    <td><?= e($b['adresse'] ?: '-') ?></td>
                    <td class="num"><span class="badge b-gray"><?= (int)$b['nb_mouvements'] ?></span></td>
                    <td class="num">
                        <form method="POST" class="inline" onsubmit="return confirm('Supprimer ce bénéficiaire ?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$b['id_beneficiaire'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Supprimer</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php render_footer();
