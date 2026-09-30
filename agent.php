<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';
require_role('agent');

$agentId = (int)$_SESSION['user_id'];

/* ---------- Actions (POST + CSRF) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $date     = $_POST['date_versement'] ?? '';
        $idBenef  = (int)($_POST['id_beneficiaire'] ?? 0);
        $nature   = trim($_POST['nature'] ?? '');
        $ref      = trim($_POST['reference'] ?? '');
        $type     = $_POST['type'] ?? '';
        $mode     = $_POST['mode_paiement'] ?? '';
        $montant  = (float)str_replace(',', '.', $_POST['montant'] ?? '0');
        $idCommis = (int)($_POST['id_commission'] ?? 0);
        $idVentil = (int)($_POST['id_ventilation'] ?? 0);

        if (DateTime::createFromFormat('Y-m-d', $date) === false || $idBenef === 0 || $nature === '' || $ref === '' || $montant <= 0) {
            flash('warning', 'Date, bénéficiaire, nature, référence et un montant positif sont obligatoires.');
        } elseif (!in_array($type, ['Recette', 'Depense'], true) || !in_array($mode, ['Virement', 'Espèces', 'Chèque'], true)) {
            flash('error', 'Type ou mode de paiement invalide.');
        } else {
            // La commission est lue en base (jamais depuis le navigateur)
            $commissionMontant = 0.0;
            if ($idCommis) {
                $c = $pdo->prepare('SELECT montant FROM commissions WHERE id = ? AND actif = 1');
                $c->execute([$idCommis]);
                $row = $c->fetch();
                if ($row) { $commissionMontant = (float)$row['montant']; } else { $idCommis = 0; }
            }
            $pdo->prepare(
                'INSERT INTO movements (id_agent, date_versement, id_beneficiaire, nature, reference, type, mode_paiement,
                                        montant, commission, id_commission, id_ventilation)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$agentId, $date, $idBenef, $nature, $ref, $type, $mode, $montant,
                        $commissionMontant, $idCommis ?: null, $idVentil ?: null]);
            flash('success', 'Mouvement enregistré.');
        }
    }

    if ($action === 'delete') {
        $st = $pdo->prepare("DELETE FROM movements WHERE id = ? AND id_agent = ? AND etat = 'En cours'");
        $st->execute([(int)($_POST['id'] ?? 0), $agentId]);
        flash($st->rowCount() ? 'success' : 'warning', $st->rowCount() ? 'Mouvement supprimé.' : 'Suppression impossible (mouvement déjà validé).');
    }
    redirect('agent.php?' . http_build_query(array_filter(['mois' => $_POST['f_mois'] ?? '', 'annee' => $_POST['f_annee'] ?? ''])));
}

/* ---------- Filtres ---------- */
$mois  = (int)($_GET['mois'] ?? 0);
$annee = (int)($_GET['annee'] ?? date('Y'));
$mois_fr = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

$where  = 'WHERE m.id_agent = :agent AND YEAR(m.date_versement) = :annee';
$params = ['agent' => $agentId, 'annee' => $annee];
if ($mois >= 1 && $mois <= 12) {
    $where .= ' AND MONTH(m.date_versement) = :mois';
    $params['mois'] = $mois;
}

$stmt = $pdo->prepare(
    "SELECT m.*, b.nom, b.prenom, b.numero_compte, v.libelle AS ventilation
     FROM movements m
     LEFT JOIN beneficiaires b ON b.id_beneficiaire = m.id_beneficiaire
     LEFT JOIN ventilations v  ON v.id = m.id_ventilation
     $where ORDER BY m.date_versement DESC, m.id DESC"
);
$stmt->execute($params);
$mouvements = $stmt->fetchAll();

$recettes = $depenses = $commissions = 0.0;
foreach ($mouvements as $m) {
    if ($m['type'] === 'Recette') { $recettes += $m['montant']; } else { $depenses += $m['montant']; }
    $commissions += $m['commission'];
}
$solde = $recettes - $depenses;

$beneficiaires = $pdo->query('SELECT id_beneficiaire, nom, prenom, numero_compte FROM beneficiaires ORDER BY nom, prenom')->fetchAll();
$ventilations  = $pdo->query('SELECT id, code, libelle FROM ventilations WHERE etat = 1 ORDER BY code')->fetchAll();
$commissionsActives = $pdo->query('SELECT id, montant, devise, type FROM commissions WHERE actif = 1 ORDER BY date_commission DESC')->fetchAll();

render_header('Gestion des règlements', 'mouvements');
?>
<div class="stats">
    <div class="stat green"><span>Recettes</span><strong><?= money($recettes) ?></strong></div>
    <div class="stat red"><span>Dépenses</span><strong><?= money($depenses) ?></strong></div>
    <div class="stat <?= $solde >= 0 ? 'blue' : 'red' ?>"><span>Solde de la période</span><strong><?= money($solde) ?></strong></div>
    <div class="stat orange"><span>Commissions</span><strong><?= money($commissions) ?></strong></div>
</div>

