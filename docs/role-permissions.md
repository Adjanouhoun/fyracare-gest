# Matrice des rôles

| Fonction | Réception + caisse | Manager | Administrateur |
|---|---:|---:|---:|
| Clients, agenda et rendez-vous | Oui | Oui | Oui |
| Confirmer, honorer, marquer absent | Oui | Oui | Oui |
| Encaisser et imprimer les reçus | Oui | Oui | Oui |
| Consulter la caisse du jour | Oui | Oui | Oui |
| Saisir une dépense | Oui | Oui | Oui |
| Injecter des fonds et clôturer la caisse | Non | Oui | Oui |
| Rapports mensuels, annuels et statistiques | Non | Oui | Oui |
| Annuler un paiement ou un rendez-vous | Non | Non | Oui |
| Prestations et catégories | Non | Non | Oui |
| Utilisateurs et paramètres | Non | Non | Oui |

`ROLE_USER` ne donne plus accès aux fonctions opérationnelles. Il reste le socle
technique commun et permet uniquement les fonctions de compte personnel.

Avant un déploiement, les comptes historiques ayant seulement `ROLE_USER` doivent
être reclassés explicitement par un administrateur.
