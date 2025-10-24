<?php
// src/Command/CheckInactivityCommand.php
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-inactivity',
    description: 'Affiche la configuration actuelle du système d\'inactivité'
)]
class CheckInactivityCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $timeout = (int) ($_ENV['APP_INACTIVITY_SECONDS'] ?? 900);
        $warning = (int) ($_ENV['APP_INACTIVITY_WARNING'] ?? 60);
        $enabled = (bool) ($_ENV['APP_INACTIVITY_ENABLED'] ?? true);
        $logoutMode = (bool) ($_ENV['APP_INACTIVITY_LOGOUT_MODE'] ?? false);

        $io->title('Configuration du Système d\'Inactivité');

        $io->table(
            ['Paramètre', 'Valeur'],
            [
                ['État', $enabled ? '✅ Activé' : '❌ Désactivé'],
                ['Timeout', $this->formatSeconds($timeout)],
                ['Avertissement', $this->formatSeconds($warning)],
                ['Mode', $logoutMode ? 'Déconnexion' : 'Verrouillage'],
            ]
        );

        if ($enabled) {
            $io->success('Le système d\'inactivité est actif.');
        } else {
            $io->warning('Le système d\'inactivité est désactivé.');
        }

        return Command::SUCCESS;
    }

    private function formatSeconds(int $seconds): string
    {
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes > 60) {
            $hours = floor($minutes / 60);
            $minutes = $minutes % 60;
            return sprintf('%d heure(s) %d minute(s)', $hours, $minutes);
        }

        return sprintf('%d minute(s) %d seconde(s)', $minutes, $remainingSeconds);
    }
}
