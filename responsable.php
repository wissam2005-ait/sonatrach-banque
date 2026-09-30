<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';
require_role('responsable');

$respId = (int)$_SESSION['user_id'];

/* ---------- Actions (POST + CSRF) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'generer') {
        $d1 = $_POST['date_debut'] ?? '';
        $d2 = $_POST['date_fin'] ?? '';
        $ok = DateTime::createFromFormat('Y-m-d', $d1) && DateTime::createFromFormat('Y-m-d', $d2);

        if (!$ok || $d1 > $d2) {
            flash('warning', 'Période invalide : la date de début doit précéder la date de fin.');
        } else {
            $st = $pdo->prepare(
                "SELECT COALESCE(SUM(CASE WHEN type='Recette' THEN montant END),0) AS rec,
                        COALESCE(SUM(CASE WHEN type='Depense' THEN montant END),0) AS dep,
                        COUNT(*) AS n
                 FROM movements WHERE date_versement BETWEEN ? AND ?"
            );
            $st->execute([$d1, $d2]);
            $t = $st->fetch();

            if ((int)$t['n'] === 0) {
                flash('warning', 'Aucun mouvement sur cette période : brouillard non généré.');
            } else {
                $pdo->prepare(
                    "INSERT INTO brouillards (id_responsable, date_debut, date_fin, total_recette, total_depense, solde_final)
                     VALUES (?, ?, ?, ?, ?, ?)"
                )->execute([$respId, $d1, $d2, $t['rec'], $t['dep'], $t['rec'] - $t['dep']]);
                flash('success', 'Brouillard généré (' . (int)$t['n'] . ' mouvements).');
            }
        }
    }

    if ($action === 'approuver' || $action === 'rejeter') {
        $id = (int)($_POST['id'] ?? 0);
        $etat = $action === 'approuver' ? 'Approuve' : 'Rejete';
        $st = $pdo->prepare("UPDATE brouillards SET etat = ? WHERE id = ? AND etat = 'En attente'");
        $st->execute([$etat, $id]);

        if ($st->rowCount() && $etat === 'Approuve') {
            // Un brouillard approuvé verrouille les mouvements de sa période
            $pdo->prepare(
                "UPDATE movements SET etat = 'Validé'
                 WHERE date_versement BETWEEN (SELECT date_debut FROM brouillards WHERE id = ?)
                                          AND (SELECT date_fin   FROM brouillards WHERE id = ?)"
            )->execute([$id, $id]);
        }
        flash($etat === 'Approuve' ? 'success' : 'warning', $etat === 'Approuve' ? 'Brouillard approuvé — mouvements validés.' : 'Brouillard rejeté.');
    }
    redirect('responsable.php');
}

$brouillards = $pdo->query('SELECT * FROM brouillards ORDER BY id DESC')->fetchAll();
$nbMvt = (int)$pdo->query('SELECT COUNT(*) FROM movements')->fetchColumn();
$nbAttente = (int)$pdo->query("SELECT COUNT(*) FROM brouillards WHERE etat = 'En attente'")->fetchColumn();

$etatBadge = ['En attente' => ['b-amber', 'En attente'], 'Approuve' => ['b-green', 'Approuvé'], 'Rejete' => ['b-red', 'Rejeté']];

render_header('Gestion des brouillards', 'brouillards');
?>
<div class="stats">
    <div class="stat blue"><span>Mouvements enregistrés</span><strong><?= $nbMvt ?></strong></div>
    <div class="stat orange"><span>Brouillards en attente</span><strong><?= $nbAttente ?></strong></div>
    <div class="stat"><span>Brouillards générés</span><strong><?= count($brouillards) ?></strong></div>
</div>

<div class="card">
    <h2>📊 Générer un brouillard de banque</h2>
    <form method="POST" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="generer">
        <div><label>Du</label><input type="date" name="date_debut" value="<?= date('Y-m-01') ?>" required></div>
        <div><label>Au</label><input type="date" name="date_fin" value="<?= date('Y-m-d') ?>" required></div>
        <button class="btn" type="submit">➕ Générer</button>
    </form>
</div>

<div class="card">
    <h2>📋 Brouillards générés</h2>
    <div class="table-wrap">
    <table>
        <thead><tr><th>N°</th><th>Période</th><th class="num">Recettes</th><th class="num">Dépenses</th><th class="num">Solde</th><th>État</th><th class="num">Actions</th></tr></thead>
        <tbody>
        <?php if (!$brouillards): ?>
            <tr><td colspan="7" class="empty">Aucun brouillard généré</td></tr>
        <?php endif; ?>
        <?php foreach ($brouillards as $b): [$cls, $lbl] = $etatBadge[$b['etat']]; ?>
            <tr>
                <td>#<?= (int)$b['id'] ?></td>
                <td><?= fdate($b['date_debut']) ?> → <?= fdate($b['date_fin']) ?></td>
                <td class="num pos"><?= money($b['total_recette']) ?></td>
                <td class="num neg"><?= money($b['total_depense']) ?></td>
                <td class="num"><strong><?= money($b['solde_final']) ?></strong></td>
                <td><span class="badge <?= $cls ?>"><?= $lbl ?></span></td>
                <td class="num">
                    <a class="btn btn-secondary btn-sm" href="brouillard.php?id=<?= (int)$b['id'] ?>" target="_blank">👁️ Voir / PDF</a>
                    <?php if ($b['etat'] === 'En attente'): ?>
                    <form method="POST" class="inline" onsubmit="return confirm('Approuver ce brouillard ? Les mouvements de la période seront verrouillés.')">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                        <button class="btn btn-success btn-sm" name="action" value="approuver">✅ Approuver</button>
                    </form>
                    <form method="POST" class="inline" onsubmit="return confirm('Rejeter ce brouillard ?')">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                        <button class="btn btn-danger btn-sm" name="action" value="rejeter">❌</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php render_footer();
