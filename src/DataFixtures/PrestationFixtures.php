<?php

namespace App\DataFixtures;

use App\Entity\Prestation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class PrestationFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $families = [
            'Coiffure', 'Esthétique', 'Bien-être', 'Massage', 'Barber', 'Onglerie',
            'Spa', 'Maquillage', 'Soin visage', 'Soin corps',
        ];

        $labels = [
            'Coupe classique', 'Coupe & Soin', 'Brushing', 'Coloration', 'Balayage',
            'Lissage', 'Permanente', 'Taille barbe', 'Rasage traditionnel',
            'Soin hydratant', 'Soin réparateur', 'Gommage', 'Pose vernis',
            'Manucure', 'Pédicure', 'Pose gel', 'Remplissage gel', 'Épilation sourcils',
            'Épilation jambes', 'Épilation aisselles', 'Épilation bras',
            'Massage relaxant', 'Massage tonique', 'Massage dos', 'Massage pierres chaudes',
            'Maquillage jour', 'Maquillage soirée', 'Rehaussement cils',
            'Soin anti-âge', 'Nettoyage de peau', 'Cuir chevelu', 'Brillance',
            'Brushing wavy', 'Coiffure événement', 'Chignon', 'Tresses',
            'Flash', 'Gloss', 'Botox capillaire', 'Keratine express',
        ];

        $descs = [
            'Prestation réalisée par un(e) expert(e).',
            'Inclut un diagnostic personnalisé et un conseil d’entretien.',
            'Utilisation de produits professionnels adaptés à votre type.',
            'Service rapide et soigné, confort assuré.',
            'Idéal avant une occasion spéciale.',
            'Entretien recommandé toutes les 4 à 6 semaines.',
        ];

        // Génère 60 prestations (>= 50 demandées)
        for ($i = 1; $i <= 60; $i++) {
            $fam = $families[array_rand($families)];
            $lab = $labels[array_rand($labels)];
            $libelle = sprintf('%s — %s', $fam, $lab);

            // Durée entre 15 et 180 minutes par pas de 15
            $duree = random_int(1, 12) * 15;

            // Prix en FCFA : entre 2.000 et 80.000 selon la famille/durée
            $baseByFamily = [
                'Coiffure'   => 5000,  'Esthétique' => 4000, 'Bien-être' => 6000,
                'Massage'    => 8000,  'Barber'     => 3000, 'Onglerie'  => 4000,
                'Spa'        => 12000, 'Maquillage' => 7000, 'Soin visage' => 9000,
                'Soin corps' => 10000,
            ];
            $base = $baseByFamily[$fam] ?? 5000;
            $prix = (int) max(2000, round($base + ($duree * random_int(60, 160)))); // simple calcul

            $desc = $descs[array_rand($descs)];
            // On enrichit un peu la description
            $description = sprintf(
                "%s\nDurée estimée : %d minutes.\nTarif indicatif : %s FCFA.",
                $desc,
                $duree,
                number_format($prix, 0, ',', ' ')
            );

            $p = (new Prestation())
                ->setLibelle($libelle)
                ->setDureeMin($duree)
                ->setPrix($prix)
                ->setIsActive((bool) random_int(0, 1))
                ->setDescription($description);

            $manager->persist($p);
        }

        $manager->flush();
    }

    /** Permet de charger uniquement ce fixture si besoin : --group=prestation */
    public static function getGroups(): array
    {
        return ['prestation'];
    }
}
