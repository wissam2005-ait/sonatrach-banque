<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';
require_role('agent');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'add') {
        $date    = $_POST['date_commission'] ?? '';
        $montant = (float)str_replace(',', '.', $_POST['montant'] ?? '0');
        $devise  = $_POST['devise'] ?? '';
        $type    = $_POST['type'] ?? '';
        $actif   = isset($_POST['actif']) ? 1 : 0;
        $dateOk  = DateTime::createFromFormat('Y-m-d', $date) !== false;

        if (!$dateOk || $montant <= 0) {
            flash('warning', 'Date valide et montant positif obligatoires.');
        } elseif (!in_array($devise, ['DZD', 'EUR', 'USD'], true) || !in_array($type, ['Contant', 'Commission'], true)) {
            flash('error', 'Devise ou type invalide.');
        } else {
            $pdo->prepare('INSERT INTO commissions (date_commission, montant, devise, type, actif) VALUES (?, ?, ?, ?, ?)')
                ->execute([$date, $montant, $devise, $type, $actif]);
            flash('success', 'Commission ajoutée.');
        }
    }
    if ($action === 'toggle') {
        $pdo->prepare('UPDATE commissions SET actif = NOT actif WHERE id = ?')->execute([$id]);
        flash('success', 'Statut mis à jour.');
    }
    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM commissions WHERE id = ?')->execute([$id]);
        flash('success', 'Commission supprimée.');
    }
    redirect('commissions.php');
}

$commissions = $pdo->query('SELECT * FROM commissions ORDER BY date_commission DESC, id DESC')->fetchAll();

render_header('Commissions', 'commissions');
?>
<div class="two-col">
    <div class="card">
        <h2>💰 Nouvelle commission</h2>
        <form method="POST" class="form-grid stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div><label>Date *</label><input type="date" name="date_commission" value="<?= date('Y-m-d') ?>" required></div>
            <div><label>Montant *</label><input type="number" name="montant" step="0.01" min="0.01" required></div>
            <div>
                <label>Devise</label>
                <select name="devise">
                    <option value="DZD">DZD — Dinar algérien</option>
                    <option value="EUR">EUR — Euro</option>
                    <option value="USD">USD — Dollar</option>
                </select>
            </div>
            <div>
                <label>Type</label>
                <select name="type"><option>Commission</option><option>Contant</option></select>
            </div>
            <div class="check"><input type="checkbox" id="actif" name="actif" value="1" checked><label for="actif">Active</label></div>
            <button class="btn" type="submit">Ajouter</button>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h2>📋 Liste</h2><span class="badge b-gray"><?= count($commissions) ?> commissions</span></div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th class="num">Montant</th><th>Devise</th><th>Type</th><th>Statut</th><th class="num">Actions</th></tr></thead>
            <tbody>
            <?php if (!$commissions): ?>
                <tr><td colspan="6" class="empty">Aucune commission enregistrée</td></tr>
            <?php endif; ?>
            <?php foreach ($commissions as $c): ?>
                <tr>
                    <td><?= fdate($c['date_commission']) ?></td>
                    <td class="num"><strong><?= number_format((float)$c['montant'], 2, ',', ' ') ?></strong></td>
                    <td><?= e($c['devise']) ?></td>
                    <td><?= e($c['type']) ?></td>
                    <td><span class="badge <?= $c['actif'] ? 'b-green' : 'b-red' ?>"><?= $c['actif'] ? 'Active' : 'Inactive' ?></span></td>
                    <td class="num">
                        <form method="POST" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button class="btn btn-secondary btn-sm" name="action" value="toggle"><?= $c['actif'] ? 'Désactiver' : 'Activer' ?></button>
                        </form>
                        <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette commission ?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
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
