# Tarif historique des rendez-vous

Chaque rendez-vous conserve désormais le tarif applicable au moment de sa
création dans `rdv.price_at_booking`.

Une modification ultérieure du prix dans le catalogue des prestations ne change
donc plus :

- le montant total d’un ancien rendez-vous ;
- son reste à payer ;
- ses reçus ;
- son statut de paiement.

## Reprise des données existantes

La migration `Version20260728233000` applique les règles suivantes :

- rendez-vous marqués `PAYE` : le tarif historique est reconstitué avec la somme
  des paiements non annulés lorsque celle-ci reste cohérente avec le prix connu
  (entre 50 % et 200 % du prix catalogue) ;
- paiements fortement incohérents avec le catalogue : le prix catalogue est
  conservé comme valeur prudente, sans modifier ni effacer le paiement anormal ;
- autres rendez-vous : le prix actuel de la prestation est utilisé comme valeur
  initiale, car la base historique ne permet pas de connaître de manière certaine
  un éventuel ancien prix.

La migration est ciblée. Ne jamais utiliser `doctrine:schema:update --force` sur
la base mutualisée, car elle contient des tables sans rapport avec Fyracare.
