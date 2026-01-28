<?php

namespace App\Repository;

use App\Entity\Checkout;
use App\Entity\Manifestation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Checkout>
 */
class CheckoutRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Checkout::class);
    }

    public function findActiveByManifestation(Manifestation $manifestation): ?Checkout
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.manifestation = :manifestation')
            ->andWhere('c.status = :status')
            ->setParameter('manifestation', $manifestation)
            ->setParameter('status', Checkout::STATUS_CHECKED_OUT)
            ->orderBy('c.checked_out_at', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
