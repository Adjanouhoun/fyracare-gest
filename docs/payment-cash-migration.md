# Migration paiement–caisse

La migration `Version20260728213000` :

- ajoute `cash_movement.payment_id` ;
- ajoute une clé étrangère nullable vers `payment` ;
- garantit l'unicité des numéros de reçu.

## Procédure obligatoire

1. Vérifier l'absence de reçus vides et de doublons.
2. Créer une sauvegarde MySQL complète et vérifier la fin du fichier.
3. Simuler avec `php bin/console doctrine:migrations:migrate --dry-run`.
4. Appliquer uniquement avec `php bin/console doctrine:migrations:migrate`.
5. Exécuter les tests et vérifier les contraintes.

## Interdiction importante

Ne jamais exécuter `doctrine:schema:update --force` sur cette base.

La base contient des tables étrangères à FyraCare. L'outil de mise à jour globale
du schéma propose de les supprimer puisqu'elles ne sont pas mappées par Doctrine.
Seules les migrations versionnées, relues et sauvegardées sont autorisées.

## Compatibilité historique

Les anciens mouvements ne possèdent pas de `payment_id`. Les repositories
conservent temporairement une recherche de repli dans leurs notes. Tous les
nouveaux paiements créent automatiquement la liaison structurée.
