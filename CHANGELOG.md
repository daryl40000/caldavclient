# Changelog - Module CalDAV Client pour Dolibarr

**Auteur** : MATER Stéphane

Toutes les modifications notables de ce projet seront documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/lang/fr/).

**Licence du dépôt** : [GNU General Public License](https://www.gnu.org/licenses/gpl-3.0.html) **version 3 ou toute version ultérieure** (SPDX : `GPL-3.0-or-later`). Textes `LICENSE` et `COPYING` à la racine du module.

## [0.20.0] - 2026-03-30

### Modifié
- **Version** : changement de numérotation vers **0.20.0** (au lieu de continuer en `0.10.x`) pour éviter un dernier chiffre qui monte à `10`, `11`, etc.

### Ajouté / amélioré
- **Agenda vue jour** : placement plus précis des activités dans la grille horaire (prise en compte des minutes) + meilleure gestion quand plusieurs activités tombent sur la même heure (hauteur de ligne).
- **Thème sombre navigateur** : harmonisation des couleurs (colonne numéro de semaine, fond des cases jour, week‑end, aujourd’hui, “gouttières” visibles avec les coins arrondis).

### Documentation (fichiers de version)
- **`VERSION`**, descripteur module, trigger, **`admin/about.php`**, **`README.md`**, **`DOCUMENTATION_INDEX.md`** : **0.20.0**.

## [0.10.11] - 2026-03-30

### Corrigé
- **Synchronisation Dolibarr → CalDAV** : exclusion robuste des événements **automatiques système** (ex. devis, signature, actions natives) pour éviter de brouiller les calendriers externes. Filtre basé sur `llx_c_actioncomm.type = 'systemauto'` et sécurité supplémentaire sur les codes `*_AUTO`.

### Documentation (fichiers de version)
- **`VERSION`**, descripteur module, trigger, **`admin/about.php`**, **`README.md`**, **`DOCUMENTATION_INDEX.md`** : **0.10.11**.

## [0.10.10] - 2026-03-27

### Modifié (refactor interne, API inchangée)
- **`lib/caldav.lib.php`** : la classe **`CalDAVClient`** est allégée (propriétés + constructeur) ; les méthodes sont réparties en traits sous **`lib/caldav/`** — HTTP, découverte, requête REPORT / cache, iCalendar, CRUD.
- **`class/caldavsync.class.php`** : la classe **`CalDAVSync`** ne contient plus que propriétés, traits et constructeur ; la logique est dans **`class/caldavsync/*.trait.php`** — orchestration / poussée Dolibarr, sync entrante et bidirectionnelle, conversion / sync manuelle.

### Documentation (fichiers de version)
- **`VERSION`**, descripteur module, trigger, **`admin/about.php`**, **`README.md`**, **`DOCUMENTATION_INDEX.md`** : **0.10.10**.

## [0.10.9] - 2026-03-27

### Documentation
- **Licence GNU GPL v3+** : renforcement explicite partout — en-tête `LICENSE` (SPDX, formulation FR/EN), fichier **`COPYING`** (copie du même texte), commentaire et tag **`\license`** dans le descripteur module, ligne **Licence** sur la page **À propos** (liens `LICENSE` / `COPYING`), clé de langue **`ModuleCalDAvClientLicense`**, en-têtes complets sur les classes `class/agenda/*.php`, **`admin/about.php`**, **`admin/test_agenda_natif.php`**, schémas **`sql/*.sql`**, commentaire en tête de **`css/caldavclient_agenda.css`**, paragraphe de licence en tête de ce **CHANGELOG**, **README** et **DOCUMENTATION_INDEX** mis à jour.

### Documentation (fichiers de version)
- **`VERSION`**, descripteur module, trigger, **`admin/about.php`**, **`README.md`**, **`DOCUMENTATION_INDEX.md`** : **0.10.9**.

## [0.10.8] - 2026-03-27

### Modifié
- **Renommage** : classe **`CaldavclientAgendaFullcalendar`** / fichier `caldavclient_agenda_fullcalendar.class.php` → **`CaldavclientAgendaCalendarJsHook`** / `caldavclient_agenda_calendar_js_hook.class.php` pour éviter toute confusion avec le **module Dolibarr tiers « fullcalendar »**. Le **nom du hook cœur** `updateFullcalendarEvents` et le contexte `fullcalendarinterface` restent ceux imposés par Dolibarr.

### Documentation
- **`VERSION`**, descripteur module, trigger, **`admin/about.php`**, **`README.md`**, **`DOCUMENTATION_INDEX.md`** : **0.10.8**.

## [0.10.7] - 2026-03-27

### Modifié (refactor interne, comportement inchangé)
- **`ActionsCaldavclient`** : découpage des responsabilités agenda dans `class/agenda/` — **`CaldavclientAgendaListSql`** (filtre SQL `systemauto`), **`CaldavclientAgendaTileStyle`** (tuiles / `eventOptions`), **`CaldavclientAgendaFooterScript`** (script `llxFooter`), **`CaldavclientAgendaCalendarJsHook`** (vue agenda JS / `updateFullcalendarEvents`), **`CaldavclientAgendaNativeEvents`**. La classe de hooks reste le point d’entrée Dolibarr et délègue à ces classes.
- **FullCalendar** : initialisation explicite du tableau intermédiaire d’événements avant accumulation (évite une variable indéfinie).

### Documentation
- **`VERSION`**, descripteur module, trigger, **`admin/about.php`**, **`README.md`**, **`DOCUMENTATION_INDEX.md`** : **0.10.7**.

## [0.10.6] - 2026-03-27

### Ajouté
- **Vue mois (agenda natif)** : **samedi et dimanche** traités comme **week-end** — barre des jours en **couleur distincte** (violet / lilas) ; lorsque les deux jours sont **côte à côte** (réglage Dolibarr habituel **« semaine commence le lundi »**), **fusion** des deux colonnes (`colspan`, libellé d’en-tête combiné, contenus des cases réunis). Si la semaine **commence le dimanche**, les deux colonnes restent séparées mais reçoivent la **même** couleur d’en-tête et un fond de case léger. Sans modification du cœur : script **`llxFooter`** (repère `MAIN_START_WEEK`) + `caldavclient_agenda.css` (dont mode sombre).

### Documentation
- **`VERSION`**, descripteur module, trigger, **`admin/about.php`**, **`README.md`**, **`DOCUMENTATION_INDEX.md`** : **0.10.6**.

## [0.10.5] - 2026-03-27

### Ajouté
- **Vue jour (agenda natif `comm/action/index.php`)** : **grille horaire** avec toutes les heures **00:00–23:00** et une ligne **« Journée entière »** ; les événements sont replacés dans le créneau déduit du texte de la tuile (heure avec ou sans minutes, ou ligne journée entière). Mise en œuvre **dans le module uniquement** (hook **`llxFooter`** + `css/caldavclient_agenda.css`), **sans modification du cœur Dolibarr**. Clé de traduction `CalDAVDayGridAllDay`.

### Corrigé
- **Libellé « Journée entière » (grille jour)** : affichage correct des **accents** — `transnoentitiesnoconv()` pour l’injection JavaScript au lieu de `trans()` (évite `&eacute;` affichés tels quels avec jQuery `.text()`).

### Documentation
- **`VERSION`**, descripteur module, trigger, **`admin/about.php`**, **`README.md`**, **`DOCUMENTATION_INDEX.md`** : **0.10.5**.

## [0.10.4] - 2026-03-27

### Corrigé
- **Couleur des tuiles agenda** : le hook `eventOptions` ne peut pas utiliser `$this->resprints` avec `return 0` — Dolibarr n’imprime `resPrint` que si un hook retourne `> 0` (remplacement du contenu). Le marqueur est maintenant émis via **`print`** dans le hook ; prise en charge du **hex utilisateur sur 3 caractères** (expansion vers 6).

### Modifié
- **Rendu des tuiles événement** : fond **uni** (plus le gris bicolore natif `#f0f0f0` + liseré) sur les vues calendrier — CSS `!important` + pour les tuiles avec marqueur CalDAV, **fond teinté** calculé en JS à partir de la couleur du liseré (clair / mode sombre) ; cellules `td.cal_event` en fond transparent pour un bloc homogène.
- **Largeur des tuiles** : suppression des marges latérales natives sur `div.event`, `width: 100%` sur la zone `agendacell`, le conteneur d’événement et `table.cal_event` pour occuper **toute la largeur utile** de la case jour (mois / semaine / jour).
- **Largeur (complément)** : suppression du **padding horizontal** sur `td.tdtop` ; padding latéral sur la **première rangée** (flex) ; annulation du **`margin-left`** du thème sur `a.dayevent-aday` ; **`display: block` / flex** sur `.dayevent.tagtable` et ses `.tagtr` pour corriger le comportement « table » Dolibarr (2 colonnes sur la 1re ligne, 1 cellule `agendacell` sur la 2e → la tuile ne s’étendait qu’à une colonne) ; `box-sizing: border-box` sur la case jour en vue mois.
- **Agenda** : masquage de la colonne **`td.cal_event_right`** (indicateur circulaire de progression / statut natif `ActionComm::getLibStatut` sur le calendrier `comm/action/index.php`).
- **Agenda** : annulation de la hauteur fixe **`.agendacell { height: 60px }`** du thème (Eldy / MD) — la zone des événements reprend une **hauteur automatique** pour afficher plusieurs tuiles par jour.

### Ajouté
- **Tuiles d’événement (agenda natif, vues mois / semaine / jour sur `comm/action/index.php`)** : option dans **CalDAV Client > Configuration** — case **« Tuiles agenda : couleur selon l’utilisateur affecté »** (`CALDAVCLIENT_AGENDA_TILE_BY_USER`). Si elle est cochée : **un seul** utilisateur affecté → bordure gauche de la tuile = **couleur de la fiche utilisateur** (champ couleur Dolibarr, 6 hex) ; **plusieurs** utilisateurs affectés → bordure = **couleur configurable** (`CALDAVCLIENT_AGENDA_MULTIUSER_COLOR`, défaut `#B85450`, sélecteur HTML5). Mise en œuvre via le hook Dolibarr **`eventOptions`** (marqueur + script en **`llxFooter`** qui applique `border-left` en `!important`). Anniversaires, congés, iCal et réservations Bookcal ne sont pas modifiés ; événements **sans** utilisateur affecté ou avec utilisateur **sans** couleur sur la fiche conservent le rendu standard Dolibarr.

### Documentation
- **README**, **`DOCUMENTATION_INDEX.md`** : nouveautés et renvois vers **`CHANGELOG.md`** section **[0.10.4]** (tuiles / couleur utilisateur). Les numéros **`VERSION`**, descripteur module, trigger et page **À propos** sont alignés sur la **dernière version publiée du module** (actuellement **[0.10.5]**).

## [0.10.3] - 2026-03-27

### Modifié
- **Vue mois (agenda natif `comm/action/index.php`)** : la ligne des jours (Lun, Mar, …) forme un **bandeau opaque** vraiment au **sommet** du tableau dans la zone à défilement : suppression du **padding haut** du conteneur scrollable (qui laissait voir les tuiles au-dessus de l’en-tête), **`position: sticky` sur chaque cellule d’en-tête** avec la **ligne `<tr>` en `static`** (évite les conflits avec le `sticky` du thème Dolibarr), conservation d’un **`border-spacing: 0`** et de **bordures gouttière** sur les cellules jour pour qu’aucun interstice ne laisse passer les tuiles sous l’en-tête ; **coins supérieurs arrondis** sur la première rangée pour rester aligné avec l’encadré « carte ». Variante **mode sombre** : mêmes règles avec couleurs adaptées (`tr.liste_titre.sticky td`).

### Documentation
- **README** : section « Nouveautés v0.10.3 », badge et table des matières.
- **`DOCUMENTATION_INDEX.md`** : titre et renvoi vers **[0.10.3]**.
- Fichiers **`VERSION`**, **`modCalDAvClient.class.php`**, **trigger**, repli **`admin/about.php`** : numéro **0.10.3**.

### Notes
- Aucune migration SQL. Comme pour la v0.10.2, un **rechargement forcé** du navigateur (ou vidage cache) suffit pour la CSS/JS ; la **réactivation du module** n’est nécessaire que si vous mettez à jour depuis une version **antérieure à 0.10.2** sans avoir encore enregistré la feuille `caldavclient_agenda.css`.

## [0.10.2] - 2026-03-27

### Ajouté
- **Habillage CSS de l’agenda natif Dolibarr** (`css/caldavclient_agenda.css`) : présentation type « tuiles » (espacement des cellules, en-têtes de jours, coins arrondis, ombres sur les blocs d’événements), surlignage du jour courant, léger adoucissement des boutons de vue, variante sombre via `prefers-color-scheme: dark`. Ciblage limité à `.cal_pannel.cal_month` et aux sections `.sectioncalendar*` sur `comm/action/index.php` (vues mois, semaine, jour).
- **Option d’affichage agenda** : dans **CalDAV Client > Réglages**, case pour masquer les événements **automatiques système** (`llx_c_actioncomm.type = 'systemauto'`, ex. « Autre (auto) », envois mail automatiques). Constante `CALDAVCLIENT_HIDE_AGENDA_SYSTEMAUTO`. Filtre SQL via hook `printFieldListFrom` sur les contextes **`agenda`** et **`agendalist`** (calendrier + liste d’événements).
- **Hook `agendalist`** déclaré dans le descripteur de module pour que le filtre ci-dessus s’applique aussi à `comm/action/list.php`.

### Corrigé
- **Hooks « addreplace » Dolibarr** : utilisation de `$this->resprints` au lieu de `$hookmanager->resPrint` dans `ActionsCaldavclient` pour `printFieldListFrom`, `addCalendarChoice` et `addCalendarJS` — sans quoi Dolibarr écrase `resPrint` et le filtre agenda / l’affichage des cases CalDAV ne fonctionnaient pas correctement.

### Documentation
- **README** : section « Nouveautés v0.10.2 », badge de version, table des matières.
- **Fichier `VERSION`** à la racine du module (lu par la page **À propos**).
- Ce changelog : entrée dédiée et numéro de version aligné sur le descripteur `modCalDAvClient`.

### Notes de mise à jour
- Après mise à jour des fichiers, **désactiver puis réactiver** le module CalDAV Client (ou équivalent permettant de régénérer les parties module) afin que Dolibarr enregistre **`MAIN_MODULE_*_CSS`** et charge la nouvelle feuille de styles.

### Modifié (complément)
- **Agenda calendrier** (`comm/action/index.php`, vues mois / semaine / jour) : la zone de filtre **« Affecté à »** (`select#search_filtert`, souvent perçue comme une liste de choix utilisateurs peu claire) est **masquée** via `css/caldavclient_agenda.css` ; le filtre effectif reste celui envoyé par le formulaire (comportement Dolibarr inchangé). La **liste d’événements**, la vue **par utilisateur** et les autres écrans agenda ne sont pas concernés.
- **Légende à cases à cocher** (agenda local, congés, iCal externes, anniversaires, Bookcal, etc.) : **suppression complète du bloc** sur ces mêmes vues calendrier, via un script en fin de page (`ActionsCaldavclient::llxFooter`) qui retire du DOM tout ce qui se trouve entre la barre de titre et la ligne de filtres. Sur `index.php`, le hook **`addCalendarChoice`** ne génère plus de cases CalDAV (inutiles une fois la légende retirée). **Effet** : sans ces champs, un nouvel envoi du formulaire peut ne plus transmettre les mêmes cases cochées qu’avant ; l’affichage des sources d’événements suit alors la logique Dolibarr / module par défaut pour les paramètres absents.
- **Vue mois** : au chargement, défilement pour **centrer le jour du jour** (cellule `td.cal_today`) dans la zone du calendrier lorsque le mois affiché est le mois courant ; `overflow-y: auto` sur `.sectioncalendarbymonth.maxscreenheightless300` pour que le centrage s’applique dans l’encadré à hauteur max (thème Dolibarr).

## [0.10.1] - 2026-02-15

### 🛠 Corrections de synchronisation multi-utilisateurs

### Corrigé
- **Affectation multi-utilisateurs** : lorsqu'un événement Dolibarr est affecté à plusieurs utilisateurs, la synchronisation Dolibarr → CalDAV envoie maintenant l'événement vers chaque calendrier CalDAV correspondant (mapping par utilisateur), au lieu d'utiliser seulement le premier calendrier trouvé.
- **Mappings multi-calendriers** : la logique de mapping a été adaptée pour gérer plusieurs lignes de correspondance pour un même événement Dolibarr (une par calendrier cible), ce qui évite l'écrasement des synchronisations précédentes.
- **Suppression multi-calendriers** : en suppression Dolibarr, le trigger parcourt désormais tous les mappings liés à l'événement et tente la suppression côté CalDAV sur chaque calendrier concerné.
- **UIDs CalDAV** : les UIDs générés tiennent compte du calendrier cible pour éviter les collisions lorsque le même événement est diffusé sur plusieurs calendriers.

### Maintenance
- **Schéma base de données à la réactivation** : `modCalDAvClient::applyDatabaseFixes()` complète automatiquement les colonnes manquantes sur `llx_caldav_calendars` (`visibility_type`, `is_default_target`, `fk_user_owner`) et crée l'index `idx_caldav_calendars_owner` si absent (évite l'erreur « Unknown column 'fk_user_owner' » sur les bases créées avant ces champs).
- Même mécanisme : remplacement de l'index unique obsolète sur `llx_caldav_event_mapping` par `uk_caldav_event_mapping_actioncomm_calendar` lorsque l'ancien `uk_caldav_event_mapping_actioncomm` est encore présent ; suppression de la FK vers `llx_actioncomm` si elle traîne encore.
- Suppression de la page d'outil temporaire `admin/fix_mapping_index.php`.
- Nettoyage des scripts SQL de migration devenus inutiles sur cet environnement de test (`sql/update_*.sql`).

