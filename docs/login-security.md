# Sécurité de connexion

La connexion est limitée à cinq échecs sur une période de quinze minutes. La
limite combine l’adresse IP et l’identifiant saisi afin de ralentir les attaques
automatisées sans bloquer globalement tous les utilisateurs.

Après dépassement, Symfony refuse temporairement les nouvelles tentatives. Les
cookies de session sont inaccessibles au JavaScript (`HttpOnly`), transmis
uniquement en HTTPS en production (`Secure: auto`) et utilisent la politique
`SameSite=Lax`.

Les secrets et accès à la base doivent rester dans `.env.local` sur chaque
environnement. Ce fichier ne doit jamais être envoyé sur Git.
