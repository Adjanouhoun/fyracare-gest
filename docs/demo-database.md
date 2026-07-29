# Base de démonstration FyraCare

Le fichier SQL de production a uniquement servi à confirmer le périmètre du
schéma. Aucune donnée personnelle ou financière réelle n'est copiée dans la
démonstration.

## Tables FyraCare retenues

- `user`
- `client`
- `prestation`
- `rdv`
- `payment`
- `cash_movement`
- `cash_closure`
- `expense_category`
- `company_settings`
- `doctrine_migration_versions`

## Tables explicitement exclues

Les tables AppGini, `membership_*`, ainsi que les tables de contenu d'un autre
site (`about_me`, `apropos`, `bienvenu`, `brochure`, `parametres`, `rendezvous`,
`services`, `slider`, `slogan`) ne font pas partie de FyraCare et ne sont jamais
créées ou importées par `app:demo:reset`.

## Garanties de sécurité

- La démo utilise exclusivement `var/demo/fyracare-demo.sqlite`.
- Le fichier est hors du répertoire web `public`.
- La commande de réinitialisation refuse tout environnement autre que `demo`.
- La commande vérifie le pilote SQLite et le chemin exact avant de supprimer un
  schéma.
- Les emails, téléphones, noms, notes et opérations générés sont fictifs.
- Le transport email de démonstration est désactivé.