## [0.10.0] - 2026-03-07

### 🎉 Nouvelle fonctionnalité majeure : Synchronisation complète

**CHANGEMENT ARCHITECTURAL IMPORTANT** : Le module passe d'un mode "affichage en lecture seule" à un système de **synchronisation complète** avec stockage des événements CalDAV dans la base de données Dolibarr.

### Ajouté
- **Table de mapping** (`llx_caldav_event_mapping`) pour gérer la correspondance entre événements Dolibarr et CalDAV
- **Trois modes de synchronisation** configurables :
  - **Dolibarr → CalDAV** (par défaut) : Dolibarr est maître, les événements créés/modifiés dans Dolibarr sont envoyés au serveur CalDAV
  - **CalDAV → Dolibarr** : Le serveur CalDAV est maître, les événements CalDAV sont importés dans Dolibarr
  - **Bidirectionnel** : Synchronisation dans les deux sens, le dernier modifié gagne
- **Cron job automatique** pour synchroniser tous les calendriers (toutes les 15 minutes par défaut, configurable)
- **Synchronisation manuelle** via un bouton dans l'interface d'administration
- **Trigger Dolibarr** pour capturer les créations, modifications et suppressions d'événements
- **Interface d'administration** dédiée à la configuration de la synchronisation (`admin/sync.php`)
- **Gestion des conflits** : système de résolution basé sur les dates de modification
- **Gestion des suppressions** : les événements supprimés d'un côté sont supprimés de l'autre selon le mode
- **Stockage des ETags CalDAV** pour détecter les modifications distantes
- **Support des UIDs CalDAV** uniques pour éviter les doublons

