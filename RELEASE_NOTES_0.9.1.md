# Notes de version 0.9.1 - Module CalDAV Client

**Date de sortie** : 7 mars 2026  
**Auteur** : MATER Stéphane

## 🎯 Objectif de cette version

Corrections techniques importantes pour améliorer la stabilité du module et éviter les conflits avec d'autres modules Dolibarr.

---

## 🔧 Corrections techniques majeures

### 1. Changement de l'ID du module

**Problème identifié** : L'ancien ID (192072) pouvait créer des conflits avec d'autres modules.

**Solution** :
- Ancien ID : `192072`
- Nouveau ID : `192076`

### 2. Refonte du système de permissions

**Problème identifié** : Le système de calcul dynamique des permissions (`$this->numero + $r`) créait des risques de conflit entre modules.

**Avant** (risqué) :
```php
$this->rights[$r][0] = $this->numero + $r;  // Donne : 192072, 192073, 192074...
```

**Après** (sécurisé) :
```php
$this->rights[0][0] = 192076001;  // Permission lecture
$this->rights[1][0] = 192076002;  // Permission écriture
```

**Avantages** :
- ✅ Aucun risque de conflit avec d'autres modules
- ✅ Jusqu'à 999 permissions possibles (192076001 à 192076999)
- ✅ Format recommandé par les bonnes pratiques Dolibarr
- ✅ Facilite le débogage et la maintenance

### 3. Corrections du widget page d'accueil

**Problèmes identifiés** :
- Format de déclaration du widget incorrect
- Erreurs de chargement des classes
- Widget apparaissant en double
- Page d'accueil qui ne se chargeait plus

**Solutions appliquées** :

#### a) Format de déclaration corrigé
**Avant** (simplifié, ne fonctionnait pas) :
```php
$this->boxes[$r][1] = "caldaveventsbox@caldavclient";
```

**Après** (format complet Dolibarr) :
```php
$this->boxes = array(
    0 => array(
        'file' => 'caldaveventsbox.php@caldavclient',
        'note' => 'Widget des événements CalDAV à venir',
        'enabledbydefaulton' => 'Home'
    )
);
```

#### b) Chargement des classes amélioré
- Remplacement de `require_once` par `dol_include_once()` (méthode Dolibarr)
- Utilisation correcte de la variable globale `$db` au lieu de `$this->db`

#### c) Gestion des erreurs renforcée
- Bloc `try-catch` global pour éviter les erreurs fatales
- Messages d'erreur informatifs dans le widget en cas de problème
- Vérification correcte des permissions (`$user->rights->caldavclient->read`)

### 4. Script de nettoyage ajouté

Nouveau fichier : `admin/cleanup_widget.php`

**Utilité** : Permet de supprimer manuellement les doublons de widgets qui peuvent subsister dans la base de données après les mises à jour.

**Utilisation** :
```
http://votre-dolibarr/custom/caldavclient/admin/cleanup_widget.php
```

---

## 📊 Résumé des changements

| Élément | Avant | Après |
|---------|-------|-------|
| ID Module | 192072 | 192076 |
| Permission lecture | 192072 | 192076001 |
| Permission écriture | 192073 | 192076002 |
| Format widget | Simplifié | Tableau complet |
| Chargement classes | `require_once` | `dol_include_once()` |

---

## ⚠️ IMPORTANT : Migration depuis 0.9.0

### Étape 1 : Désactiver le module
1. Aller dans **Configuration > Modules/Applications**
2. Rechercher **"CalDAV Client"**
3. Cliquer sur **Désactiver**
   - ✅ Supprime les anciennes permissions (192072, 192073)
   - ✅ Supprime les anciens widgets
   - ✅ Supprime les anciennes entrées de menu

### Étape 2 : Vider le cache (recommandé)
```bash
sudo rm -rf /var/lib/dolibarr/documents/*/temp/*
```

