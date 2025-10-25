# 🏥 FyraCare - Système de Gestion de Salon/Centre de Soins

[![Symfony](https://img.shields.io/badge/Symfony-7.3-black?logo=symfony)](https://symfony.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-Proprietary-red)]()

Application web de gestion complète pour centres de soins, salons de coiffure et établissements similaires. Gestion des rendez-vous, clients, prestations, paiements et comptabilité.

---

## 📑 Table des matières

- [Fonctionnalités](#-fonctionnalités)
- [Architecture](#-architecture)
- [Prérequis](#-prérequis)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Utilisation](#-utilisation)
- [Sécurité](#-sécurité)
- [Tests](#-tests)
- [Déploiement](#-déploiement)
- [Contributeurs](#-contributeurs)

---

## ✨ Fonctionnalités

### 🗓️ Gestion des Rendez-vous
- **Agenda visuel** avec FullCalendar
- Création/modification/suppression de RDV
- Statuts : Planifié, Confirmé, Honoré, Annulé, Absent
- Recherche et filtres avancés
- Vue jour/semaine/mois

### 👥 Gestion Clients
- Fiche client complète (nom, téléphone, email, notes)
- Historique des RDV et paiements
- Statistiques par client
- Recherche rapide

### 💼 Gestion Prestations
- Catalogue de services
- Prix, durée, description
- Statut actif/inactif
- Calcul automatique de fin de RDV

### 💰 Gestion des Paiements
- Enregistrement espèces/mobile money
- Génération automatique de reçus (PDF)
- Numérotation automatique
- Historique complet

### 📊 Comptabilité (Caisse)
- **Suivi en temps réel** du solde de caisse
- Entrées (paiements, injections de fonds)
- Sorties (dépenses catégorisées)
- Clôtures journalières
- Rapports mensuels/annuels
- Graphiques ApexCharts

### 📈 Statistiques & Rapports
- Dashboard avec KPIs
- Chiffre d'affaires (jour/mois/année)
- Top prestations
- Évolution des RDV
- Graphiques interactifs

### 🔐 Sécurité
- Système d'authentification robuste
- Gestion des rôles (ROLE_USER, ROLE_ADMIN, ROLE_SUPER_ADMIN)
- **Verrouillage automatique par inactivité**
- Tokens CSRF sur toutes les actions sensibles

### ⚙️ Administration
- Gestion des utilisateurs
- Paramètres de l'entreprise (logo, coordonnées)
- Catégories de dépenses
- Configuration système

---

## 🏗️ Architecture

### Stack Technique

**Backend**
- Symfony 7.3
- PHP 8.2+
- Doctrine ORM
- Twig (templating)

**Frontend**
- Velzon (Bootstrap 5 theme)
- ApexCharts (graphiques)
- FullCalendar (agenda)
- Select2 (autocomplete)
- RemixIcon (icônes)

**Base de données**
- MySQL 8.0+ / MariaDB 10.11+
- PostgreSQL 16+ (support)

**PDF**
- Dompdf (génération de reçus)
- KnpSnappy (alternative wkhtmltopdf)

### Structure du projet

```
fyracare/
├── config/              # Configuration Symfony
├── migrations/          # Migrations Doctrine
├── public/              # Point d'entrée web
│   └── assets/         # Assets statiques
├── src/
│   ├── Command/        # Commandes console
│   ├── Controller/     # Contrôleurs
│   ├── Entity/         # Entités Doctrine
│   ├── EventSubscriber/ # Event Subscribers (inactivité)
│   ├── Form/           # Types de formulaires
│   ├── Repository/     # Repositories Doctrine
│   └── Service/        # Services métier
├── templates/          # Templates Twig
│   ├── cash/          # Caisse
│   ├── client/        # Clients
│   ├── payment/       # Paiements
│   ├── rdv/           # Rendez-vous
│   └── ...
├── tests/             # Tests unitaires/fonctionnels
└── var/               # Cache, logs
```

---

## 🔧 Prérequis

### Serveur

- PHP >= 8.2
- Composer 2.x
- Node.js 18+ & npm (optionnel, pour assets)
- MySQL 8.0+ / MariaDB 10.11+ / PostgreSQL 16+

### Extensions PHP requises

```bash
php -m | grep -E 'ctype|iconv|pdo_mysql|intl|mbstring|xml|curl|zip'
```

### Outils additionnels

- wkhtmltopdf (pour génération PDF via Snappy)
- Git

---

## 🚀 Installation

### 1. Cloner le projet

```bash
git clone https://github.com/votre-repo/fyracare.git
cd fyracare
```

### 2. Installer les dépendances

```bash
composer install
```

### 3. Configurer l'environnement

Copier `.env` vers `.env.local` et ajuster :

```bash
cp .env .env.local
```

**Éditer `.env.local` :**

```env
# Base de données
DATABASE_URL="mysql://user:password@127.0.0.1:3306/fyracare?serverVersion=8.0.32&charset=utf8mb4"

# Environnement
APP_ENV=prod
APP_SECRET=VOTRE_SECRET_GENERE

# Inactivité (secondes)
APP_INACTIVITY_SECONDS=900
APP_INACTIVITY_WARNING=60
APP_INACTIVITY_ENABLED=true
APP_INACTIVITY_LOGOUT_MODE=false
```

### 4. Créer la base de données

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### 5. (Optionnel) Charger des données de test

```bash
php bin/console doctrine:fixtures:load
```

### 6. Installer les assets

```bash
php bin/console assets:install public
```

### 7. Lancer le serveur de développement

```bash
symfony server:start
# ou
php -S localhost:8000 -t public/
```

🎉 **Accès** : `http://localhost:8000`

**Identifiants par défaut** (si fixtures chargées) :
- Admin : `admin@fyracare.test` / `admin1234`
- User : `user@fyracare.test` / `user1234`

---

## ⚙️ Configuration

### Système d'inactivité

Paramètres dans `.env` :

| Variable | Description | Valeur par défaut |
|----------|-------------|-------------------|
| `APP_INACTIVITY_SECONDS` | Temps avant verrouillage | 900 (15 min) |
| `APP_INACTIVITY_WARNING` | Avertissement avant lock | 60 |
| `APP_INACTIVITY_ENABLED` | Activer/désactiver | true |
| `APP_INACTIVITY_LOGOUT_MODE` | Déconnexion au lieu de lock | false |

**Commande de vérification** :
```bash
php bin/console app:check-inactivity
```

### wkhtmltopdf (génération PDF)

**Windows :**
```yaml
# config/packages/knp_snappy.yaml
knp_snappy:
  pdf:
    binary: '"C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe"'
```

**Linux/Mac :**
```yaml
knp_snappy:
  pdf:
    binary: '/usr/local/bin/wkhtmltopdf'
```

### Paramètres de l'entreprise

Via l'interface admin : **Paramètres > Entreprise**
- Téléphone, email, adresse
- Logo (PNG/JPG, max 3 Mo)

---

## 📖 Utilisation

### Workflow typique

1. **Créer un client** : Clients > Nouveau client
2. **Ajouter une prestation** : Prestations > Nouvelle prestation
3. **Planifier un RDV** : Rendez-vous > Nouveau RDV (sélectionner client + prestation)
4. **Enregistrer le paiement** : Depuis la fiche RDV, cliquer "Payer"
5. **Générer le reçu PDF** : Automatique après paiement
6. **Consulter la caisse** : Caisse > Vue journalière

### Rôles et permissions

| Rôle | Accès |
|------|-------|
| `ROLE_USER` | Dashboard, RDV, Clients, Paiements, Agenda, Caisse (lecture) |
| `ROLE_ADMIN` | + Prestations, Utilisateurs, Dépenses, Catégories, Paramètres |
| `ROLE_SUPER_ADMIN` | Tous les accès |

### Raccourcis clavier

- Créer un RDV : `Alt+N` (depuis l'agenda)
- Recherche rapide : `Ctrl+K`

---

## 🔒 Sécurité

### Points clés

1. **Authentification** : Symfony Security Component
2. **Mots de passe** : Hachage bcrypt automatique
3. **CSRF** : Tokens sur tous les formulaires
4. **Inactivité** : Lock automatique configurable
5. **Validation** : Contraintes Symfony Validator
6. **SQL Injection** : Protégé via Doctrine ORM
7. **XSS** : Échappement automatique Twig

### Bonnes pratiques

- Changer `APP_SECRET` en production
- Désactiver le mode debug (`APP_ENV=prod`)
- Utiliser HTTPS
- Sauvegardes régulières de la base de données
- Mettre à jour les dépendances : `composer update`

---

## 🧪 Tests

### Lancer les tests

```bash
# Tous les tests
php bin/phpunit

# Tests unitaires uniquement
php bin/phpunit --testsuite Unit

# Tests fonctionnels
php bin/phpunit --testsuite Functional

# Avec couverture
php bin/phpunit --coverage-html var/coverage
```

### Structure des tests

```
tests/
├── Controller/     # Tests fonctionnels (HTTP)
├── Entity/         # Tests unitaires entités
├── Service/        # Tests unitaires services
└── Integration/    # Tests d'intégration
```

---

## 🚢 Déploiement

### Préparer pour la production

```bash
# 1. Optimiser l'autoloader
composer install --no-dev --optimize-autoloader

# 2. Vider le cache
php bin/console cache:clear --env=prod

# 3. Warm-up du cache
php bin/console cache:warmup --env=prod

# 4. Installer les assets
php bin/console assets:install public --env=prod
```

### Serveur web (Apache)

**`.htaccess`** dans `/public` :

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>
```

### Serveur web (Nginx)

```nginx
server {
    root /var/www/fyracare/public;
    
    location / {
        try_files $uri /index.php$is_args$args;
    }
    
    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        internal;
    }
    
    location ~ \.php$ {
        return 404;
    }
}
```

### Tâches CRON (optionnelles)

```bash
# Clôture automatique de caisse (exemple : minuit)
0 0 * * * cd /var/www/fyracare && php bin/console app:cash:close-day >> var/log/cron.log 2>&1
```

---

## 📝 Notes de version

### v1.0.0 (2025-01-XX)
- ✅ Gestion complète RDV, clients, prestations
- ✅ Module paiements + reçus PDF
- ✅ Comptabilité caisse (entrées/sorties/clôtures)
- ✅ Statistiques et dashboard
- ✅ Système d'inactivité
- ✅ Multi-utilisateurs avec rôles

---

## 🤝 Contributeurs

**Développé par** : Amadou Adjanouhoun (Dantie-IT)  
**Contact** : [votre-email@exemple.com]

---

## 📄 Licence

© 2025 Dantie-IT. Tous droits réservés.  
Logiciel propriétaire - Utilisation commerciale interdite sans autorisation.

---

## 🆘 Support

Pour toute question ou bug :
- 📧 Email : support@fyracare.com
- 📱 WhatsApp : +222 XX XX XX XX
- 🐛 Issues : [GitHub Issues](https://github.com/votre-repo/issues)

---

**Fait avec ❤️ en Mauritanie 🇲🇷**


