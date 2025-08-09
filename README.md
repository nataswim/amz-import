# Amazon Product Importer for WooCommerce

Plugin permettant l’importation de produits Amazon directement dans WooCommerce.

## Fonctionnalités

- **Recherche de produits** : par mots-clés ou ASIN  
- **Importation complète** : titre, description, images, prix et attributs  
- **Synchronisation automatique** : mise à jour des prix et informations  
- **Gestion des catégories** : import et mappage des catégories Amazon  
- **Produits avec variations** : support complet des variations  
- **Logs détaillés** : suivi des importations et des erreurs  

## Prérequis

- WordPress 5.0+  
- WooCommerce 5.0+  
- PHP 7.4+  
- Clés API Amazon Product Advertising API  

## Installation manuelle

1. Télécharger le plugin  
2. Uploader le dossier `amazon-product-importer` dans `/wp-content/plugins/`  
3. Activer le plugin via le menu **Plugins** de WordPress  
4. Configurer les clés API dans **Paramètres → Amazon Importer**  

---

## Commandes Git utiles

### Premier envoi

```bash
cd amz-import
git add .
git commit -m "feat: Initial release of Amazon Product Importer plugin v1.0.0"
git push origin main
```

### Configuration initiale (si première fois)

```bash
git config --global user.name "Votre Nom"
git config --global user.email "votre.email@example.com"
```

Cloner le repository :  
```bash
git clone https://github.com/nataswim/amz-import.git
cd amz-import
```

Ajouter les fichiers du plugin :  
```bash
git add .
```

---

### Workflow quotidien

1. Récupérer les dernières modifications :  
```bash
git pull origin main
```
2. Faire les modifications dans votre IDE  
3. Voir les changements :  
```bash
git status
git diff
```
4. Ajouter les fichiers modifiés :  
```bash
git add .
```
5. Commit avec un message descriptif :  
```bash
git commit -m "fix: Corriger la validation des ASIN dans l'importateur"
```
6. Envoyer sur GitHub :  
```bash
git push origin main
```

---

### Commandes supplémentaires

- Voir l’historique :  
```bash
git log --oneline
```
- Créer une nouvelle branche :  
```bash
git checkout -b feature/nouvelle-fonctionnalite
git push -u origin feature/nouvelle-fonctionnalite
```
- Revenir sur main :  
```bash
git checkout main
```
- Créer un tag :  
```bash
git tag -a v1.0.0 -m "Version 1.0.0 - Initial release"
git push origin v1.0.0
```

---

### Types de commits recommandés

```bash
feat: Nouvelle fonctionnalité
fix: Correction de bug
docs: Mise à jour documentation
style: Formatage code
refactor: Refactorisation
test: Ajout de tests
chore: Maintenance générale
```

---

### Authentification GitHub

- **Token personnel** :  
  1. Aller sur GitHub → Settings → Developer settings → Personal access tokens  
  2. Générer un token et l’utiliser comme mot de passe  

- **Clé SSH** :  
```bash
ssh-keygen -t ed25519 -C "votre.email@example.com"
```
Ajouter la clé publique dans **GitHub → Settings → SSH and GPG keys**  

---

### Fichier `.gitignore` recommandé

```gitignore
# WordPress
wp-config.php
wp-content/uploads/
wp-content/cache/

# Plugin spécifique
*.log
.DS_Store
Thumbs.db
node_modules/
vendor/
.env

# IDE
.vscode/
.idea/

# Fichiers temporaires
*.tmp
*.bak
*~
```