### Étape 3 : Réactiver le module
1. Attendre **3 secondes**
2. Cliquer sur **Activer**
   - ✅ Crée les nouvelles permissions (192076001, 192076002)
   - ✅ Crée le widget avec le bon format
   - ✅ Recrée les entrées de menu

### Étape 4 : Ajouter le widget (optionnel)
1. **Accueil** → Cliquer sur **⚙️** (roue dentée)
2. **"Ajouter un widget"**
3. Chercher **"Événements CalDAV à venir"**
4. Le widget devrait apparaître **une seule fois**
5. Cocher, positionner et enregistrer

### En cas de problème avec les doublons
Si vous voyez toujours des widgets en double :
1. Accéder à : `http://votre-dolibarr/custom/caldavclient/admin/cleanup_widget.php`
2. Le script supprimera automatiquement les doublons
3. Rafraîchir la page de gestion des widgets

---

## 🐛 Corrections de bugs

### Bug #1 : Widget apparaissant en double
**Cause** : Format de déclaration incorrect + anciennes entrées dans la base de données  
**Solution** : Format de déclaration corrigé + script de nettoyage fourni

### Bug #2 : Page d'accueil ne se charge plus
**Cause** : Erreurs fatales dans le code du widget (classes non chargées, permissions incorrectes)  
**Solution** : Gestion d'erreurs robuste avec try-catch, chargement correct des classes

### Bug #3 : Widget ne trouve pas les classes CalDAV
**Cause** : Utilisation de `require_once` avec chemins absolus  
**Solution** : Utilisation de `dol_include_once()` (méthode Dolibarr pour modules custom)

---

## 📚 Documentation mise à jour

- `CHANGELOG.md` : Ajout de la section 0.9.1
- `VERSION` : Mise à jour vers 0.9.1
- `RELEASE_NOTES_0.9.1.md` : Notes détaillées sur cette version

---

## ✅ Tests recommandés après mise à jour

1. ✅ Vérifier que le module s'active/désactive sans erreur
2. ✅ Vérifier que les permissions sont bien créées (192076001, 192076002)
3. ✅ Vérifier qu'il n'y a qu'un seul widget "Événements CalDAV" disponible
4. ✅ Vérifier que le widget s'affiche correctement sur la page d'accueil
5. ✅ Vérifier que les événements CalDAV apparaissent dans l'agenda
6. ✅ Vérifier le fonctionnement de FullCalendar
7. ✅ Vérifier la gestion de visibilité (Public/Individuel)

---

## 🔄 Compatibilité

- **Dolibarr** : 16.0+
- **PHP** : 7.4+
- **Base de données** : MySQL/MariaDB
- **Migration depuis** : 0.9.0 (désactiver/réactiver requis)
- **Migration depuis** : 0.8.x (exécuter `sql/update_0.8.1_to_0.8.2.sql` puis désactiver/réactiver)

---

## 💡 Améliorations pour les développeurs

### Format de permissions recommandé
Le module utilise maintenant le format de permissions recommandé par Dolibarr :

```php
// ID module : 192076
// Permissions : 192076XXX (XXX de 001 à 999)

$this->rights[0][0] = 192076001;  // Lecture
$this->rights[1][0] = 192076002;  // Écriture
$this->rights[2][0] = 192076003;  // Future permission...
```

### Format de widgets recommandé
```php
$this->boxes = array(
    0 => array(
        'file' => 'widgetname.php@modulename',
        'note' => 'Description du widget',
        'enabledbydefaulton' => 'Home'
    )
);
```

---

## 🆘 Support et aide

En cas de problème :
1. Consulter `README.md` pour la documentation générale
2. Consulter `WIDGET.md` pour la documentation du widget
3. Vérifier les logs Dolibarr : `/var/lib/dolibarr/documents/dolibarr.log`
4. Utiliser le script de nettoyage : `admin/cleanup_widget.php`

---

**Note** : Cette version corrige des problèmes techniques importants. La désactivation/réactivation du module est **obligatoire** pour appliquer correctement les changements.