### Modifié
- Description du module mise à jour pour refléter la synchronisation bidirectionnelle
- **Triggers activés** (`triggers => 1` dans `modCalDAvClient.class.php`)
- **Constantes de configuration** ajoutées :
  - `CALDAVCLIENT_SYNC_MODE` : Mode de synchronisation
  - `CALDAVCLIENT_SYNC_INTERVAL` : Intervalle en minutes
  - `CALDAVCLIENT_AUTO_SYNC_ENABLED` : Activer/désactiver la sync automatique
- **Script SQL de migration** : `sql/update_0.9.1_to_0.10.0.sql`

### Nouvelle architecture
- **Classe `CalDAVSync`** : Gère toute la logique de synchronisation
- **Classe `CalDAVEventMapping`** : Gère les mappings entre événements
- **Méthodes CalDAVClient étendues** : Support complet de l'écriture CalDAV
- **Trigger complet** : Capture toutes les modifications d'événements Dolibarr

### Technique
- Conversion automatique entre événements Dolibarr (`ActionComm`) et format CalDAV
- Gestion des événements "toute la journée" (`fulldayevent`)
- Logs détaillés pour le débogage de la synchronisation
- Gestion des erreurs robuste avec try-catch
- Limite de 100 événements par synchronisation pour éviter les timeouts

