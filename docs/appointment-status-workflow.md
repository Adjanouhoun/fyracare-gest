# Cycle opérationnel des rendez-vous

## Transitions autorisées

- `PLANIFIE` vers `CONFIRME`, `HONORE` ou `ABSENT` ;
- `CONFIRME` vers `HONORE` ou `ABSENT` ;
- `HONORE`, `ABSENT` et `ANNULE` sont des états finaux ;
- l’annulation passe par la procédure dédiée avec motif obligatoire.

`HONORE` et `ABSENT` ne peuvent être sélectionnés qu’à partir de l’heure de début
du rendez-vous. Un paiement complet ne modifie plus le statut de présence.

Chaque transition conserve l’ancien état, le nouvel état, la date et
l’utilisateur dans `appointment_status_history`.

## Alertes

Le délai de rappel est réglable par un administrateur dans les paramètres de
l’entreprise. Sa valeur par défaut est de 24 heures.

Le tableau de bord, affiché après la connexion, présente :

- les rendez-vous planifiés entrant dans la fenêtre de confirmation ;
- les rendez-vous passés encore planifiés ou confirmés et devant être régularisés.

L’application ne confirme jamais automatiquement un rendez-vous : la confirmation
doit correspondre à une action réelle de la réception.
