<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';
require_role('agent');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'add') {
        $code    = strtoupper(trim($_POST['code'] ?? ''));
        $libelle = trim($_POST['libelle'] ?? '');
        $famille = $_POST['famille'] ?? '';
        $etat    = isset($_POST['etat']) ? 1 : 0;

        if ($code === '' || $libelle === '') {
            flash('warning', 'Le code et le libellé sont obligatoires.');
        } elseif (!in_array($famille, ['Exploitation', 'Investissement'], true)) {
            flash('error', 'Famille invalide.');
        } else {
            try {
                $pdo->prepare('INSERT INTO ventilations (code, libelle, famille, etat) VALUES (?, ?, ?, ?)')
                    ->execute([$code, $libelle, $famille, $etat]);
                flash('success', 'Ventilation ajoutée.');
            } catch (PDOException $e) {
                flash('warning', $e->getCode() === '23000' ? 'Ce code existe déjà.' : 'Erreur lors de l\'ajout.');
            }
        }
    }
    if ($action === 'toggle') {
        $pdo->prepare('UPDATE ventilations SET etat = NOT etat WHERE id = ?')->execute([$id]);
        flash('success', 'Statut mis à jour.');
    }
    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM ventilations WHERE id = ?')->execute([$id]);
        flash('success', 'Ventilation supprimée.');
    }
    redirect('ventilations.php');
}

$ventilations = $pdo->query(
    'SELECT v.*, (SELECT COUNT(*) FROM movements m WHERE m.id_ventilation = v.id) AS nb_mouvements
     FROM ventilations v ORDER BY v.code'
)->fetchAll();

render_header('Ventilations', 'ventilations');
?>
<div class="two-col">
    <div class="card">
        <h2>🏷️ Nouvelle ventilation</h2>
        <form method="POST" class="form-grid stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div><label>Code *</label><input type="text" name="code" placeholder="V004" maxlength="20" required></div>
            <div><label>Libellé *</label><input type="text" name="libelle" placeholder="Frais de transport" required></div>
            <div>
                <label>Famille</label>
                <select name="famille">
                    <option>Exploitation</option>
                    <option>Investissement</option>
                </select>
            </div>
            <div class="check"><input type="checkbox" id="etat" name="etat" value="1" checked><label for="etat">Active</label></div>
            <button class="btn" type="submit">Ajouter</button>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h2>📋 Liste</h2><span class="badge b-gray"><?= count($ventilations) ?> ventilations</span></div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Code</th><th>Libellé</th><th>Famille</th><th>État</th><th class="num">Utilisée</th><th class="num">Actions</th></tr></thead>
            <tbody>
            <?php if (!$ventilations): ?>
                <tr><td colspan="6" class="empty">Aucune ventilation enregistrée</td></tr>
            <?php endif; ?>
            <?php foreach ($ventilations as $v): ?>
                <tr>
                    <td><strong><?= e($v['code']) ?></strong></td>
                    <td><?= e($v['libelle']) ?></td>
                    <td><span class="badge <?= $v['famille'] === 'Exploitation' ? 'b-blue' : 'b-amber' ?>"><?= e($v['famille']) ?></span></td>
                    <td><span class="badge <?= $v['etat'] ? 'b-green' : 'b-red' ?>"><?= $v['etat'] ? 'Active' : 'Inactive' ?></span></td>
                    <td class="num"><?= (int)$v['nb_mouvements'] ?> mvt</td>
                    <td class="num">
                        <form method="POST" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                            <button class="btn btn-secondary btn-sm" name="action" value="toggle"><?= $v['etat'] ? 'Désactiver' : 'Activer' ?></button>
                        </form>
                        <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette ventilation ?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                            <button class="btn btn-danger btn-sm" name="action" value="delete">✕</button>
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
