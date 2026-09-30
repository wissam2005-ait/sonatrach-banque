<?php
require __DIR__ . '/bootstrap.php';
require_role('responsable');

$st = $pdo->prepare('SELECT * FROM brouillards WHERE id = ?');
$st->execute([(int)($_GET['id'] ?? 0)]);
$b = $st->fetch();
if (!$b) { http_response_code(404); exit('Brouillard introuvable.'); }

$st = $pdo->prepare(
    "SELECT m.*, CONCAT(be.nom, ' ', be.prenom) AS beneficiaire, v.libelle AS ventilation
     FROM movements m
     LEFT JOIN beneficiaires be ON be.id_beneficiaire = m.id_beneficiaire
     LEFT JOIN ventilations v  ON v.id = m.id_ventilation
     WHERE m.date_versement BETWEEN ? AND ? ORDER BY m.date_versement, m.id"
);
$st->execute([$b['date_debut'], $b['date_fin']]);
$mvts = $st->fetchAll();

$etat = ['En attente' => ['b-amber', '⏳ En attente'], 'Approuve' => ['b-green', '✅ Approuvé'], 'Rejete' => ['b-red', '❌ Rejeté']][$b['etat']];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Brouillard #<?= (int)$b['id'] ?> · <?= fdate($b['date_debut']) ?> au <?= fdate($b['date_fin']) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="print-page">
    <div class="print-actions">
        <button class="btn" onclick="window.print()">🖨️ Imprimer / Enregistrer en PDF</button>
        <a class="btn btn-secondary" href="responsable.php">← Retour</a>
    </div>

    <div class="print-head">
        <h1>BROUILLARD DE BANQUE N° <?= (int)$b['id'] ?></h1>
        <p>Sonatrach · Direction Finance · Béjaïa</p>
        <p>Période du <strong><?= fdate($b['date_debut']) ?></strong> au <strong><?= fdate($b['date_fin']) ?></strong></p>
        <p><span class="badge <?= $etat[0] ?>"><?= $etat[1] ?></span></p>
    </div>

    <table>
        <thead><tr><th>Date</th><th>Bénéficiaire</th><th>Nature</th><th>Référence</th><th>Type</th><th>Ventilation</th><th class="num">Montant</th></tr></thead>
        <tbody>
        <?php foreach ($mvts as $m): $rec = $m['type'] === 'Recette'; ?>
            <tr>
                <td><?= fdate($m['date_versement']) ?></td>
                <td><?= e($m['beneficiaire'] ?? 'N/A') ?></td>
                <td><?= e($m['nature']) ?></td>
                <td><?= e($m['reference']) ?></td>
                <td><span class="badge <?= $rec ? 'b-green' : 'b-red' ?>"><?= $rec ? 'Recette' : 'Dépense' ?></span></td>
                <td><?= e($m['ventilation'] ?: '-') ?></td>
                <td class="num <?= $rec ? 'pos' : 'neg' ?>"><?= money($m['montant']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals">
        <div><span>Total recettes</span><strong class="pos"><?= money($b['total_recette']) ?></strong></div>
        <div><span>Total dépenses</span><strong class="neg"><?= money($b['total_depense']) ?></strong></div>
        <div class="final"><span>SOLDE FINAL</span><span><?= money($b['solde_final']) ?></span></div>
    </div>

    <div class="signatures">
        <div>L'agent financier</div>
        <div>Le responsable finance</div>
    </div>
    <p style="text-align:center;color:#6b7280;font-size:12px;margin-top:30px">
        Édité le <?= date('d/m/Y à H:i') ?> par <?= e($_SESSION['nom_complet']) ?>
    </p>
</body>
</html>
