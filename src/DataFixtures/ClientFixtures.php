<?php

namespace App\DataFixtures;

use App\Entity\Client;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class ClientFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        // Petits jeux de données fixes pour rester simple et compatible (pas de Faker requis)
        $prenoms = [
            'Adam','Aïcha','Alex','Alice','Amadou','Aminata','Anaïs','Arnaud','Assa','Binta',
            'Brice','Camille','Charles','Chloé','Cyril','Diane','Didier','Djeneba','Elise','Emma',
            'Fabrice','Fatou','Georges','Hawa','Hugo','Inès','Issa','Jules','Jérémy','Karim',
            'Khadija','Laetitia','Lamine','Léa','Lina','Luc','Maïmouna','Marc','Marie',
            'Moussa','Nadia','Nicolas','Océane','Oumar','Paul','Rama','Sarah','Souleymane','Théo',
            'Valentin','Yacine','Yao','Yasmine','Zoé'
        ];

        $noms = [
            'Diop','Ba','Barry','Traoré','Koné','Sy','Ndoye','Ndiaye','Camara','Sarr',
            'Martin','Dubois','Moreau','Laurent','Simon','Bernard','Durand','Petit','Robert','Richard',
            'Diallo','Coulibaly','Doumbia','Cissé','Touré','Kouyaté','Sow','Bah','Keita','Fofana'
        ];

        $notesPool = [
            "Client fidèle — rdv mensuel.",
            "Préfère contact WhatsApp.",
            "Pas de produits à base d’ammoniaque.",
            "Créneau matin en priorité.",
            "Apprécie les prestations rapides.",
            "Historique : ponctuel.",
            "OK pour rappel SMS.",
        ];

        $domaines = ['exemple.local','demo.fr','fyracare.local','client.test'];

        $now = new \DateTimeImmutable();

        // => 100 clients (modifie la borne si tu veux + ou -)
        for ($i = 1; $i <= 100; $i++) {
            $prenom = $prenoms[$i % count($prenoms)];
            $nom    = $noms[$i % count($noms)];
            $full   = sprintf('%s %s', $prenom, $nom);

            // téléphone simple (chaîne <= 255)
            $cc = ['+225', '+221', '+229', '+237', '+33'][ $i % 5 ];
            $telephone = sprintf('%s %02d-%02d-%02d-%02d', $cc, ($i*7)%100, ($i*9)%100, ($i*11)%100, ($i*13)%100);

            // email unique (<= 255)
            $local  = strtolower(preg_replace('/[^a-z0-9]+/i', '.', "{$prenom}.{$nom}"));
            $domain = $domaines[$i % count($domaines)];
            $email  = substr("{$local}.{$i}@{$domain}", 0, 250); // sécurité longueur

            // notes NON NULL (ton champ n’est pas nullable: Types::TEXT sans nullable=true)
            $notes = $notesPool[$i % count($notesPool)];

            // createdAt : \DateTime (MUTABLE) obligatoire
            $daysAgo = $i % 360;
            $createdAtImmutable = $now->sub(new \DateInterval('P'.$daysAgo.'D'));
            $createdAt = (new \DateTime())->setTimestamp($createdAtImmutable->getTimestamp());

            $client = (new Client())
                ->setNometprenom($full)
                ->setTelephone($telephone)
                ->setEmail($email)
                ->setNotes($notes)
                ->setCreatedAt($createdAt);

            $manager->persist($client);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['client'];
    }
}
