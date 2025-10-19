# Système de Gestion Documentaire RH

## 📋 Description

Ce projet est un système complet de gestion documentaire pour les ressources humaines, développé avec Symfony 7.3. Il implémente une architecture basée sur les rôles avec gestion hiérarchique des employés, contrats multiples, organisations, dossiers et documents. Le système inclut une matrice documentaire avancée pour la conformité légale.

## 🏗️ Architecture

### Structure de Base de Données

Le système utilise une structure normalisée avec préfixes `p_` (paramétrage) et `t_` (traitement) :

#### Tables de Paramétrage (p_)
- **`p_document`** - Référentiel documentaire RH
- **`p_nature_contrat`** - Types de contrats normalisés
- **`p_nature_contrat_type_document`** - Matrice des obligations documentaires
- **`p_placards`** - Emplacements de stockage
- **`p_organisation`** - Structure organisationnelle

#### Tables de Traitement (t_)
- **`t_employe`** - Table des employés
- **`t_employee_contrat`** - Contrats des employés (support multi-contrats)
- **`t_organisation_employee_contrat`** - Assignations organisationnelles
- **`t_dossier`** - Dossiers RH des employés
- **`t_demandes`** - Demandes et réclamations

### Système de Rôles

- **`ROLE_ADMINISTRATEUR_RH`** - Contrôle total du système
- **`ROLE_RESPONSABLE_RH`** - Gestion opérationnelle et matrice documentaire
- **`ROLE_EMPLOYEE`** - Accès personnel limité

## 🚀 Installation

### Prérequis

- PHP 8.2+
- Composer
- PostgreSQL 16
- Docker (optionnel)

### Installation

1. **Cloner le projet**
```bash
git clone https://github.com/anass21ziani/rh_projet.git
cd rh_projet
```

2. **Installer les dépendances**
```bash
composer install
```

3. **Configuration de l'environnement**
```bash
cp .env .env.local
# Modifier les paramètres de base de données dans .env.local
```

4. **Base de données**
```bash
# Avec Docker
docker-compose up -d

# Ou avec PostgreSQL local
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
```

5. **Démarrer le serveur**
```bash
symfony serve
# ou
php -S localhost:8000 -t public
```

## 👤 Utilisateurs de Test

- **Administrateur RH** : `admin@uiass.rh` / `password123`
- **Responsable RH** : `rh@uiass.rh` / `password123`
- **Employé** : `employe@uiass.rh` / `password123`

## 📁 Structure du Projet

```
src/
├── Controller/          # Contrôleurs métier
│   ├── AdministrateurRhController.php
│   ├── ResponsableRhController.php
│   ├── NatureContratTypeDocumentController.php
│   └── SecurityController.php
├── Entity/             # Entités Doctrine
│   ├── Employe.php
│   ├── EmployeeContrat.php
│   ├── Organisation.php
│   ├── OrganisationEmployeeContrat.php
│   ├── NatureContrat.php
│   ├── NatureContratTypeDocument.php
│   ├── Document.php
│   ├── Dossier.php
│   ├── Placard.php
│   └── Demande.php
├── Form/               # Formulaires Symfony
├── Repository/         # Repositories de données
├── Security/           # Configuration sécurité
└── DataFixtures/       # Données de test

templates/              # Templates Twig
├── administrateur-rh/  # Interface administrateur
├── responsable-rh/     # Interface responsable
├── employee/           # Interface employé
├── dashboard/          # Dashboard unifié
└── base_sidebar.html.twig  # Template avec sidebar
```

## 🔧 Fonctionnalités

### Pour l'Administrateur RH
- ✅ Gestion complète des responsables RH
- ✅ Vue d'ensemble du système
- ✅ Configuration des accès

### Pour le Responsable RH
- ✅ **Gestion des employés** avec contrats multiples
- ✅ **Organisations** - Structure organisationnelle complète
- ✅ **Matrice Documentaire** - Obligations par type de contrat
- ✅ **Gestion des dossiers** et documents
- ✅ **Dashboard** avec statistiques avancées
- ✅ **Assignations** employés-organisations
- ✅ **Interface opérationnelle** complète

### Pour l'Employé
- ✅ **Dashboard personnel** avec KPIs individuels
- ✅ **Mon profil** - Informations personnelles
- ✅ **Mes contrats** - Historique des contrats
- ✅ **Mes dossiers** - Documents personnels
- ✅ **Mes demandes** - Suivi des réclamations

