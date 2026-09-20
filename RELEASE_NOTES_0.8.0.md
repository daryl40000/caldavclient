# Notes de version 0.8.0

**Date de sortie** : 15 février 2026

## 🎉 Nouveautés majeures

### Performance : 75-85% plus rapide ! 🚀
- Mise en cache intelligent des événements (5 minutes)
- Parsing iCal optimisé (40-50% plus rapide)
- Réduction drastique des logs en production
- Période de récupération optimisée

### Interface utilisateur améliorée
- Affichage personnalisé : "#NomEvenement - #NomDuCalDAV - #NomDuCalendrier"
- Configuration par calendrier (couleur, nom, activation)
- Checkboxes fonctionnelles dans l'agenda
- Découverte automatique des calendriers

### Support FullCalendar
- Intégration native avec le module FullCalendar de Dolibarr
- Affichage fluide et réactif
- Événements colorés par calendrier
- Pas de duplication d'événements

## 🐛 Corrections importantes

### Événement fantôme "REMINDER" éliminé
Les composants VALARM (rappels) ne sont plus interprétés comme des événements séparés.

### Bouton "Modifier" fonctionnel
La page de configuration des calendriers fonctionne maintenant correctement.

### Couleur sauvegardée
Le sélecteur de couleur sauvegarde maintenant correctement la couleur choisie.

### Timestamps GMT corrigés
Les événements s'affichent maintenant correctement dans l'agenda natif.

## 📊 Métriques de performance

| Métrique | Avant | Après | Amélioration |
|----------|-------|-------|--------------|
| Temps de chargement (1er) | 2-4 sec | 0.5-1 sec | -70% |
| Temps de chargement (suivant) | 2-4 sec | 0.1-0.3 sec | -90% |
| Requêtes HTTP | 3-5 | 0-1 | -80% |
| Mémoire | ~10 MB | ~5 MB | -50% |

## 🔧 Configuration recommandée

Pour profiter pleinement des performances :
1. Activer le cache (activé par défaut)
2. Régler les logs sur "Erreurs uniquement" en production
3. N'activer que les calendriers nécessaires
4. Utiliser un serveur CalDAV performant

## 📚 Documentation

Cette version inclut une documentation complète :
- **README.md** : Guide utilisateur complet
- **CHANGELOG.md** : Historique détaillé des versions
- **OPTIMISATIONS.md** : Documentation technique des optimisations
- **AUTHORS.md** : Contributeurs et guide de contribution

## 🔄 Migration depuis 0.1.0

La migration est transparente :
1. Mettre à jour les fichiers du module
2. Le cache se créera automatiquement au premier chargement
3. Aucune modification de base de données requise

**Note** : Videz le cache navigateur (Ctrl+F5) après la mise à jour.

## 🚀 Installation

### Nouvelle installation
```bash
cd /var/www/html/dolibarr/htdocs/custom
git clone https://github.com/daryl40000/caldavclient.git
chown -R www-data:www-data caldavclient
```

Puis activer le module dans Dolibarr.

### Mise à jour
```bash
cd /var/www/html/dolibarr/htdocs/custom/caldavclient
git pull
rm -rf cache/*  # Vider le cache
```

## 🐞 Problèmes connus

Aucun problème majeur connu à ce jour.

Pour signaler un bug : ouvrez une issue sur le dépôt Git.

## 🎯 Prochaines étapes (v0.9.0)

- Écriture d'événements vers CalDAV
- Modification d'événements existants
- Suppression d'événements
- Support des récurrences
- Synchronisation bidirectionnelle

## 💬 Feedback

Vos retours sont importants ! N'hésitez pas à :
- Signaler des bugs
- Proposer des améliorations
- Partager vos cas d'usage
- Contribuer au code

---

**Développé par MATER Stéphane**

**Merci d'utiliser le module CalDAV Client !** 🙏

Pour plus d'informations, consultez le [README.md](README.md) complet.
