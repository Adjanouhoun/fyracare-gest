<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class SettingsLogoStorage
{
    private const TYPES = ['app', 'invoice'];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function store(UploadedFile $file, string $type): string
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Type de logo inconnu.');
        }

        $uploadsDir = $this->projectDir.'/public/uploads/settings';
        (new Filesystem())->mkdir($uploadsDir, 0755);

        if (!is_writable($uploadsDir)) {
            throw new \RuntimeException('Le dossier des logos n’est pas accessible en écriture.');
        }

        $extension = strtolower($file->guessExtension() ?: 'png');
        if (!in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            throw new \RuntimeException('Le format du logo n’est pas pris en charge.');
        }

        $filename = sprintf('logo-%s-%s.%s', $type, bin2hex(random_bytes(12)), $extension);
        $file->move($uploadsDir, $filename);

        return 'uploads/settings/'.$filename;
    }

    public function delete(?string $relativePath): void
    {
        if (!$relativePath) {
            return;
        }

        $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (!str_starts_with($normalized, 'uploads/settings/')) {
            // Les anciens fichiers restent conservés : rétrocompatibilité et
            // protection contre la suppression d’un chemin arbitraire.
            return;
        }

        $absolutePath = $this->projectDir.'/public/'.$normalized;
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}
