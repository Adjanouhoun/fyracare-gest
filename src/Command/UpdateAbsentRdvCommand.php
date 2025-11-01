<?php

namespace App\Command;

use App\Entity\Rdv;
use App\Repository\RdvRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:rdv:update-absent',
    description: 'Marque automatiquement les rendez-vous passés non honorés comme ABSENT'
)]
class UpdateAbsentRdvCommand extends Command
{
    public function __construct(
        private RdvRepository $rdvRepository,
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche les RDV qui seraient modifiés sans les modifier')
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Nombre de jours dans le passé à vérifier (par défaut: 1)', 1)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $days = (int) $input->getOption('days');

        $io->title('Mise à jour des rendez-vous absents');

        if ($dryRun) {
            $io->note('Mode DRY-RUN activé : aucune modification ne sera effectuée');
        }

        // Date limite : maintenant
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
        
        // On peut aussi remonter dans le passé si l'option days est > 1
        $fromDate = $now->modify("-{$days} days");

        $io->info(sprintf('Recherche des RDV entre %s et maintenant...', $fromDate->format('d/m/Y H:i')));

        // Trouver tous les RDV passés qui ne sont ni HONORE ni ANNULE ni ABSENT
        $qb = $this->rdvRepository->createQueryBuilder('r')
            ->where('r.startAt < :now')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('now', $now)
            ->setParameter('statuses', [Rdv::S_PLANIFIE, Rdv::S_CONFIRME])
            ->orderBy('r.startAt', 'DESC');

        $rdvs = $qb->getQuery()->getResult();

        if (empty($rdvs)) {
            $io->success('Aucun rendez-vous à mettre à jour.');
            return Command::SUCCESS;
        }

        $io->section(sprintf('%d rendez-vous trouvé(s)', count($rdvs)));

        $table = [];
        $updated = 0;

        foreach ($rdvs as $rdv) {
            /** @var Rdv $rdv */
            $client = $rdv->getClient() ? $rdv->getClient()->getNometprenom() : 'Sans client';
            $prestation = $rdv->getPrestation() ? $rdv->getPrestation()->getLibelle() : 'Sans prestation';
            $date = $rdv->getStartAt()->format('d/m/Y H:i');
            $oldStatus = $rdv->getStatus();

            $table[] = [
                $client,
                $prestation,
                $date,
                $oldStatus,
                'ABSENT'
            ];

            if (!$dryRun) {
                $rdv->setStatus(Rdv::S_ABSENT);
                $updated++;
            }
        }

        // Afficher le tableau des modifications
        $io->table(
            ['Client', 'Prestation', 'Date', 'Ancien statut', 'Nouveau statut'],
            $table
        );

        if (!$dryRun) {
            $this->em->flush();
            $io->success(sprintf('%d rendez-vous marqué(s) comme ABSENT.', $updated));
        } else {
            $io->note(sprintf('%d rendez-vous seraient marqués comme ABSENT en mode normal.', count($rdvs)));
        }

        // Informations supplémentaires
        $io->section('📝 Pour programmer cette commande automatiquement :');
        $io->text([
            'Ajoutez cette ligne dans votre crontab (cron) :',
            '',
            '  # Tous les jours à 1h du matin',
            '  0 1 * * * cd /chemin/vers/votre/projet && php bin/console app:rdv:update-absent',
            '',
            'Ou utilisez le Scheduler Symfony :',
            '  https://symfony.com/doc/current/scheduler.html',
        ]);

        return Command::SUCCESS;
    }
}