### Fonctionnalités Avancées
- ✅ **Contrats Multiples** - Un employé peut avoir plusieurs contrats
- ✅ **Organisations Multiples** - Assignation à différentes organisations
- ✅ **Matrice Documentaire** - Conformité légale automatisée
- ✅ **Dashboard Unifié** - Contenu adaptatif selon le rôle
- ✅ **Interface Responsive** - Sidebar moderne
- ✅ **Authentification sécurisée** avec gestion des rôles
- ✅ **Upload de documents** avec validation
- ✅ **Recherche et filtrage** avancés
- ✅ **Historique et traçabilité** complète

## 🏢 Gestion des Organisations

### Structure Organisationnelle
- **Division d'Activités Stratégiques (DAS)**
- **Groupements**
- **Dossiers** avec désignations
- **Assignations** employés-organisations avec dates

### Contrats Multiples
- **Contrat Principal** : Obligatoire lors de la création
- **Contrat Secondaire** : Optionnel, nature différente possible
- **Organisations différentes** pour chaque contrat
- **Gestion temporelle** indépendante

## 📊 Matrice Documentaire

### Obligations Légales
- **Documents obligatoires** par type de contrat
- **Documents optionnels** selon la nature
- **Conformité automatisée** avec alertes
- **Statistiques** de conformité

### Types de Documents Supportés
- CIN, CV, Diplôme, Contrat
- Bulletins de paie, Certificats médicaux
- RIB, Photo d'identité
- Attestations de travail, Lettres de motivation

## 🛠️ Technologies Utilisées

- **Backend** : Symfony 7.3, PHP 8.2+
- **Base de données** : PostgreSQL 16
- **ORM** : Doctrine 3.5
- **Frontend** : Twig, Bootstrap 5, Chart.js
- **Conteneurisation** : Docker Compose
- **Sécurité** : Symfony Security Bundle

## 📊 Cas d'Usage

### Recrutement Avancé
1. Créer un employé avec informations complètes
2. Créer un contrat principal avec organisation
3. Optionnellement créer un contrat secondaire
4. Assigner aux organisations appropriées
5. Créer un dossier administratif
6. Vérifier la conformité documentaire via la matrice

### Gestion Organisationnelle
1. Configurer la structure organisationnelle
2. Assigner les employés aux organisations
3. Gérer les contrats multiples
4. Suivre les évolutions organisationnelles

### Conformité Documentaire
1. Consulter la matrice des obligations
2. Identifier les documents manquants
3. Uploader les documents requis
4. Vérifier la conformité par employé

### Audit et Reporting
- Historique des contrats multiples
- Traçabilité des assignations organisationnelles
- Statistiques de conformité documentaire
- Logs d'activité complets

## 🔐 Sécurité

- Authentification par formulaire sécurisé
- Hachage automatique des mots de passe
- Protection CSRF sur tous les formulaires
- Contrôle d'accès par rôles granulaires
- Headers de sécurité automatiques
- Protection contre la navigation arrière
- Sessions sécurisées

## 📈 Évolutions Possibles

- **Workflow d'approbation** pour les documents
- **Versioning** des documents
- **Notifications** automatiques
- **API REST** pour intégrations
- **Recherche full-text** avancée
- **Rapports** personnalisés
- **Intégration** avec d'autres systèmes RH
- **Mobile app** pour les employés

## 🎯 Fonctionnalités Récentes

### ✅ Implémentées
- **Support des contrats multiples** par employé
- **Gestion des organisations** avec assignations
- **Matrice documentaire** interactive
- **Dashboard unifié** adaptatif
- **Interface sidebar** moderne
- **Conformité documentaire** automatisée

### 🔄 Améliorations Continues
- Interface utilisateur optimisée
- Performance des requêtes
- Expérience utilisateur
- Sécurité renforcée

## 🤝 Contribution

1. Fork le projet
2. Créer une branche feature (`git checkout -b feature/nouvelle-fonctionnalite`)
3. Commit les changements (`git commit -am 'Ajouter nouvelle fonctionnalité'`)
4. Push vers la branche (`git push origin feature/nouvelle-fonctionnalite`)
5. Ouvrir une Pull Request

## 📄 Licence

Ce projet est sous licence propriétaire.

## 📞 Support

Pour toute question ou support, contactez l'équipe de développement.

---

**Développé avec ❤️ pour la gestion moderne des ressources humaines**

*Système RH complet avec gestion des organisations, contrats multiples et conformité documentaire automatisée.*