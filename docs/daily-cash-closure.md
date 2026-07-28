# Clôture journalière de caisse

Une seule clôture est autorisée par date.

La caisse repart à zéro chaque matin. Son solde théorique correspond donc
uniquement aux entrées moins les sorties de la journée concernée ; aucun solde
des jours précédents n’est reporté.

Lors de la clôture, le manager saisit le solde réellement compté. L’application
recalcule et fige :

- les entrées de la journée ;
- les sorties de la journée ;
- le solde attendu ;
- le solde compté ;
- l’écart entre le compté et l’attendu ;
- la date, l’heure et le responsable.

Un solde compté supérieur ou inférieur au solde théorique reste autorisé afin de
refléter le comptage réel. L’écart est affiché avant validation, une confirmation
est demandée et une justification d’au moins cinq caractères devient obligatoire.

Après clôture, aucun paiement, dépense, injection ou annulation comptable ne peut
être enregistré pour cette même journée. La clôture elle-même ne crée plus de
faux mouvement de caisse à montant zéro.

Les corrections concernant une journée déjà clôturée doivent être enregistrées
comme une nouvelle opération explicative dans une journée ouverte.

## Si une clôture est oubliée

À partir de la date d’activation de ce contrôle, l’application recherche chaque
jour ayant des mouvements de caisse mais aucune clôture :

- une alerte rouge apparaît sur le tableau de bord ;
- le bouton de clôture ouvre automatiquement la plus ancienne journée oubliée ;
- toute nouvelle opération financière est bloquée tant que cette journée n’est
  pas clôturée ;
- le manager doit saisir le solde réellement compté et un motif de clôture
  tardive d’au moins cinq caractères ;
- la clôture conserve la date concernée, l’heure réelle de saisie, le responsable
  et le motif du retard.

Les données historiques antérieures à la date d’activation ne sont pas soumises
à cette obligation. Cela évite qu’un ancien historique incomplet bloque la caisse
au moment de la mise en production de cette évolution.
