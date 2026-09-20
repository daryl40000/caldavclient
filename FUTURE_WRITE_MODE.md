# Note pour une future version avec synchronisation bidirectionnelle

**Version actuelle** : 0.9.0 (lecture seule)  
**Date** : 7 mars 2026

## Pourquoi la synchronisation bidirectionnelle a été abandonnée

La tentative d'implémentation de la synchronisation (lecture + écriture) vers CalDAV s'est heurtée à plusieurs problèmes :

### Problèmes techniques rencontrés

1. **Timing des hooks**
   - Les hooks Dolibarr ne garantissent pas l'ordre d'exécution
   - Les variables globales ne persistent pas toujours entre hooks et triggers
   - Le formulaire FullCalendar utilise AJAX, ce qui complique la transmission de données

2. **Complexité de l'intégration**
   - Dolibarr n'utilise pas `array_options` automatiquement pour les événements
   - Les extrafields nécessitent une configuration SQL supplémentaire
   - Le système de hooks n'est pas conçu pour modifier les formulaires existants

3. **Risques de conflits**
   - Boucles de synchronisation potentielles (Dolibarr ↔ CalDAV)
   - Gestion des conflits de modification simultanée
   - Événements dupliqués ou corrompus

## Solutions possibles pour le futur

### Solution 1 : Interface séparée (RECOMMANDÉE)

**Principe** : Créer une interface dédiée pour gérer les événements CalDAV, séparée de l'agenda natif de Dolibarr.

**Avantages** :
- ✅ Pas de conflit avec le système natif
- ✅ Interface sur mesure adaptée à CalDAV
- ✅ Contrôle total sur la création/modification/suppression
- ✅ Peut coexister avec les événements Dolibarr natifs

**Implémentation** :
1. **Nouveau menu** : "Événements CalDAV" dans le module
2. **Page de gestion** : `/custom/caldavclient/events.php`
3. **Formulaire dédié** : Création/modification d'événements CalDAV
4. **Liste des événements** : Affichage des événements créés via le module
5. **Synchronisation** : Bouton "Rafraîchir" pour synchroniser avec le serveur

**Structure** :
```
caldavclient/
├── events.php              # Liste des événements CalDAV
├── event_card.php          # Fiche événement CalDAV
├── event_create.php        # Création événement CalDAV
├── class/
│   └── caldavevent.class.php  # Nouvelle classe pour gérer les événements
```

**Flux utilisateur** :
1. Accéder à "Événements CalDAV" dans le menu
2. Cliquer sur "Créer un événement CalDAV"
3. Remplir le formulaire (titre, date, calendrier, etc.)
4. Enregistrer → Créé directement sur le serveur CalDAV
5. L'événement apparaît dans la liste ET dans l'agenda Dolibarr

### Solution 2 : Remplacement complet du système natif (COMPLEXE)

**Principe** : Remplacer entièrement le système d'événements de Dolibarr par une gestion CalDAV.

**Avantages** :
- ✅ Expérience utilisateur unifiée
- ✅ Un seul système pour tous les événements

**Inconvénients** :
- ❌ Très complexe à implémenter
- ❌ Risque de conflit avec le core de Dolibarr
- ❌ Nécessite de surcharger de nombreux fichiers
- ❌ Maintenance difficile lors des mises à jour Dolibarr
- ❌ Perte des événements existants dans Dolibarr

**Non recommandé** pour un module externe.

### Solution 3 : Extrafields + Hooks améliorés (MOYEN)

**Principe** : Utiliser le système d'extrafields de Dolibarr pour ajouter un champ "Synchroniser vers CalDAV".

**Avantages** :
- ✅ Utilise les mécanismes standards de Dolibarr
- ✅ Intégration dans le formulaire natif

**Inconvénients** :
- ❌ Nécessite une configuration manuelle des extrafields
- ❌ Complexité pour l'utilisateur final
- ❌ Toujours des problèmes de timing avec les triggers

**Implémentation** :
1. Créer un extrafield `caldav_sync_calendar` via SQL
2. Utiliser un trigger APRÈS la création (not BEFORE)
3. Lire l'extrafield dans le trigger
4. Synchroniser vers CalDAV

**Problème principal** : Les extrafields ne sont pas toujours bien gérés avec les hooks.

## Recommandation

**Solution 1 (Interface séparée)** est la meilleure approche :

### Phase 1 : Interface de gestion
- Créer une page `/custom/caldavclient/events.php`
- Lister les événements CalDAV (depuis le serveur, pas Dolibarr)
- Boutons : Créer, Modifier, Supprimer
- Formulaire dédié pour la création/modification