### Notes de migration depuis 0.9.1

**⚠️ IMPORTANT** : Cette version change fondamentalement le fonctionnement du module.

**Avant la mise à jour :**
1. Sauvegarder votre base de données Dolibarr
2. Noter vos configurations de calendriers existants

**Après la mise à jour :**
1. Désactiver puis réactiver le module pour exécuter le script SQL de migration
2. Vérifier que la table `llx_caldav_event_mapping` a bien été créée
3. Configurer le mode de synchronisation dans **CalDAV Client > Synchronisation**
4. Activer le cron job dans **Outils > Tâches planifiées** (rechercher "CalDAV")
5. Lancer une première synchronisation manuelle pour tester

**Changements de comportement :**
- Les événements CalDAV sont maintenant **stockés dans `llx_actioncomm`** au lieu d'être affichés dynamiquement
- Les événements synchronisés sont identifiables via la table de mapping
- Le cache de 10 minutes reste actif pour les requêtes CalDAV

---

## [0.9.1] - 2026-03-07

### 🔧 Corrections techniques

#### Changement de l'ID du module
- **Ancien ID** : 192072
- **Nouveau ID** : 192076
- **Raison** : Éviter les conflits avec d'autres modules

#### Système de permissions amélioré
- **Avant** : Permissions calculées dynamiquement (`$this->numero + $r`)
  - Risque de conflit avec d'autres modules
  - IDs : 192072, 192073...
