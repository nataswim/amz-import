# Amazon Product Importer for WooCommerce

Un plugin produits.

## Fonctionnalités

- 🔍 **Recherche de produits** : Recherchez des produits par mots-clés ou ASIN
- 📦 **Importation complète** : Importe titre, description, images, prix et attributs
- 🔄 **Synchronisation automatique** : Met à jour les prix et informations automatiquement
- 🏷️ **Gestion des catégories** : Importe et mappe les catégories Amazon
- 🎯 **Variations de produits** : Support complet des produits avec variations
- 📊 **Logs détaillés** : Suivi complet des importations et erreurs

### Prérequis

- WordPress 5.0+
- WooCommerce 5.0+
- PHP 7.4+
- Clés API Amazon Product Advertising API

### Installation manuelle

1. Téléchargez le plugin
2. Uploadez le dossier `amazon-product-importer` dans `/wp-content/plugins/`
3. Activez le plugin via le menu 'Plugins' de WordPress
4. Configurez vos clés API dans Paramètres → Amazon Importer



🚀 COMMANDE RAPIDE POUR PREMIER ENVOI
bash# Si vous avez déjà tous vos fichiers prêts
cd amz-import
git add .
git commit -m "feat: Initial release of Amazon Product Importer plugin v1.0.0"
git push origin main



🚀 CONFIGURATION INITIALE (si première fois)
bash# Configurer votre identité Git (si pas déjà fait)
git config --global user.name "Votre Nom"
git config --global user.email "votre.email@example.com"
📥 CLONER LE REPOSITORY (si pas déjà fait)
bash# Cloner le repository
git clone https://github.com/nataswim/amz-import.git

# Aller dans le dossier
cd amz-import
📁 AJOUTER VOS FICHIERS DU PLUGIN
bash# Copier tous les fichiers du plugin dans le dossier cloné
# Puis ajouter tous les fichiers au staging
git add .

# OU ajouter des fichiers spécifiques
git add amazon-product-importer.php
git add includes/
git add admin/
# etc...
💾 COMMANDES POUR ENVOYER VOS MODIFICATIONS
bash# 1. Vérifier le statut des fichiers
git status

# 2. Ajouter tous les fichiers modifiés/nouveaux
git add .

# 3. Faire un commit avec un message descriptif
git commit -m "feat: Initial release of Amazon Product Importer plugin v1.0.0

- Complete WordPress/WooCommerce plugin for Amazon product import
- Admin interface with search and import functionality  
- Automatic price synchronization
- Product variations and categories support
- Image import and management
- Logging and caching system
- Multi-language support
- Unit tests included"

# 4. Envoyer vers GitHub
git push origin main
🔄 WORKFLOW QUOTIDIEN POUR LES MODIFICATIONS
bash# 1. Récupérer les dernières modifications (si travail en équipe)
git pull origin main

# 2. Faire vos modifications dans VSCode...

# 3. Voir ce qui a changé
git status
git diff

# 4. Ajouter les fichiers modifiés
git add .
# OU fichiers spécifiques:
git add path/to/specific/file.php

# 5. Commit avec message descriptif
git commit -m "fix: Corriger la validation des ASIN dans l'importateur"

# 6. Envoyer vers GitHub
git push origin main
📋 COMMANDES UTILES SUPPLÉMENTAIRES
bash# Voir l'historique des commits
git log --oneline

# Voir les branches
git branch -a

# Créer une nouvelle branche pour une fonctionnalité
git checkout -b feature/nouvelle-fonctionnalite
git push -u origin feature/nouvelle-fonctionnalite

# Revenir à la branche main
git checkout main

# Voir les modifications non commitées
git diff

# Voir les fichiers dans le staging area
git diff --cached

# Annuler des modifications non commitées
git checkout -- filename.php

# Retirer un fichier du staging
git reset HEAD filename.php
🏷️ CRÉER DES RELEASES/TAGS
bash# Créer un tag pour une version
git tag -a v1.0.0 -m "Version 1.0.0 - Initial release"
git push origin v1.0.0

# Lister les tags
git tag -l
📝 MESSAGES DE COMMIT RECOMMANDÉS
bash# Types de commits recommandés:
git commit -m "feat: Nouvelle fonctionnalité"
git commit -m "fix: Correction de bug"
git commit -m "docs: Mise à jour documentation"
git commit -m "style: Formatage code"
git commit -m "refactor: Refactorisation du code"
git commit -m "test: Ajout de tests"
git commit -m "chore: Maintenance générale"
🔐 AUTHENTIFICATION GITHUB
Si c'est votre première fois, GitHub peut vous demander de vous authentifier :
bash# Utiliser un token personnel (recommandé)
# Allez sur GitHub → Settings → Developer settings → Personal access tokens
# Générez un token et utilisez-le comme mot de passe

# OU configurer SSH (plus sécurisé)
ssh-keygen -t ed25519 -C "votre.email@example.com"
# Puis ajouter la clé publique à GitHub



⚠️ FICHIER .gitignore RECOMMANDÉ
Créez un fichier .gitignore dans votre repository :
gitignore# WordPress
wp-config.php
wp-content/uploads/
wp-content/cache/

# Plugin specifique
*.log
.DS_Store
Thumbs.db
node_modules/
vendor/
.env

# IDE
.vscode/settings.json
.idea/

# Temporary files
*.tmp
*.bak
*~