### Phase 2 : Intégration dans l'agenda
- Les événements créés via l'interface apparaissent dans l'agenda Dolibarr
- Utiliser les hooks existants (`getCalendarEvents`) pour les afficher
- Ajouter un indicateur visuel (icône) pour distinguer les événements CalDAV

### Phase 3 : Synchronisation bidirectionnelle
- Rafraîchissement automatique depuis CalDAV
- Détection des modifications externes
- Gestion des conflits

## Exemple de code pour la Solution 1

### Nouvelle classe `CalDAVEvent`

```php
class CalDAVEvent extends CommonObject
{
    public $id;
    public $uid;              // UID CalDAV
    public $fk_calendar;      // ID du calendrier CalDAV
    public $title;
    public $description;
    public $location;
    public $date_start;
    public $date_end;
    public $all_day;
    public $status;
    
    /**
     * Créer un événement sur le serveur CalDAV
     */
    public function create($user)
    {
        // 1. Charger le calendrier
        $calendar = new CalDAVCalendar($this->db);
        $calendar->fetch($this->fk_calendar);
        
        // 2. Charger la connexion
        $connection = new CalDAVConnection($this->db);
        $connection->fetch($calendar->fk_connection);
        
        // 3. Créer le client CalDAV
        $client = new CalDAVClient($connection);
        
        // 4. Préparer les données
        $event_data = array(
            'uid' => $this->uid ? $this->uid : $this->generateUID(),
            'summary' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'start' => $this->date_start,
            'end' => $this->date_end,
            'all_day' => $this->all_day,
        );
        
        // 5. Créer sur CalDAV
        $result = $client->createEvent($event_data);
        
        if ($result) {
            dol_syslog("CalDAV Event créé : ".$this->uid);
            return 1;
        } else {
            $this->error = "Échec de la création sur CalDAV";
            return -1;
        }
    }
    
    /**
     * Mettre à jour un événement
     */
    public function update($user)
    {
        // Similar to create but uses updateEvent()
    }
    
    /**
     * Supprimer un événement
     */
    public function delete($user)
    {
        // Uses deleteEvent()
    }
    
    /**
     * Récupérer tous les événements d'un calendrier
     */
    public function fetchAll($fk_calendar, $date_start = null, $date_end = null)
    {
        // Récupérer depuis le serveur CalDAV
        // Pas de stockage en base de données
    }
}
```

### Page de gestion `events.php`

```php
<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/class/caldavevent.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/class/caldavcalendar.class.php';

// Permissions
if (!$user->rights->agenda->myactions->read) {
    accessforbidden();
}

// Paramètres
$action = GETPOST('action', 'alpha');
$id = GETPOST('id', 'int');

// Actions
if ($action == 'create') {
    // Redirection vers event_create.php
}

// Affichage
llxHeader('', 'Événements CalDAV');

print '<h1>Événements CalDAV</h1>';

// Bouton créer
print '<a class="butAction" href="event_create.php">Créer un événement CalDAV</a>';

// Liste des calendriers avec leurs événements
$calendar = new CalDAVCalendar($db);
$calendars = $calendar->getAllActive($conf->entity, $user->id);

foreach ($calendars as $cal) {
    print '<h2>'.$cal->name.'</h2>';
    
    // Récupérer les événements
    $event = new CalDAVEvent($db);
    $events = $event->fetchAll($cal->id);
    
    // Afficher la liste
    print '<table class="border">';
    foreach ($events as $evt) {
        print '<tr>';
        print '<td>'.$evt->title.'</td>';
        print '<td>'.dol_print_date($evt->date_start, 'dayhour').'</td>';
        print '<td><a href="event_card.php?id='.$evt->uid.'">Voir</a></td>';
        print '</tr>';
    }
    print '</table>';
}

llxFooter();
```

## Conclusion

Pour implémenter la synchronisation bidirectionnelle dans une future version :

1. **Adopter la Solution 1** (Interface séparée)
2. **Créer une classe `CalDAVEvent`** dédiée
3. **Développer une interface de gestion** séparée
4. **Conserver l'affichage dans l'agenda** via les hooks existants
5. **Pas de modification du core de Dolibarr**

Cette approche est :
- ✅ Simple à implémenter
- ✅ Stable et maintenable
- ✅ Sans conflit avec Dolibarr
- ✅ Extensible pour de futures fonctionnalités

---

**Version actuelle** : 0.9.0 (lecture seule stable)  
**Version future** : 1.0.0 (avec interface d'écriture dédiée)  
**Date** : 7 mars 2026
