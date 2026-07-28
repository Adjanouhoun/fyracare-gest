# Traçabilité des encaissements

Chaque nouveau paiement conserve directement l’utilisateur qui a réalisé
l’encaissement. Cette information apparaît dans le détail du paiement, sur le
reçu affiché et sur le reçu PDF.

Lors de la migration, l’application récupère également le caissier des paiements
historiques lorsque leur mouvement de caisse associé possède déjà un auteur.
Les paiements plus anciens sans information fiable restent affichés comme tels :
aucun utilisateur n’est attribué artificiellement.