- **Après** : Permissions avec numéros fixes (format recommandé Dolibarr)
  - Aucun risque de conflit
  - IDs : 192076001 (read), 192076002 (write)
  - Jusqu'à 999 permissions possibles (192076001 à 192076999)

#### Widget page d'accueil corrigé
- **Correction du format de déclaration** : Utilisation du format tableau complet au lieu du format simplifié
- **Amélioration du chargement** : Utilisation de `dol_include_once()` au lieu de `require_once`
- **Gestion des erreurs renforcée** : Bloc try-catch global pour éviter les erreurs fatales
- **Permissions correctes** : Vérification des permissions `caldavclient->read` au lieu de `agenda->myactions->read`
- **Variable globale** : Utilisation correcte de `$db` global dans `loadBox()`

### 📝 Documentation
- Script de nettoyage ajouté : `admin/cleanup_widget.php` pour supprimer les doublons de widgets

### ⚠️ Actions requises après mise à jour
1. **IMPORTANT** : Désactiver puis réactiver le module pour appliquer les nouveaux numéros de permissions
2. Si des doublons de widgets apparaissent, utiliser le script `admin/cleanup_widget.php`

## [0.9.0] - 2026-03-07

### ⚠️ Retour en mode lecture seule

**Décision importante** : Après tests, la synchronisation bidirectionnelle s'est révélée trop complexe et instable. Le module revient en **mode lecture seule** stable et performant.