<div class="card">
    <h2>📝 Saisir un mouvement</h2>
    <?php if (!$beneficiaires): ?>
        <div class="alert alert-warning">Ajoutez d'abord un bénéficiaire dans l'onglet « Bénéficiaires ».</div>
    <?php endif; ?>
    <form method="POST" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="f_mois" value="<?= $mois ?: '' ?>">
        <input type="hidden" name="f_annee" value="<?= $annee ?>">

        <div><label>Date *</label><input type="date" name="date_versement" value="<?= date('Y-m-d') ?>" required></div>
        <div>
            <label>Type *</label>
            <select name="type" required>
                <option value="Depense">💸 Dépense</option>
                <option value="Recette">💰 Recette</option>
            </select>
        </div>
        <div>
            <label>Bénéficiaire *</label>
            <select name="id_beneficiaire" required>
                <option value="">— Choisir —</option>
                <?php foreach ($beneficiaires as $b): ?>
                    <option value="<?= (int)$b['id_beneficiaire'] ?>"><?= e($b['nom'] . ' ' . $b['prenom']) ?> (<?= e($b['numero_compte']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label>Nature *</label><input type="text" name="nature" placeholder="Ex : Frais de mission" required></div>
        <div><label>Référence *</label><input type="text" name="reference" placeholder="REF-004" required></div>
        <div>
            <label>Mode de paiement</label>
            <select name="mode_paiement"><option>Virement</option><option>Espèces</option><option>Chèque</option></select>
        </div>
        <div><label>Montant (DA) *</label><input type="number" name="montant" step="0.01" min="0.01" required></div>
        <div>
            <label>Commission</label>
            <select name="id_commission">
                <option value="">— Aucune —</option>
                <?php foreach ($commissionsActives as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= number_format((float)$c['montant'], 2, ',', ' ') ?> <?= e($c['devise']) ?> (<?= e($c['type']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Ventilation</label>
            <select name="id_ventilation">
                <option value="">— Aucune —</option>
                <?php foreach ($ventilations as $v): ?>
                    <option value="<?= (int)$v['id'] ?>"><?= e($v['code'] . ' · ' . $v['libelle']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn" type="submit">➕ Ajouter</button>
    </form>
</div>

<div class="card">
    <div class="card-head">
        <h2>📋 Mouvements — <?= $mois ? e($mois_fr[$mois]) . ' ' : '' ?><?= $annee ?> <span class="badge b-gray"><?= count($mouvements) ?></span></h2>
        <form method="GET" class="toolbar">
            <div>
                <select name="mois">
                    <option value="">Tous les mois</option>
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $mois === $i ? 'selected' : '' ?>><?= $mois_fr[$i] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div><input type="number" name="annee" value="<?= $annee ?>" min="2000" max="2100"></div>
            <button class="btn btn-secondary" type="submit">Filtrer</button>
            <a class="btn btn-secondary" href="agent.php">Réinitialiser</a>
        </form>
    </div>

    <div class="table-wrap">
    <table>
        <thead>
            <tr><th>Date</th><th>Type</th><th>Bénéficiaire</th><th>Nature / Réf.</th><th>Paiement</th>
                <th class="num">Montant</th><th class="num">Commission</th><th>Ventilation</th><th>État</th><th></th></tr>
        </thead>
        <tbody>
        <?php if (!$mouvements): ?>
            <tr><td colspan="10" class="empty">Aucun mouvement sur cette période</td></tr>
        <?php endif; ?>
        <?php foreach ($mouvements as $m): $isRec = $m['type'] === 'Recette'; ?>
            <tr>
                <td><?= fdate($m['date_versement']) ?></td>
                <td><span class="badge <?= $isRec ? 'b-green' : 'b-red' ?>"><?= $isRec ? 'Recette' : 'Dépense' ?></span></td>
                <td>
                    <strong><?= e($m['nom'] ?? 'N/A') ?></strong> <?= e($m['prenom']) ?>
                    <?php if ($m['numero_compte']): ?><span class="sub"><?= e($m['numero_compte']) ?></span><?php endif; ?>
                </td>
                <td><?= e($m['nature']) ?><span class="sub"><?= e($m['reference']) ?></span></td>
                <td><?= e($m['mode_paiement']) ?></td>
                <td class="num <?= $isRec ? 'pos' : 'neg' ?>"><?= $isRec ? '+' : '−' ?> <?= money($m['montant']) ?></td>
                <td class="num"><?= $m['commission'] > 0 ? money($m['commission']) : '-' ?></td>
                <td><?= e($m['ventilation'] ?: '-') ?></td>
                <td><span class="badge <?= $m['etat'] === 'En cours' ? 'b-amber' : 'b-green' ?>"><?= e($m['etat']) ?></span></td>
                <td class="num">
                    <?php if ($m['etat'] === 'En cours'): ?>
                    <form method="POST" class="inline" onsubmit="return confirm('Supprimer ce mouvement ?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                        <input type="hidden" name="f_mois" value="<?= $mois ?: '' ?>">
                        <input type="hidden" name="f_annee" value="<?= $annee ?>">
                        <button class="btn btn-danger btn-sm" type="submit">✕</button>
                    </form>
                    <?php else: ?>🔒<?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php render_footer();
