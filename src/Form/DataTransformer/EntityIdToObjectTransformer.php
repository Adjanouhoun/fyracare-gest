<?php

namespace App\Form\DataTransformer;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class EntityIdToObjectTransformer implements DataTransformerInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private string $className
    ) {}

    public function transform(mixed $value): mixed
    {
        if ($value === null) return '';
        if (!\is_object($value)) return (string) $value;

        $meta = $this->em->getClassMetadata($this->className);
        $ids  = $meta->getIdentifierValues($value);

        return $ids ? (string) reset($ids) : '';
    }

    public function reverseTransform(mixed $value): mixed
    {
        if ($value === null || $value === '') return null;

        $entity = $this->em->getRepository($this->className)->find($value);
        if (!$entity) {
            $short = (new \ReflectionClass($this->className))->getShortName();
            throw new TransformationFailedException(sprintf('%s #%s introuvable.', $short, (string) $value));
        }

        return $entity;
    }
}
