# Widget Événements CalDAV - Guide complet

**Version** : 0.9.0  
**Date** : 7 mars 2026  
**Licence** : ce document décrit un composant du module CalDAV Client, sous **GNU GPL v3 ou ultérieure** (SPDX `GPL-3.0-or-later`) — voir `LICENSE` / `COPYING` à la racine du module.

## 📋 Vue d'ensemble

Le widget "Événements CalDAV à venir" affiche sur la page d'accueil de Dolibarr les événements de vos calendriers CalDAV pour **aujourd'hui et demain**.

## ✨ Fonctionnalités

### Informations affichées

Pour chaque événement :
- **Indicateur de calendrier** : Carré coloré selon la couleur du calendrier
- **Jour** : "Aujourd'hui" ou "Demain"
- **Heure** : 
  - Format "HH:MM - HH:MM" pour les événements avec horaire
  - "Toute la journée" pour les événements sur 24h
- **Titre** : Nom de l'événement (tronqué si trop long)

### Tooltip au survol

Au survol de la souris, le tooltip affiche :
- Nom du serveur CalDAV (ex: "Nextcloud")
- Nom du calendrier (ex: "Personnel")
- Lieu de l'événement (si renseigné)
- Description complète de l'événement

### Tri et filtrage

- ✅ **Tri automatique** par date/heure de début
- ✅ **Filtrage** : Seuls les événements d'aujourd'hui et demain
- ✅ **Événements en cours** : Affiche aussi les événements commencés avant mais pas encore terminés
- ✅ **Limite** : Par défaut 5 événements (configurable)

## 🚀 Installation

### Étape 1 : Activer le module CalDAV Client

Si ce n'est pas déjà fait :
1. **Accueil > Configuration > Modules/Applications**
2. Rechercher "CalDAV Client"
3. Cliquer sur "Activer"

### Étape 2 : Configurer au moins un calendrier CalDAV

1. **Accueil > Agenda > CalDAV Client** (dans le menu gauche)
2. Ajouter une connexion CalDAV
3. Découvrir les calendriers
4. Activer au moins un calendrier

### Étape 3 : Ajouter le widget à la page d'accueil

1. **Accéder à la page d'accueil** : Cliquer sur "Accueil" dans le menu principal
2. **Entrer en mode édition** : Cliquer sur l'icône ⚙️ (roue dentée) en haut à droite de la page
3. **Ajouter un widget** :
   - Cliquer sur le bouton "+ Ajouter un widget" ou "Ajouter une boîte"
   - Dans la liste, chercher "Événements CalDAV à venir" ou "CalDAV"
   - Cocher la case du widget
4. **Positionner le widget** :
   - Glisser-déposer le widget à l'endroit souhaité
   - Vous pouvez le mettre en colonne gauche, centrale ou droite
5. **Enregistrer** : Cliquer sur "Enregistrer" ou quitter le mode édition

## ⚙️ Configuration

### Nombre d'événements affichés

Par défaut, le widget affiche **5 événements maximum**. Pour changer cette limite :

1. **Mode édition** de la page d'accueil (icône ⚙️)
2. **Paramètres du widget** : Cliquer sur l'icône ⚙️ du widget "Événements CalDAV"
3. **Modifier la valeur** : Champ "Nombre maximum de lignes" ou "Max"
4. **Enregistrer** : Valider les modifications

**Valeurs recommandées** :
- `3` : Pour un affichage compact
- `5` : Valeur par défaut (bon compromis)
- `10` : Pour voir plus d'événements
- `20` : Maximum recommandé

### Calendriers affichés

Le widget affiche automatiquement les événements de **tous les calendriers CalDAV** :
- Activés dans la configuration
- Auxquels l'utilisateur a accès (selon le système de visibilité Public/Individuel)

Pour modifier les calendriers affichés :
1. **Accueil > Agenda > CalDAV Client**
2. Activer/Désactiver les calendriers souhaités
3. Rafraîchir la page d'accueil

## 📊 Exemples d'affichage

### Exemple 1 : Journée chargée

```
┌─────────────────────────────────────────────────┐
│ Événements CalDAV à venir (Aujourd'hui et demain) │
├─────────────────────────────────────────────────┤
│ 🟦 Aujourd'hui  │ 09:00 - 10:00 │ Stand-up      │
│ 🟩 Aujourd'hui  │ 14:00 - 15:30 │ Réunion client│
│ 🟧 Aujourd'hui  │ 16:00 - 17:00 │ Revue de code │
│ 🟦 Demain       │ 10:00 - 11:00 │ Formation     │
│ 🟩 Demain       │ Toute la journée │ Congé        │
└─────────────────────────────────────────────────┘
```

### Exemple 2 : Aucun événement

```
┌─────────────────────────────────────────────────┐
│ Événements CalDAV à venir (Aujourd'hui et demain) │
├─────────────────────────────────────────────────┤
│        Aucun événement CalDAV à venir           │
└─────────────────────────────────────────────────┘
```

### Exemple 3 : Pas de calendrier configuré

```
┌─────────────────────────────────────────────────┐
│ Événements CalDAV à venir (Aujourd'hui et demain) │
├─────────────────────────────────────────────────┤
│      Aucun calendrier CalDAV configuré          │
└─────────────────────────────────────────────────┘
```

