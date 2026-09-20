# Module CalDAV Client pour Dolibarr

![Version](https://img.shields.io/badge/version-0.22.1-blue.svg)
![License](https://img.shields.io/badge/license-GPL--3.0%2B-green.svg)
![Dolibarr](https://img.shields.io/badge/Dolibarr-16.0%2B-orange.svg)

Module permettant à Dolibarr de **synchroniser** avec des calendriers externes via le protocole CalDAV (Nextcloud, iCloud, Google Calendar, etc.). Synchronisation bidirectionnelle complète avec trois modes configurables.

## 📋 Table des matières

- [Nouveautés v0.22.0](#-nouveautés-v0220)
- [Nouveautés v0.21.0](#-nouveautés-v0210)
- [Nouveautés v0.20.0](#-nouveautés-v0200)
- [Nouveautés v0.10.11](#-nouveautés-v01011)
- [Nouveautés v0.10.10](#-nouveautés-v01010)
- [Nouveautés v0.10.9](#-nouveautés-v0109)
- [Nouveautés v0.10.8](#-nouveautés-v0108)
- [Nouveautés v0.10.7](#-nouveautés-v0107)
- [Nouveautés v0.10.6](#-nouveautés-v0106)
- [Nouveautés v0.10.5](#-nouveautés-v0105)
- [Nouveautés v0.10.4](#-nouveautés-v0104)
- [Nouveautés v0.10.3](#-nouveautés-v0103)
- [Nouveautés v0.10.2](#-nouveautés-v0102)
- [Nouveautés v0.10.1](#-nouveautés-v0101)
- [Fonctionnalités](#-fonctionnalités)
- [Prérequis](#-prérequis)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Utilisation](#-utilisation)
- [Performance](#-performance)
- [Dépannage](#-dépannage)
- [Développement](#-développement)
- [Licence](#-licence)

## 🆕 Nouveautés v0.22.0

### Compatibilité Dolibarr 24 : les événements automatiques sont de nouveau masqués

Depuis Dolibarr **24.0**, l’option **« Masquer les événements automatiques système »** ne faisait plus effet : l’agenda se remplissait à nouveau de traces automatiques (envoi de devis, de facture, etc.).

Trois causes, toutes corrigées :

- Dolibarr 24 **remet à zéro** son propre filtre « événements non automatiques ». Le module ne s’appuie plus dessus.
- La **liste de l’agenda** a changé de requête (le dictionnaire des types s’appelle désormais `c` et non plus `ca`). Le filtre passe maintenant par le crochet `printFieldListWhere`, écrit pour ne dépendre d’aucun de ces deux noms.
- Le **calendrier** (vues mois / semaine / jour) pouvait ignorer le filtre ajouté. Un filet de sécurité retire désormais la tuile si un automatique passe quand même.

Rappel : l’option native de Dolibarr ne masque qu’un seul type (« Autre (auto) »). Celle du module masque **toute** la famille des automatiques système.

👉 L’option se trouve dans **CalDAV Client > Configuration**. Elle est sur **Non** par défaut.

### Bon à savoir sur le filet de sécurité

Le filtre principal se fait **dans la requête** : les événements automatiques ne sont pas lus du tout. C’est le cas courant, et il n’a aucun effet de bord.

Dans deux situations, le filtre repasse par le **filet de sécurité**, qui retire la tuile *après* l’affichage :

- quand vous filtrez l’agenda **par ressource** (le calendrier de Dolibarr construit alors une requête qui n’accepte pas notre filtre) ;
- si un événement automatique échappe malgré tout à la requête.

Dans ces cas, gardez en tête que :

- l’événement masqué **compte quand même** dans la limite d’affichage par journée (le « +N » en bas des cases). Une journée peut donc sembler vide tout en affichant un « +N » ;
- un très bref clignotement est possible avant que la tuile disparaisse ;
- si JavaScript est désactivé dans le navigateur, le filet ne fonctionne pas.

---

## 🆕 Nouveautés v0.21.0

### Sécurité des connexions CalDAV

- L’URL du serveur **doit être en `https://`** (HTTP est refusé).
- Le **certificat SSL est vérifié** par défaut (évite qu’un imposteur sur le réseau récupère le mot de passe).
- Dans **CalDAV Client > Configuration**, option **« Vérifier le certificat SSL »** : passez à **Non** uniquement pour un serveur de test avec certificat auto-signé.
- Après mise à jour : **désactiver puis réactiver** le module une fois.

---

## 🆕 Nouveautés v0.20.0

### Changement de numérotation

La numérotation passe en **0.20.0** (au lieu de continuer en `0.10.x`) pour éviter d’avoir un dernier chiffre qui monte à `10`, `11`, etc.  
Le fonctionnement du module ne change pas à cause de ce numéro : c’est uniquement une **version** plus lisible.

### Améliorations agenda (vue jour + thème sombre navigateur)

- **Vue jour** : placement des activités à cheval sur les heures (ex. 11h30→12h30) et meilleure gestion quand plusieurs tuiles sont sur la même heure (hauteur de ligne).
- **Mode sombre (navigateur)** : harmonisation des couleurs (numéro de semaine, fond des cases, week‑end, aujourd’hui, gouttières).

---

## 🆕 Nouveautés v0.10.11

### Exclusion des événements natifs “auto” vers CalDAV

Les événements **automatiques système** de Dolibarr (devis, signature, etc.) peuvent être **invisibles dans l’agenda** mais quand même présents dans `llx_actioncomm`.  
À partir de cette version, la synchronisation **Dolibarr → CalDAV** les **ignore** (filtre `llx_c_actioncomm.type = 'systemauto'` + sécurité sur les codes `*_AUTO`). 📖 **`CHANGELOG.md` [0.10.11]**.

---

## 🆕 Nouveautés v0.10.10

### Refactor : fichiers volumineux découpés (comportement identique)

Pour faciliter la maintenance, **`lib/caldav.lib.php`** et **`class/caldavsync.class.php`** sont découpés en **traits** (`lib/caldav/*.trait.php`, `class/caldavsync/*.trait.php`). Aucun changement d’API pour le reste du module : les `require_once` existants vers `caldav.lib.php` et `caldavsync.class.php` suffisent. 📖 **`CHANGELOG.md` [0.10.10]**.

---

## 🆕 Nouveautés v0.10.9

### Licence GNU GPL v3+ explicite

Le module est clairement identifié comme **GNU GPL version 3 ou ultérieure** (`GPL-3.0-or-later`) : fichiers **`LICENSE`** et **`COPYING`**, page **À propos**, langues, schémas SQL, CSS, CHANGELOG. 📖 **`CHANGELOG.md` [0.10.9]**.

---

## 🆕 Nouveautés v0.10.8

### Nom de classe distinct du module « fullcalendar »

La classe qui alimente le hook cœur **`updateFullcalendarEvents`** s’appelle désormais **`CaldavclientAgendaCalendarJsHook`** (fichier `class/agenda/caldavclient_agenda_calendar_js_hook.class.php`) pour ne pas prêter à confusion avec le **module Dolibarr tiers** du même nom. Le hook et le contexte **`fullcalendarinterface`** restent ceux de Dolibarr. 📖 **`CHANGELOG.md` [0.10.8]**.

---

## 🆕 Nouveautés v0.10.7

### Refactor du code des hooks agenda

Réorganisation interne du module : la logique lourde (script pied de page, tuiles, FullCalendar, agenda natif, filtre SQL liste) est déplacée dans des classes sous **`class/agenda/`** pour faciliter la maintenance — **aucun changement fonctionnel attendu** pour l’utilisateur. 📖 **`CHANGELOG.md` [0.10.7]**.

---

## 🆕 Nouveautés v0.10.6

### Week-end sur la vue « mois »

Sur le **calendrier mois**, le **samedi** et le **dimanche** ont une **couleur d’en-tête** différente des jours de semaine. Lorsqu’ils sont **à la suite** (cas le plus courant en France : semaine qui commence **lundi**), les **deux colonnes sont fusionnées** en une seule grande case week-end. 📖 **`CHANGELOG.md` [0.10.6]**.

---

## 🆕 Nouveautés v0.10.5

### Grille horaire en vue « jour »

En **vue jour** du calendrier Dolibarr, le module affiche un **tableau avec toutes les heures** (de minuit à 23 h) et une ligne **« Journée entière »** pour les événements sans heure précise. Les événements sont rangés dans la ligne correspondant à l’heure affichée sur la tuile (sans toucher au cœur de Dolibarr). Les **accents** du libellé « Journée entière » s’affichent correctement.

📖 **Détail** : `CHANGELOG.md` **[0.10.5]**.

---

## 🆕 Nouveautés v0.10.4

### Couleur des tuiles sur l’agenda (1 vs plusieurs utilisateurs)

Dans **CalDAV Client > Configuration**, vous pouvez activer la case **« Tuiles agenda : couleur selon l’utilisateur affecté »** : sur le calendrier Dolibarr (mois, semaine, jour), la **bande colorée à gauche** de l’événement reprend la **couleur de la fiche utilisateur** lorsqu’**un seul** utilisateur est affecté ; si **plusieurs** le sont, une **couleur dédiée** (réglable, orange brique par défaut) s’applique.

📖 **Détail** : `CHANGELOG.md` **[0.10.4]**.

---

## 🆕 Nouveautés v0.10.3

### En-tête du calendrier « mois »

**Version 0.10.3** peaufine la **vue mois** de l’agenda natif : la rangée des jours de la semaine reste une **bande pleine**, **tout en haut** de la zone qui défile, sans laisser voir les tuiles des jours qui passent **au-dessus** ou **entre** les colonnes. Le comportement s’appuie sur le CSS du module (`caldavclient_agenda.css`) : collage correct du `sticky`, suppression du padding haut gênant, en-tête opaque (y compris en mode sombre).

📖 **Détail technique** : voir `CHANGELOG.md` section **[0.10.3]**.

---

## 🆕 Nouveautés v0.10.2

### Interface agenda Dolibarr (sans modifier le cœur)

**Version 0.10.2** améliore l’intégration visuelle avec l’agenda natif et des options d’affichage.

| Élément | Description |
|--------|-------------|
| **CSS agenda** | Fichier `css/caldavclient_agenda.css` : calendrier (mois, semaine, jour) plus lisible, cellules en « tuiles », ombres sur les événements, adaptation sombre si le navigateur est en mode sombre. |
| **Réactivation module** | Obligatoire une fois après mise à jour : **désactiver** puis **réactiver** CalDAV Client pour que Dolibarr enregistre la feuille CSS (`MAIN_MODULE_*_CSS`). |
| **Masquer les auto** | Dans **CalDAV Client > Réglages**, option pour ne plus afficher les événements automatiques système (type `systemauto`, ex. propositions / mails auto). S’applique au **calendrier** et à la **liste** d’événements. |
| **Correctif hooks** | Les cases CalDAV et le filtre « auto » utilisent correctement le mécanisme `resprints` de Dolibarr. |

📖 **Détail technique** : voir `CHANGELOG.md` section **[0.10.2]**.

---

## 🆕 Nouveautés v0.10.1

### 🎉 SYNCHRONISATION COMPLÈTE + CORRECTIONS MULTI-UTILISATEURS

**Version 0.10.1** conserve la synchronisation complète introduite en 0.10.0 et corrige la diffusion des événements quand plusieurs utilisateurs sont affectés au même événement Dolibarr.

#### Trois modes de synchronisation

1. **Dolibarr → CalDAV (par défaut)** 
   - Dolibarr est maître
   - Les événements créés/modifiés dans Dolibarr sont envoyés au serveur CalDAV
   - Idéal pour gérer vos événements dans Dolibarr et les partager via CalDAV

2. **CalDAV → Dolibarr**
   - Le serveur CalDAV est maître
   - Les événements CalDAV sont importés automatiquement dans Dolibarr
   - Idéal pour centraliser vos calendriers externes dans Dolibarr

3. **Bidirectionnel**
   - Synchronisation dans les deux sens
   - Le dernier modifié gagne en cas de conflit
   - Idéal pour une collaboration complète

#### Nouvelles fonctionnalités

- ✅ **Stockage dans Dolibarr** : Les événements CalDAV sont maintenant stockés dans `llx_actioncomm`
- ✅ **Cron job automatique** : Synchronisation toutes les 15 minutes (configurable)
- ✅ **Synchronisation manuelle** : Bouton pour forcer une synchronisation immédiate
- ✅ **Mapping intelligent** : Table dédiée pour éviter les doublons
- ✅ **Gestion des conflits** : Résolution automatique basée sur les dates de modification
- ✅ **Gestion des suppressions** : Synchronisée selon le mode choisi
- ✅ **Interface admin dédiée** : Configuration complète de la synchronisation
- ✅ **Trigger Dolibarr** : Capture automatique des modifications d'événements

### ⚠️ MIGRATION depuis 0.9.1

**IMPORTANT** : Cette version change fondamentalement le module.

**Étapes de migration :**

1. **Sauvegarder** votre base de données Dolibarr
2. **Désactiver** le module CalDAV Client
3. **Réactiver** le module (crée la table `llx_caldav_event_mapping`)
4. **Configurer** le mode de synchronisation dans `CalDAV Client > Synchronisation`
5. **Activer** le cron job dans `Outils > Tâches planifiées`
6. **Tester** avec une synchronisation manuelle

📖 **Documentation complète** : Voir `CHANGELOG.md`

---

## ✨ Fonctionnalités

### Synchronisation
- ✅ **Trois modes de synchronisation** : Dolibarr→CalDAV / CalDAV→Dolibarr / Bidirectionnel
- ✅ **Synchronisation automatique** : Cron job configurable (15 min par défaut)
- ✅ **Synchronisation manuelle** : Bouton dans l'interface admin
- ✅ **Mapping intelligent** : Évite les doublons avec table `llx_caldav_event_mapping`
- ✅ **Gestion des conflits** : Le dernier modifié gagne (mode bidirectionnel)
- ✅ **Gestion des suppressions** : Synchronisée selon le mode
- ✅ **Support ETags CalDAV** : Détection des modifications distantes

### Gestion des calendriers
- ✅ **Connexion multi-serveurs** : Nextcloud, iCloud, Google Calendar, etc.
- ✅ **Découverte automatique** des calendriers disponibles
- ✅ **Affichage dans l'agenda Dolibarr** natif
- ✅ **Habillage visuel de l’agenda (v0.10.2+)** : CSS dédié + option pour masquer les événements automatiques système ; en-tête vue mois affiné en **v0.10.3** ; **v0.10.4** tuiles selon affectation ; **v0.10.5** grille horaire en vue jour ; **v0.10.6** week-end coloré / fusion sam.–dim. ; **v0.10.7** refactor code hooks (`class/agenda/`) ; **v0.10.8** renommage `CaldavclientAgendaCalendarJsHook` ; **v0.10.9** mentions **GPL v3+** ; **v0.10.10** découpage traits CalDAV / sync (voir [v0.10.10](#-nouveautés-v01010), [v0.10.9](#-nouveautés-v0109), [v0.10.8](#-nouveautés-v0108), [v0.10.7](#-nouveautés-v0107), [v0.10.6](#-nouveautés-v0106), [v0.10.5](#-nouveautés-v0105), [v0.10.4](#-nouveautés-v0104), [v0.10.3](#-nouveautés-v0103), [v0.10.2](#-nouveautés-v0102))
- ✅ **Configuration par calendrier** : couleur, nom, activation
- ✅ **Visibilité** : publics (tous) ou individuels (par utilisateur)
- ✅ **Gestion sécurisée** : mots de passe chiffrés

### Fonctionnalités
- ✅ **Événements toute la journée** : Support complet
- ✅ **Widget page d'accueil** : Affiche les événements à venir
- ✅ **Cache optimisé** : 10 minutes pour les requêtes CalDAV
- ✅ **Logs détaillés** : Débogage facilité
- ✅ **Interface admin complète** : Configuration et synchronisation
- ✅ **Trigger Dolibarr** : Capture automatique des modifications

### Serveurs CalDAV testés et supportés
- ✅ **Nextcloud** (versions 25+)
- ✅ **OwnCloud**
- ✅ **Apple iCloud**
- ✅ **Google Calendar** (avec CalDAV activé)
- ✅ **Radicale**
- ✅ **Baikal**
- ✅ **FastMail**
- ✅ Tout serveur compatible CalDAV (RFC 4791)

## 📦 Prérequis

- **Dolibarr** : Version 16.0 ou supérieure
- **PHP** : Version 7.4 ou supérieure
- **Extensions PHP requises** :
  - `curl` (pour les connexions HTTP)
  - `simplexml` (pour parser les réponses XML)
  - `mbstring` (pour le traitement des caractères)
- **Accès réseau** : Connexion sortante vers les serveurs CalDAV

## 🚀 Installation

### Méthode 1 : Installation manuelle

1. **Télécharger le module** dans le dossier custom de Dolibarr :
   ```bash
   cd /var/www/html/dolibarr/htdocs/custom
   git clone https://github.com/daryl40000/caldavclient.git
   ```

2. **Définir les permissions** :
   ```bash
   chown -R www-data:www-data caldavclient
   chmod -R 755 caldavclient
   ```

3. **Activer le module** :
   - Accéder à **Accueil > Configuration > Modules/Applications**
   - Rechercher "CalDAV Client"
   - Cliquer sur "Activer"

### Méthode 2 : Installation via l'interface Dolibarr

1. Accéder à **Accueil > Configuration > Modules/Applications**
2. Cliquer sur "Déployer un module externe"
3. Sélectionner le fichier ZIP du module
4. Cliquer sur "Envoyer"
5. Activer le module dans la liste

## ⚙️ Configuration

### 1. Ajouter une connexion CalDAV

1. Accéder à **Accueil > Configuration > Modules/Applications > CalDAV Client > Configuration**
2. Cliquer sur "Nouvelle connexion"
3. Remplir les informations :
   - **Nom** : Nom descriptif (ex: "Nextcloud Personnel")
   - **URL** : Adresse du serveur CalDAV
   - **Nom d'utilisateur** : Votre identifiant
   - **Mot de passe** : Votre mot de passe (chiffré automatiquement)
   - **Chemin du calendrier** : (optionnel, laissez vide pour auto-détection)
   - **Couleur** : Couleur par défaut pour les calendriers de cette connexion
   - **Activé** : Oui
   - **Actif par défaut** : Oui (afficher les calendriers par défaut)

### Exemples de configuration par serveur

#### Nextcloud
```
URL: https://nextcloud.example.com
Nom d'utilisateur: votre-email@example.com
Mot de passe: votre-mot-de-passe-applicatif
Chemin du calendrier: (laisser vide pour auto-détection)
```

#### iCloud
```
URL: https://caldav.icloud.com
Nom d'utilisateur: votre-identifiant-apple
Mot de passe: mot-de-passe-applicatif-icloud
Chemin du calendrier: (laisser vide)
```

**Note** : Pour iCloud, vous devez générer un mot de passe applicatif depuis appleid.apple.com

#### Google Calendar
```
URL: https://apidata.googleusercontent.com/caldav/v2/
Nom d'utilisateur: votre-email@gmail.com
Mot de passe: mot-de-passe-applicatif
Chemin du calendrier: (laisser vide)
```

**Note** : Activez l'API CalDAV dans les paramètres Google Calendar

### 2. Découvrir les calendriers

1. Depuis la page de configuration, cliquer sur "Voir les calendriers" à côté d'une connexion
2. Cliquer sur "Découvrir les calendriers"
3. Le module détectera automatiquement tous les calendriers disponibles
4. Les calendriers sont ajoutés à la liste

### 3. Configurer les calendriers

Pour chaque calendrier découvert :
1. Cliquer sur "Modifier"
2. Personnaliser :
   - **Nom d'affichage** : Nom visible dans l'agenda
   - **Couleur** : Couleur d'affichage des événements
   - **Visibilité** : Choisir qui peut voir ce calendrier
     - **Public** : Visible par tous les utilisateurs de l'entité (défaut)
     - **Individuel** : Visible uniquement par les utilisateurs sélectionnés
   - **Utilisateurs assignés** : Si visibilité individuelle, sélectionner les utilisateurs autorisés
   - **Activé** : Activer/désactiver le calendrier
   - **Actif par défaut** : Coché par défaut dans l'agenda

### 4. Gérer la visibilité des calendriers

Le système de visibilité vous permet de contrôler qui peut voir chaque calendrier :

#### Visibilité **Public**
- Le calendrier est visible par **tous les utilisateurs** de l'entité Dolibarr
- Idéal pour : calendriers d'entreprise, jours fériés, événements communs
- Exemple : "Congés équipe", "Jours fériés France"

#### Visibilité **Individuel**
- Le calendrier est visible **uniquement par les utilisateurs sélectionnés**
- Vous devez choisir dans la liste les utilisateurs qui auront accès
- Idéal pour : calendriers personnels, calendriers d'équipes spécifiques
- Exemple : "Calendrier commercial" (visible uniquement par l'équipe commerciale)

**Note** : Le changement de visibilité prend effet immédiatement. Les utilisateurs non assignés ne verront plus le calendrier dans leur agenda.

## 📅 Utilisation

### Dans l'agenda Dolibarr

1. Accéder à **Accueil > Agenda**
2. Les checkboxes des calendriers CalDAV apparaissent en haut
3. Cocher/décocher pour afficher/masquer les événements
4. Les événements s'affichent avec le format : **Titre - Serveur - Calendrier**

### Avec FullCalendar

1. Installer et activer le module FullCalendar de Dolibarr
2. Accéder à l'agenda FullCalendar
3. Les événements CalDAV s'affichent automatiquement
4. Utiliser les checkboxes pour filtrer par calendrier

### Format d'affichage

Les événements CalDAV sont affichés avec les informations suivantes :
- **Titre** : Nom de l'événement
- **Date et heure** : Date de début et de fin
- **Lieu** : Localisation (si renseignée)
- **Description** : Notes et détails
- **Provenance** : "Serveur CalDAV - Nom du calendrier"

### Création d'événements

**Important** : Ce module fonctionne en **lecture seule**. Vous pouvez :

1. **Créer des événements dans Dolibarr** normalement :
   - Accéder à **Accueil > Agenda > Nouvel événement**
   - Ces événements restent dans Dolibarr uniquement
   - Ils ne sont PAS envoyés vers les serveurs CalDAV

2. **Voir les événements CalDAV** :
   - Les événements de vos calendriers CalDAV externes s'affichent dans l'agenda
   - Ils sont en lecture seule (non modifiables depuis Dolibarr)
   - Modifiez-les directement sur le serveur CalDAV (Nextcloud, etc.)

### Légende

- 🔒 **Non éditable** : Les événements CalDAV sont affichés en lecture seule
- 🎨 **Colorés** : Chaque calendrier a sa propre couleur
- 🔄 **Mise à jour** : Rafraîchi automatiquement toutes les 10 minutes (cache)

### Widget page d'accueil

Le module inclut un widget pour afficher les événements CalDAV à venir sur la page d'accueil de Dolibarr.

#### Activation du widget

1. **Accéder à la page d'accueil** : Dolibarr > Accueil
2. **Mode édition** : Cliquer sur l'icône ⚙️ (paramètres) en haut à droite
3. **Ajouter un widget** : Cliquer sur "Ajouter un widget"
4. **Sélectionner** : Chercher "Événements CalDAV à venir"
5. **Enregistrer** : Le widget s'affiche maintenant sur votre page d'accueil

#### Fonctionnalités du widget

Le widget affiche les événements CalDAV pour **aujourd'hui et demain** :

- ✅ **Indicateur visuel** : Carré de couleur du calendrier
- ✅ **Jour** : "Aujourd'hui" ou "Demain"
- ✅ **Heure** : Heure de début (et de fin si disponible), ou "Toute la journée"
- ✅ **Titre** : Nom de l'événement
- ✅ **Tooltip** : Au survol, affiche le serveur CalDAV, le calendrier, le lieu et la description
- ✅ **Tri automatique** : Les événements sont triés par date de début
- ✅ **Limite configurable** : Par défaut, affiche jusqu'à 5 événements

#### Personnalisation

Vous pouvez configurer le nombre d'événements affichés :

1. Mode édition de la page d'accueil
2. Cliquer sur ⚙️ du widget "Événements CalDAV"
3. Modifier le paramètre "Nombre maximum de lignes"
4. Enregistrer

#### Exemple d'affichage

```
Événements CalDAV à venir (Aujourd'hui et demain)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🟦 Aujourd'hui  | 14:00-15:00  | Réunion d'équipe
🟩 Aujourd'hui  | Toute la journée | Formation
🟧 Demain       | 09:00-10:00  | Rendez-vous client
```

## ⚡ Performance

### Optimisations implémentées

Le module est optimisé pour offrir une excellente performance :

- **Mise en cache** : 10 minutes, réduit les requêtes HTTP de 80-90%
- **Parsing optimisé** : 40-50% plus rapide grâce aux regex optimisées
- **Logs conditionnels** : 10-15% plus rapide en production
- **Période réduite** : 75% moins de données transférées

### Métriques de performance

| Métrique | Avant optimisation | Après optimisation | Amélioration |
|----------|-------------------|-------------------|--------------|
| Temps de chargement (cache froid) | 2-4 sec | 0.5-1 sec | -70% |
| Temps de chargement (cache chaud) | 2-4 sec | 0.1-0.3 sec | -90% |
| Requêtes HTTP par chargement | 3-5 | 0-1 | -80% |
| Mémoire utilisée | ~10 MB | ~5 MB | -50% |

**Résultat global : 75-85% plus rapide !** 🚀

### Configuration avancée

Pour ajuster la durée du cache (fichier `lib/caldav.lib.php`) :
```php
$cache_duration = 900; // 15 minutes pour encore plus de performance
// ou
$cache_duration = 300; // 5 minutes pour plus de fraîcheur (valeur d'origine)
```

Pour désactiver le cache (déconseillé) :
```php
$events = $client->getEvents($calendar->url, $start, $end, false);
```

### Nettoyage manuel du cache

```bash
rm -rf /var/www/html/documents/caldavclient/cache/*
```

## 🔧 Dépannage

### Problème : "Aucun calendrier trouvé"

**Solutions** :
1. Vérifier que l'URL du serveur CalDAV est correcte
2. Vérifier les identifiants de connexion
3. Pour Nextcloud : utiliser un mot de passe applicatif
4. Consulter les logs Dolibarr : **Accueil > Configuration > Journaux système**

### Problème : "Les événements ne s'affichent pas"

**Solutions** :
1. Vider le cache : `rm -rf /documents/caldavclient/cache/*`
2. Vérifier que les calendriers sont activés dans la configuration
3. Vérifier que les checkboxes sont cochées dans l'agenda
4. Consulter les logs pour les erreurs de connexion

### Problème : "Erreur de connexion SSL"

**Solutions** :
1. Vérifier que l'URL commence bien par **https://**
2. Vérifier que le certificat SSL du serveur est valide (Let's Encrypt, etc.)
3. Pour un **certificat auto-signé** (machine de test uniquement) : dans la connexion CalDAV, mettre **Vérifier le certificat SSL** sur **Non**. Ne pas faire cela en production.

### Problème : "Les couleurs ne s'affichent pas correctement"

**Solutions** :
1. Vider le cache navigateur (Ctrl+F5)
2. Vérifier que les couleurs sont bien configurées dans la configuration des calendriers

### Activer les logs détaillés

1. **Accueil > Configuration > Autres > Journaux système**
2. Choisir **Niveau de log : Debug**
3. Reproduire le problème
4. Consulter les logs : **Accueil > Configuration > Journaux système > Afficher les logs**
5. Rechercher les lignes contenant "CalDAV"

### Erreurs courantes

| Erreur | Cause | Solution |
|--------|-------|----------|
| "Connection refused" | Serveur CalDAV inaccessible | Vérifier l'URL et le pare-feu |
| "Unauthorized 401" | Identifiants incorrects | Vérifier nom d'utilisateur et mot de passe |
| "Method not allowed 405" | Chemin incorrect | Laisser le chemin vide pour auto-détection |
| "SSL certificate problem" | Certificat SSL invalide | Corriger le certificat, ou désactiver la vérification SSL **uniquement** sur un serveur de test |

## 👨‍💻 Développement

### Structure du projet

```
caldavclient/
├── admin/                      # Pages d'administration
│   ├── index.php              # Redirection vers setup.php
│   └── setup.php              # Configuration des connexions et calendriers
├── class/                      # Classes PHP
│   ├── actions_caldavclient.class.php  # Hooks pour l'agenda
│   └── caldavcalendar.class.php        # Gestion des calendriers
├── core/
│   ├── modules/
│   │   └── modCalDAvClient.class.php   # Définition du module
│   └── triggers/
│       └── interface_99_modCalDAvClient_CalDAvClientTrigger.class.php
├── lib/
│   └── caldav.lib.php         # Client CalDAV et parsing iCal
├── sql/
│   ├── llx_caldav_connections.sql      # Table des connexions
│   └── llx_caldav_calendars.sql        # Table des calendriers
├── CHANGELOG.md               # Historique des versions
├── OPTIMISATIONS.md           # Documentation des optimisations
└── README.md                  # Ce fichier

```

### Base de données

#### Table `llx_caldav_connections`
Stocke les connexions aux serveurs CalDAV :
- `name` : Nom de la connexion
- `url` : URL du serveur CalDAV
- `username` : Nom d'utilisateur
- `password` : Mot de passe (chiffré)
- `calendar_path` : Chemin optionnel
- `color` : Couleur par défaut
- `enabled` : Connexion active
- `active_by_default` : Afficher par défaut

#### Table `llx_caldav_calendars`
Stocke les calendriers découverts :
- `fk_connection` : ID de la connexion parente
- `name` : Nom technique du calendrier
- `displayname` : Nom d'affichage
- `url` : URL du calendrier
- `color` : Couleur personnalisée
- `enabled` : Calendrier actif
- `active_by_default` : Coché par défaut dans l'agenda

### Hooks utilisés

- **`addCalendarChoice`** : Ajoute les checkboxes dans l'agenda
- **`getCalendarEvents`** : Fournit les événements pour l'agenda natif
- **`updateFullcalendarEvents`** : Fournit les événements pour la **vue agenda JavaScript** du cœur Dolibarr (tableau `$TEvent` ; hook cœur, distinct du module tiers « fullcalendar »)
- **`addCalendarJS`** : Ajoute le JavaScript pour gérer les checkboxes

### API CalDAV

Le module utilise les méthodes CalDAV standard :
- **`PROPFIND`** : Découverte des calendriers
- **`REPORT`** : Récupération des événements avec filtres de dates

### Format iCalendar

Propriétés supportées :
- `UID` : Identifiant unique
- `SUMMARY` : Titre de l'événement
- `DESCRIPTION` : Description
- `DTSTART` : Date de début
- `DTEND` : Date de fin
- `LOCATION` : Lieu
- `URL` : URL associée

### Contribuer

Les contributions sont les bienvenues ! 

1. Fork le projet
2. Créer une branche pour votre fonctionnalité
3. Commiter vos changements
4. Pousser vers la branche
5. Ouvrir une Pull Request

## 📄 Licence

Ce module est distribué sous **GNU General Public License version 3 ou toute version ultérieure** (**GPL-3.0+**, identifiant SPDX : **`GPL-3.0-or-later`**).

- Fichiers **`LICENSE`** et **`COPYING`** à la racine du module (même texte de référence).
- Texte intégral de la GPLv3 : [www.gnu.org/licenses/gpl-3.0.html](https://www.gnu.org/licenses/gpl-3.0.html)

Copyright (C) 2025-2026 MATER Stéphane

Ce programme est un logiciel libre ; vous pouvez le redistribuer et/ou le modifier selon les termes de la Licence publique générale GNU, telle que publiée par la Free Software Foundation ; soit la version 3 de la licence, soit (à votre choix) toute version ultérieure.

## 👨‍💻 Auteur

**MATER Stéphane** - Développement et maintenance

- Email : stephane@example.com
- GitHub : [daryl40000](https://github.com/daryl40000/caldavclient)

## 🙏 Remerciements

- Équipe Dolibarr pour le framework
- Communauté CalDAV pour les spécifications
- Tous les contributeurs du projet

## 📞 Support

- **Documentation** : Voir ce fichier README.md
- **Issues** : Ouvrir un ticket sur le dépôt Git
- **Forum Dolibarr** : https://www.dolibarr.org/forum

---

**Module CalDAV Client v0.9.1** - Mars 2026

Développé par **MATER Stéphane** avec ❤️ pour la communauté Dolibarr
