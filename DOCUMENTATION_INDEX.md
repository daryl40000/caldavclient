# Index de la Documentation - Module CalDAV Client v0.22.0

Bienvenue dans la documentation complète du module CalDAV Client pour Dolibarr.

> **Licence** : [GNU GPL v3 ou ultérieure](https://www.gnu.org/licenses/gpl-3.0.html) (SPDX `GPL-3.0-or-later`) — fichiers `LICENSE` et `COPYING` à la racine du module.

> **Version actuelle** : voir le fichier `VERSION` et `CHANGELOG.md` (section **[0.21.0]** : HTTPS obligatoire + vérification SSL par défaut ; **[0.20.0]** : changement de numérotation + améliorations agenda ; **[0.10.11]** : exclusion sync des événements `systemauto` ; **[0.10.10]** : découpage `caldav.lib.php` / `caldavsync.class.php` en traits ; **[0.10.9]** : mentions licence ; **[0.10.8]** : renommage classe vue agenda JS ; **[0.10.7]** : refactor `class/agenda/` ; **[0.10.6]** : week-end vue mois ; **[0.10.5]** : grille horaire vue jour ; **[0.10.4]** : couleur des tuiles selon affectation ; **[0.10.3]** / **[0.10.2]** : en-tête vue mois, CSS agenda, légende, etc.).

## 📚 Documentation disponible

### Pour les utilisateurs

1. **[README.md](README.md)** - **COMMENCEZ ICI !**
   - Guide d'installation complet
   - Configuration pas à pas
   - Utilisation de l'agenda
   - Widget page d'accueil
   - Dépannage
   - FAQ

2. **[WIDGET.md](WIDGET.md)**
   - Guide complet du widget page d'accueil
   - Installation et configuration
   - Personnalisation
   - Exemples d'affichage
   - Dépannage spécifique au widget

3. **[RELEASE_NOTES_0.9.1.md](RELEASE_NOTES_0.9.1.md)** ⭐ **NOUVEAU**
   - Corrections techniques v0.9.1
   - Nouveau système de permissions
   - Widget corrigé
   - Guide de migration

4. **[RELEASE_NOTES_0.8.2.md](RELEASE_NOTES_0.8.2.md)**
   - Système de visibilité des calendriers
   - Gestion des permissions utilisateur
   
   **[RELEASE_NOTES_0.8.0.md](RELEASE_NOTES_0.8.0.md)**
   - Corrections de bugs
   - Métriques de performance

5. **[MIGRATION_0.8.2.md](MIGRATION_0.8.2.md)**
   - Guide de migration vers 0.8.2
   - Instructions SQL pour visibilité

6. **[CHANGELOG.md](CHANGELOG.md)**
   - Historique complet des versions
   - Toutes les modifications depuis la v0.1.0
   - Feuille de route des versions futures

### Pour les développeurs

7. **[CHANGELOG.md](CHANGELOG.md)**
   - Historique complet des versions
   - Toutes les modifications depuis la v0.1.0
   - Feuille de route des versions futures

### Pour les développeurs

8. **[OPTIMISATIONS.md](OPTIMISATIONS.md)**
   - Détails techniques des optimisations
   - Configuration avancée du cache
   - Métriques de performance détaillées
   - Évolutions possibles

9. **[FUTURE_WRITE_MODE.md](FUTURE_WRITE_MODE.md)** ⭐ **IMPORTANT**
   - Réflexion sur l'implémentation future de la synchronisation bidirectionnelle
   - Pourquoi la tentative a été abandonnée
   - Solutions possibles pour le futur
   - Recommandations techniques

10. **[AUTHORS.md](AUTHORS.md)**
   - Liste des contributeurs
   - Guide de contribution
   - Standards de code

### Informations légales

11. **[LICENSE](LICENSE)** et **[COPYING](COPYING)** (même texte de référence)
   - **GNU GPL v3 ou ultérieure** (SPDX `GPL-3.0-or-later`)
   - Termes et conditions

## 🗂️ Structure du projet

```
caldavclient/
├── 📖 Documentation
│   ├── README.md                      ⭐ Guide principal
│   ├── WIDGET.md                      📊 Widget page d'accueil (NOUVEAU)
│   ├── CHANGELOG.md                   📝 Historique
│   ├── OPTIMISATIONS.md              ⚡ Performance
│   ├── FUTURE_WRITE_MODE.md          🔮 Futur mode écriture (IMPORTANT)
│   ├── RELEASE_NOTES_0.8.2.md        🎉 Notes v0.8.2
│   ├── RELEASE_NOTES_0.8.0.md        🎉 Notes v0.8.0
│   ├── MIGRATION_0.8.2.md            📖 Migration v0.8.2
│   ├── AUTHORS.md                     👥 Contributeurs
│   ├── LICENSE                        ⚖️ Licence (GPL-3.0-or-later)
│   ├── COPYING                        ⚖️ Copie de la licence (GNU)
│   ├── VERSION                        🔢 Numéro de version
│   ├── .gitignore                     🚫 Fichiers ignorés
│   └── DOCUMENTATION_INDEX.md         📚 Ce fichier
│
├── 🔧 Administration
│   └── admin/
│       ├── index.php                  # Redirection
│       └── setup.php                  # Configuration
│
├── 💻 Code source
│   ├── class/                         # Classes PHP
│   │   ├── actions_caldavclient.class.php
│   │   ├── caldavsync.class.php       # Sync (traits dans caldavsync/)
│   │   ├── caldavsync/                # Traits CalDAVSync (outbound, inbound, support)
│   │   └── caldavcalendar.class.php
│   ├── core/modules/                  # Définition module
│   │   └── modCalDAvClient.class.php
│   ├── lib/                           # Bibliothèques
│   │   ├── caldav.lib.php             # Client CalDAV (traits dans caldav/)
│   │   └── caldav/                    # Traits CalDAVClient (HTTP, discovery, fetch, ical, crud)
│   └── sql/                           # Structure base de données
│       ├── llx_caldav_connections.sql
│       └── llx_caldav_calendars.sql
│
└── 📦 Fichiers système
    ├── index.php                      # Page d'accueil
    └── .gitignore                     # Configuration Git
```

## 🚀 Démarrage rapide

### Installation en 3 étapes

1. **Installer le module**
   ```bash
   cd /var/www/html/dolibarr/htdocs/custom
   git clone https://github.com/daryl40000/caldavclient.git
   chown -R www-data:www-data caldavclient
   ```

2. **Activer le module**
   - Dolibarr > Configuration > Modules > CalDAV Client > Activer

3. **Configurer une connexion**
   - Configuration > CalDAV Client > Nouvelle connexion
   - Renseigner URL, identifiants
   - Découvrir les calendriers

### Liens rapides

- [Guide d'installation détaillé](README.md#-installation)
- [Configuration par serveur](README.md#exemples-de-configuration-par-serveur)
- [Utilisation de l'agenda](README.md#-utilisation)
- [Dépannage](README.md#-dépannage)

## 📊 Points clés de la v0.9.1

### ✨ Fonctionnalités
- ✅ Support FullCalendar
- ✅ Gestion multi-calendriers
- ✅ **Système de visibilité** (Public/Individuel)
- ✅ **Mode lecture seule stable** (affichage uniquement)
- ✅ Découverte automatique
- ✅ Personnalisation complète

### ⚡ Performance
- 🚀 **75-85% plus rapide**
- 💾 Cache intelligent (10 min)
- 📉 50% moins de mémoire
- 🔄 80% moins de requêtes HTTP

### ⚠️ Important v0.9.1
- 📖 **Mode lecture seule uniquement**
- ✅ Affichage des événements CalDAV dans Dolibarr
- ✅ **Widget page d'accueil fonctionnel** (corrigé en v0.9.1)
- ✅ **Système de permissions sécurisé** (192076001, 192076002)
- ❌ Pas de création/modification/suppression vers CalDAV
- 💡 Une future version pourrait ajouter l'écriture (voir `FUTURE_WRITE_MODE.md`)

## 🆘 Besoin d'aide ?

1. **Consultez le README** : La plupart des réponses s'y trouvent
2. **Section Dépannage** : Erreurs courantes et solutions
3. **Logs Dolibarr** : Configuration > Journaux système
4. **Ouvrir une issue** : Sur le dépôt Git

## 🤝 Contribuer

Vos contributions sont les bienvenues ! Consultez [AUTHORS.md](AUTHORS.md) pour les détails.

## 📞 Contact et support

- **Documentation** : Ce dossier
- **Issues** : Dépôt Git
- **Forum Dolibarr** : https://www.dolibarr.org/forum

---

**Module CalDAV Client v0.9.1** - Mars 2026

Développé par **MATER Stéphane** avec ❤️ pour la communauté Dolibarr
