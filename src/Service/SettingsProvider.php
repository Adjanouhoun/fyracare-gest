<?php

namespace App\Service;

use App\Entity\CompanySettings;
use Doctrine\ORM\EntityManagerInterface;

class SettingsProvider
{
    public function __construct(private EntityManagerInterface $em) {}

    private ?CompanySettings $cache = null;

    public function get(): CompanySettings
    {
        if ($this->cache) return $this->cache;

        $repo = $this->em->getRepository(CompanySettings::class);
        $settings = $repo->find(1);
        if (!$settings) {
            $settings = (new CompanySettings())
                ->setPhone('') ->setEmail('') ->setAddress(null)->setLogoAppPath(null)->setLogoInvoicePath(null);
            $this->em->persist($settings);
            $this->em->flush();
        }
        return $this->cache = $settings;
    }
}
