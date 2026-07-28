<?php

namespace App\Service;

use App\Entity\CompanySettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SettingsProvider
{
    public function __construct(
        private EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    private ?CompanySettings $cache = null;

    public function get(): CompanySettings
    {
        if ($this->cache) return $this->cache;

        $repo = $this->em->getRepository(CompanySettings::class);
        $settings = $repo->find(1);
        if (!$settings) {
            $settings = (new CompanySettings())
                ->setPhone('') ->setEmail('') ->setAddress(null)->setLogoAppPath(null)->setLogoInvoicePath(null)
                ->setCashClosureEnforcementStartDate(new \DateTimeImmutable('today'));
            $this->em->persist($settings);
            $this->em->flush();
        }
        return $this->cache = $settings;
    }

    public function getLogoAppPath(): ?string
    {
        $path = $this->get()->getLogoAppPath();
        if (!$path) {
            return null;
        }

        $relativePath = ltrim($path, '/');

        return is_file($this->projectDir.'/public/'.$relativePath) ? $relativePath : null;
    }
}