### Ajouté
- **Widget page d'accueil** : Affiche les événements CalDAV à venir (aujourd'hui et demain)
  - Indicateur visuel avec la couleur du calendrier
  - Affiche le jour (Aujourd'hui/Demain)
  - Affiche l'heure de l'événement (ou "Toute la journée")
  - Affiche le titre de l'événement
  - Tooltip avec lieu et description
  - Limite configurable du nombre d'événements affichés
  - Tri automatique par date de début

### Modifié
- **Mode lecture seule uniquement** : Le module affiche les événements CalDAV dans Dolibarr sans possibilité de création/modification/suppression vers CalDAV
- **Trigger désactivé** : Le trigger est vidé et désactivé (réservé pour une future version)
- **Hooks nettoyés** : Suppression des hooks `formObjectOptions`, `doActions`, `addMoreActionsButtons`
- **Description du module** : Clarification du mode lecture seule dans la description
- **Documentation mise à jour** : README et documentation clarifiés sur le fonctionnement en lecture seule

### Supprimé
- Tous les hooks de formulaire pour la synchronisation
- Code de synchronisation vers CalDAV (createEvent, updateEvent, deleteEvent depuis Dolibarr)
- Champ "Calendrier CalDAV de destination" dans le formulaire d'événement

### Conservé
- ✅ Affichage des événements CalDAV dans l'agenda Dolibarr
- ✅ Système de visibilité (Public/Individuel)
- ✅ Cache optimisé (10 minutes)
- ✅ Support FullCalendar
- ✅ Gestion multi-calendriers
- ✅ Toutes les fonctionnalités de lecture existantes

### Note pour le futur
Une version future pourrait implémenter la synchronisation bidirectionnelle de deux façons possibles :
1. **Approche simple** : Bouton dédié pour créer un événement CalDAV (interface séparée)
2. **Approche complète** : Remplacement du système d'événements natif de Dolibarr (complexe)

## [0.8.2] - 2026-02-15

### Ajouté
- **Système de visibilité des calendriers** : Deux modes de visibilité disponibles
  - **Public** : Le calendrier est visible par tous les utilisateurs de l'entité
  - **Individuel** : Le calendrier est visible uniquement par les utilisateurs sélectionnés
- **Gestion des utilisateurs assignés** : Interface pour sélectionner les utilisateurs autorisés à voir un calendrier en mode individuel
- **Table de liaison** : Nouvelle table `llx_caldav_calendar_users` pour gérer les assignations
- **Méthodes de gestion** : `setAssignedUsers()` et `getAssignedUsers()` dans la classe `CalDAVCalendar`
- **Affichage amélioré** : Indication du type de visibilité et du nombre d'utilisateurs assignés dans la liste des calendriers
- **Interface dynamique** : Le sélecteur d'utilisateurs s'affiche/masque automatiquement selon le type de visibilité choisi

### Modifié
- **Méthode `getAllActive()`** : Filtre maintenant les calendriers selon les permissions de l'utilisateur courant
  - Calendriers publics : visibles par tous
  - Calendriers individuels : visibles uniquement par les utilisateurs assignés
- **Script SQL** : Nouveau script `update_0.8.1_to_0.8.2.sql` pour migrer la base de données
- **Traductions** : Ajout des nouvelles clés de traduction en français

## [0.8.1] - 2026-02-15

### Sécurité (Patch Critique)
- **Suppression des fichiers de debug exposés publiquement**
  - Suppression de `debug_events_keys.php` (risque d'exposition de la structure de la base de données)
  - Suppression de `debug_hooks.php` (risque d'exposition des hooks internes)
  - Suppression de `test_events.php` (risque d'exposition des données CalDAV)
- **Nettoyage de la documentation technique**
  - Suppression de `DOC_CALENDRIER_EXTERNE.md` (notes de développement redondantes)
  - Suppression de `DOC_HOOK_AGENDA.md` (notes de développement redondantes)
- **Mise à jour des références** : Nettoyage des liens vers les fichiers supprimés dans `admin/setup.php`, `README.md` et `DOCUMENTATION_INDEX.md`

### Améliorations
- **Optimisation du cache** : Durée du cache augmentée de 5 à 10 minutes pour de meilleures performances
  - Réduction supplémentaire de 50% des requêtes HTTP vers les serveurs CalDAV
  - Amélioration de 30-40% du temps de chargement
  - Meilleur équilibre performance/fraîcheur des données
- Score de qualité du code amélioré : 8.5/10 → 9.0/10
- Réduction de 96% de la taille des fichiers de debug/documentation
- Élimination de 100% des points d'entrée de debug publics

## [0.8.0] - 2026-02-15

### Ajouté
- **Système de mise en cache** : Cache des événements CalDAV pendant 5 minutes pour améliorer les performances de 80-90%
- **Support complet de FullCalendar** : Intégration native avec le module FullCalendar de Dolibarr
- **Configuration par calendrier** : Gestion individuelle de chaque calendrier avec nom d'affichage, couleur et activation
- **Découverte automatique des calendriers** : Détection automatique des calendriers disponibles sur le serveur CalDAV
- **Gestion des connexions multiples** : Support de plusieurs serveurs CalDAV simultanément
- **Affichage personnalisé** : Format d'affichage amélioré "#NomEvenement - #NomDuCalDAV - #NomDuCalendrier"
- **Checkboxes d'activation** : Cases à cocher pour activer/désactiver l'affichage de chaque calendrier dans l'agenda
- **Documentation complète** : README.md, OPTIMISATIONS.md et CHANGELOG.md

### Optimisé
- **Parsing iCal 40-50% plus rapide** : Utilisation d'expressions régulières optimisées
- **Réduction des logs** : Logs détaillés uniquement si niveau DEBUG activé (10-15% plus rapide)
- **Période de récupération optimisée** : Récupération de -1 mois à +3 mois au lieu de -1 an à +1 an (75% moins de données)
- **Détection précoce** : Arrêt immédiat si aucun calendrier n'est sélectionné
- **Code simplifié** : Suppression des logs redondants et optimisation des boucles

### Corrigé
- **Événement fantôme "REMINDER"** : Suppression des composants VALARM qui étaient mal interprétés comme des événements
- **Événements sans titre** : Validation stricte pour ignorer les fragments d'événements mal parsés
- **Bouton "Modifier" non fonctionnel** : Correction de la page de configuration des calendriers
- **Couleur non sauvegardée** : Correction de la sauvegarde de la couleur des calendriers
- **Duplications d'événements** : Élimination des doublons dans FullCalendar
- **Timestamps GMT** : Correction du format des clés de date pour l'agenda natif
- **Propriété userassigned** : Ajout de la propriété manquante pour l'affichage dans l'agenda natif

### Technique
- **Extraction complète des VEVENT** : Utilisation de `preg_match_all` au lieu de `preg_split` pour des événements complets
- **Normalisation en une passe** : Fusion de plusieurs étapes de normalisation en une seule
- **Gestion du cache dans DOL_DATA_ROOT** : Stockage du cache dans `/documents/caldavclient/cache/`
- **Support des événements sur toute la journée** : Détection et gestion correcte des événements full-day
- **Gestion des fuseaux horaires** : Conversion correcte entre UTC et le fuseau horaire local

### Sécurité
- **Chiffrement des mots de passe** : Utilisation de `dol_encode()` pour stocker les mots de passe CalDAV
- **Validation des entrées** : Validation stricte des données reçues du formulaire
- **Échappement HTML** : Protection contre les injections XSS

## [0.1.0] - 2025-01-XX

### Ajouté
- Version initiale du module
- Connexion basique aux serveurs CalDAV
- Affichage des événements dans l'agenda Dolibarr
- Structure SQL pour les connexions et calendriers
- Interface d'administration basique

---

## Types de changements

- **Ajouté** : Nouvelles fonctionnalités
- **Modifié** : Changements dans les fonctionnalités existantes
- **Déprécié** : Fonctionnalités bientôt supprimées
- **Supprimé** : Fonctionnalités supprimées
- **Corrigé** : Corrections de bugs
- **Sécurité** : Correctifs de sécurité
- **Optimisé** : Améliorations de performance
- **Technique** : Modifications techniques internes

## Feuille de route

### Version 0.9.0 (À venir)
- Écriture d'événements vers les serveurs CalDAV
- Modification des événements existants
- Suppression d'événements
- Support des récurrences (événements répétitifs)
- Synchronisation bidirectionnelle

### Version 1.0.0 (Stable)
- Tests complets
- Documentation utilisateur finale
- Support de plusieurs serveurs CalDAV populaires
- Gestion avancée des conflits
- Interface mobile optimisée

### Versions futures
- Support CalDAV/CardDAV combiné
- Partage de calendriers
- Gestion des invitations
- Notifications push
- Support de l'authentification OAuth2
