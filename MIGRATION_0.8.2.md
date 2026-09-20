# Guide de Migration vers la version 0.8.2

**De** : Version 0.8.1  
**Vers** : Version 0.8.2  
**Date** : 15 février 2026

## 🎯 Nouveautés de la version 0.8.2

Cette version ajoute le **système de visibilité des calendriers** permettant de contrôler qui peut voir chaque calendrier.

### Nouvelles Fonctionnalités

1. **Deux modes de visibilité** :
   - **Public** : Visible par tous les utilisateurs
   - **Individuel** : Visible par les utilisateurs sélectionnés

2. **Gestion des permissions utilisateur** :
   - Sélection multi-utilisateurs
   - Interface intuitive
   - Changements en temps réel

## 📋 Étapes de Migration

### Étape 1 : Sauvegarder la base de données

**IMPORTANT** : Avant toute migration, faites une sauvegarde !

```bash
mysqldump -u root -p dolibarr llx_caldav_connections llx_caldav_calendars > backup_caldav_0.8.1.sql
```

### Étape 2 : Exécuter le script SQL de mise à jour

Deux méthodes possibles :

#### Méthode A : Via l'interface Dolibarr

1. Connectez-vous en tant qu'administrateur
2. Allez dans **Configuration > Base de données > SQL**
3. Copiez le contenu de `sql/update_0.8.1_to_0.8.2.sql`
4. Exécutez le script

#### Méthode B : Via la ligne de commande

```bash
mysql -u root -p dolibarr < /usr/share/dolibarr/htdocs/custom/caldavclient/sql/update_0.8.1_to_0.8.2.sql
```

### Étape 3 : Vérifier la migration

Après l'exécution du script, vérifiez que :

1. **La colonne `visibility_type` existe** :
   ```sql
   DESCRIBE llx_caldav_calendars;
   ```
   Vous devez voir une colonne `visibility_type` de type VARCHAR(20)

2. **La table de liaison existe** :
   ```sql
   SHOW TABLES LIKE 'llx_caldav_calendar_users';
   ```
   La table doit exister

3. **Les calendriers existants sont en mode public** :
   ```sql
   SELECT name, visibility_type FROM llx_caldav_calendars;
   ```
   Tous doivent avoir `visibility_type = 'public'`

### Étape 4 : Vider le cache

Pour que les changements soient pris en compte immédiatement :

```bash
rm -rf /var/lib/dolibarr/documents/caldavclient/cache/*.cache
```

Ou depuis Dolibarr : **Configuration > Autres > Purger le cache**

### Étape 5 : Tester la nouvelle fonctionnalité

1. Allez dans **Configuration > CalDAV Client > Configuration**
2. Cliquez sur "Voir les calendriers" pour une connexion
3. Cliquez sur "Modifier" pour un calendrier
4. Vous devez voir le nouveau champ **"Visibilité"**
5. Testez les deux modes (Public et Individuel)

## 🔄 Comportement par Défaut

### Calendriers Existants

**Tous les calendriers existants sont automatiquement en mode "Public"** (comme avant la mise à jour).

- ✅ Aucun changement de comportement
- ✅ Tous les utilisateurs continuent de voir les mêmes calendriers
- ✅ Migration transparente

### Nouveaux Calendriers

Les nouveaux calendriers découverts seront également en mode "Public" par défaut.

Vous pouvez ensuite les changer en mode "Individuel" si nécessaire.

## 🎨 Utilisation du Système de Visibilité

### Exemple 1 : Calendrier Public (Jours Fériés)

1. Modifier le calendrier "Jours Fériés"
2. Visibilité : **Public**
3. → Tous les utilisateurs voient les jours fériés

### Exemple 2 : Calendrier Individuel (Commercial)

1. Modifier le calendrier "Agenda Commercial"
2. Visibilité : **Individuel**
3. Utilisateurs assignés : Sélectionner Alice, Bob, Charlie (équipe commerciale)
4. → Seuls Alice, Bob et Charlie voient ce calendrier

### Exemple 3 : Calendrier Personnel

1. Modifier le calendrier "Mon Nextcloud"
2. Visibilité : **Individuel**
3. Utilisateurs assignés : Sélectionner uniquement votre utilisateur
4. → Seul vous voyez ce calendrier

## ⚠️ Points d'Attention

### Permissions Administrateur

- Seuls les **administrateurs** peuvent configurer la visibilité des calendriers
- Les utilisateurs normaux voient uniquement les calendriers auxquels ils ont accès

### Changement de Visibilité

Quand vous changez un calendrier de "Public" vers "Individuel" :
- Les utilisateurs non assignés **perdent immédiatement l'accès**
- Leurs checkboxes disparaissent de l'interface
- Les événements ne sont plus affichés

### Performance

Le système de visibilité utilise une requête SQL optimisée avec JOIN :
- ✅ Performance équivalente au mode public
- ✅ Pas d'impact sur le temps de chargement
- ✅ Index créés pour optimiser les requêtes

## 🔙 Rollback (si nécessaire)

Si vous rencontrez un problème et souhaitez revenir en arrière :

### Restaurer la sauvegarde

```bash
mysql -u root -p dolibarr < backup_caldav_0.8.1.sql
```

### Supprimer les modifications

```sql
ALTER TABLE llx_caldav_calendars DROP COLUMN visibility_type;
DROP TABLE IF EXISTS llx_caldav_calendar_users;
```

## 📊 Modifications de la Base de Données

### Nouvelle Colonne

| Table | Colonne | Type | Défaut | Description |
|-------|---------|------|--------|-------------|
| `llx_caldav_calendars` | `visibility_type` | VARCHAR(20) | 'public' | Type de visibilité |

### Nouvelle Table

| Table | Description |
|-------|-------------|
| `llx_caldav_calendar_users` | Liaison calendriers ↔ utilisateurs |

**Colonnes** :
- `rowid` : ID auto-incrémenté
- `fk_calendar` : ID du calendrier
- `fk_user` : ID de l'utilisateur
- `date_creation` : Date de création

## ✅ Checklist de Migration

- [ ] Sauvegarde de la base de données effectuée
- [ ] Script SQL exécuté sans erreur
- [ ] Colonne `visibility_type` présente dans `llx_caldav_calendars`
- [ ] Table `llx_caldav_calendar_users` créée
- [ ] Tous les calendriers existants en mode 'public'
- [ ] Cache vidé
- [ ] Interface testée avec les deux modes de visibilité
- [ ] Utilisateurs testés (assignés et non assignés)

## 📞 Support

En cas de problème pendant la migration :
1. Consultez les logs Dolibarr (niveau DEBUG)
2. Vérifiez les messages d'erreur SQL
3. Assurez-vous d'avoir les permissions MySQL nécessaires
4. Restaurez la sauvegarde si nécessaire

---

**Migration réussie ?** Profitez du nouveau système de visibilité ! 🎉
