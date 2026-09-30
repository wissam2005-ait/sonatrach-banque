# 🏛️ Sonatrach Finance — Gestion des règlements & brouillards de banque

Application web de gestion financière pour la **Direction Finance de Sonatrach Béjaïa** : saisie des recettes et dépenses, gestion des bénéficiaires, commissions et ventilations, puis génération de **brouillards de banque** validés par un responsable.

> Projet réalisé en **PHP / MySQL** (PDO) avec une interface responsive en CSS pur.

## ✨ Fonctionnalités

| Rôle | Ce qu'il peut faire |
|------|---------------------|
| **Agent financier** | Saisir des mouvements (recettes / dépenses), filtrer par mois et année, suivre le bilan de la période, gérer les bénéficiaires, les ventilations et les commissions |
| **Responsable finance** | Générer un brouillard sur une période, l'approuver ou le rejeter, l'imprimer / l'exporter en PDF |
| **Administrateur** | Créer et supprimer des comptes utilisateurs |

**Workflow métier :** l'agent saisit → le responsable génère un brouillard → à l'approbation, les mouvements de la période passent en **« Validé »** et ne peuvent plus être supprimés.

## 🛠️ Stack technique

- PHP 8+ (PDO, sessions, `password_hash`)
- MySQL / MariaDB (InnoDB, clés étrangères)
- HTML5 / CSS3 (design responsive, sans framework)

## 🚀 Installation

1. Placer le dossier dans `htdocs` (XAMPP) ou `www` (WAMP).
2. Importer la base : phpMyAdmin → *Importer* → `schema.sql` (ou `mysql -u root < schema.sql`).
3. Copier `config.example.php` en `config.php` et adapter les identifiants MySQL.
4. Ouvrir `http://localhost/sonatrach-banque/`.

### Comptes de démonstration

| Rôle | Identifiant | Mot de passe |
|------|-------------|--------------|
| Administrateur | `admin` | `admin123` |
| Agent | `agent1` | `agent123` |
| Responsable | `responsable` | `resp123` |

> ⚠️ Comptes réservés à la démonstration : changez-les avant tout usage réel.

## 🔐 Sécurité

- Mots de passe **hachés** (bcrypt via `password_hash`)
- Requêtes **préparées** partout (anti-injection SQL)
- **Protection CSRF** sur toutes les actions (les suppressions passent en POST, plus en lien GET)
- Échappement systématique des sorties (anti-XSS)
- Régénération de l'ID de session à la connexion
- Identifiants de base de données hors dépôt (`config.php` dans `.gitignore`)

## 🗂️ Structure

```
sonatrach-banque/        (un seul dossier, aucun sous-dossier)
├── index.php            # Connexion
├── agent.php            # Mouvements (recettes / dépenses)
├── beneficiaires.php    # Bénéficiaires
├── ventilations.php     # Ventilations comptables
├── commissions.php      # Commissions
├── responsable.php      # Brouillards : génération, approbation
├── brouillard.php       # Vue imprimable / PDF
├── admin.php            # Gestion des utilisateurs
├── logout.php
├── bootstrap.php        # PDO, sessions, CSRF, helpers
├── layout.php           # Menu latéral + gabarit commun
├── style.css            # Feuille de style unique
├── schema.sql           # Schéma + données de démonstration
├── config.example.php
├── .gitignore
├── LICENSE
└── README.md
```

## 🧭 Pistes d'évolution

- Export Excel des mouvements
- Graphiques recettes / dépenses par mois
- Journal d'audit des actions
- Pagination et recherche avancée

## 👤 Auteur

**Wissam Ait El Hadi** — [Portfolio](https://wissam2005-ait.github.io/siteportfolio)