## 🔍 Informations techniques

### Récupération des événements

- **Source** : Serveurs CalDAV externes (Nextcloud, iCloud, etc.)
- **Période** : Du début d'aujourd'hui à la fin de demain (23:59:59)
- **Cache** : 10 minutes (même durée que l'agenda)
- **Filtrage** : 
  - Événements commençant aujourd'hui ou demain
  - Événements en cours (commencés avant mais pas encore terminés)

### Performance

Le widget utilise le même système de cache que l'affichage dans l'agenda :
- **Première visite** : Récupération depuis les serveurs CalDAV (~1-2 secondes)
- **Visites suivantes** : Lecture depuis le cache (<0.1 seconde)
- **Rafraîchissement** : Automatique toutes les 10 minutes

### Permissions

L'utilisateur doit avoir :
- ✅ Permission "Lire les actions/événements" dans l'agenda
- ✅ Accès aux calendriers CalDAV (système de visibilité)

Si l'utilisateur n'a pas les permissions, le widget affiche : "Permission non accordée"

## 🐛 Dépannage

### Le widget n'apparaît pas dans la liste

**Vérifier** :
1. Module CalDAV Client activé
2. Cache vidé : Outils > Purge du cache
3. Navigateur rafraîchi (Ctrl+F5 ou Cmd+Shift+R)

**Solution** :
```bash
# Vider le cache Dolibarr
rm -rf /usr/share/dolibarr/documents/install/temp/*

# Redémarrer le serveur web
sudo systemctl restart apache2
```

### Le widget affiche "Aucun calendrier configuré"

**Vérifier** :
1. Au moins une connexion CalDAV est active
2. Au moins un calendrier est activé
3. L'utilisateur a accès au(x) calendrier(s)

**Solution** :
- Configuration > Module CalDAV Client
- Vérifier les connexions et calendriers

### Le widget affiche "Permission non accordée"

**Problème** : L'utilisateur n'a pas les droits sur l'agenda.

**Solution** :
1. Utilisateurs & Groupes > Sélectionner l'utilisateur
2. Permissions > Agenda
3. Cocher "Lire les actions/événements"
4. Enregistrer

### Les événements n'apparaissent pas

**Vérifier** :
1. Les événements existent bien sur le serveur CalDAV
2. Les événements sont bien aujourd'hui ou demain
3. Le cache n'est pas corrompu

**Solution** :
```bash
# Vider le cache CalDAV
rm -rf /usr/share/dolibarr/documents/caldavclient/cache/*

# Rafraîchir la page
```

### Les événements sont en retard

**Problème** : Décalage horaire entre Dolibarr et le serveur CalDAV.

**Vérifier** :
1. Configuration > Société/Organisation > Options
2. "Fuseau horaire du serveur" et "Fuseau horaire utilisateur"
3. Doivent correspondre à votre fuseau réel

### Le widget est trop grand/petit

**Ajuster** :
1. Mode édition de la page d'accueil
2. Modifier le nombre maximum de lignes (3, 5, 10, 20)
3. Ou déplacer le widget dans une colonne plus/moins large

## 💡 Conseils d'utilisation

### Pour une vue optimale

- **Colonne de droite** : Parfait pour le widget (largeur idéale)
- **5 événements** : Bon compromis entre information et espace
- **Couleurs** : Utilisez des couleurs différentes pour distinguer les calendriers

### Cas d'usage

1. **Vue d'ensemble matinale** :
   - Ouvrir Dolibarr le matin
   - Voir immédiatement les événements du jour

2. **Planification** :
   - Voir les événements de demain pour anticiper
   - Préparer les réunions à l'avance

3. **Multi-calendriers** :
   - Personnel + Professionnel
   - Voir tous les événements en un coup d'œil

### Limitations

- ❌ **Pas d'interaction** : Le widget est en lecture seule
- ❌ **Pas de modification** : Pour modifier, aller sur le serveur CalDAV
- ❌ **Pas de création** : Créer les événements sur Nextcloud/iCloud/etc.
- ⏰ **Cache 10 min** : Les nouveaux événements peuvent mettre jusqu'à 10 minutes à apparaître

## 🔄 Mises à jour

### Rafraîchissement automatique

Le widget se rafraîchit automatiquement :
- À chaque chargement de la page d'accueil
- Si le cache a expiré (> 10 minutes)

### Rafraîchissement manuel

Pour forcer le rafraîchissement :
1. Vider le cache : Outils > Purge du cache
2. Ou attendre l'expiration du cache (10 min)

## 📞 Support

Si vous rencontrez des problèmes :

1. **Vérifier les logs** :
   ```bash
   tail -100 /chemin/vers/dolibarr.log | grep -i caldav
   ```

2. **Consulter la documentation** :
   - README.md : Guide général
   - CHANGELOG.md : Historique des versions

3. **Vérifier la configuration** :
   - Module CalDAV Client activé
   - Calendriers configurés et activés
   - Permissions utilisateur correctes

---

**Version** : 0.9.0  
**Auteur** : MATER Stéphane  
**Date** : 7 mars 2026
