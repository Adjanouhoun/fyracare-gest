<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class UserAccountManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
    ) {
    }

    public function deactivate(User $target, User $author): void
    {
        if (!$target->isActive()) {
            throw new \DomainException('Ce compte est déjà désactivé.');
        }
        if ($target === $author) {
            throw new \DomainException('Vous ne pouvez pas désactiver votre propre compte.');
        }
        if (in_array('ROLE_ADMIN', $target->getRoles(), true) && $this->activeAdminCount() <= 1) {
            throw new \DomainException('Le dernier administrateur actif ne peut pas être désactivé.');
        }

        $target
            ->setIsActive(false)
            ->setDeactivatedAt(new \DateTimeImmutable())
            ->setDeactivatedBy($author);
        $this->em->flush();
    }

    public function reactivate(User $target): void
    {
        if ($target->isActive()) {
            throw new \DomainException('Ce compte est déjà actif.');
        }

        $target
            ->setIsActive(true)
            ->setDeactivatedAt(null)
            ->setDeactivatedBy(null);
        $this->em->flush();
    }

    private function activeAdminCount(): int
    {
        return count(array_filter(
            $this->users->findBy(['isActive' => true]),
            static fn (User $user): bool => in_array('ROLE_ADMIN', $user->getRoles(), true),
        ));
    }
}
