<?php

namespace App\Repository;

use App\Entity\Manifestation;
use App\Entity\Reservation;
use App\Entity\Member;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function findOldestWaitingByManifestation(Manifestation $manifestation): ?Reservation
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.manifestation = :manifestation')
            ->andWhere('r.status = :status')
            ->setParameter('manifestation', $manifestation)
            ->setParameter('status', Reservation::STATUS_WAITING)
            ->orderBy('r.reserved_at', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findWaitingByManifestationAndMember(Manifestation $manifestation, Member $member): ?Reservation
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.manifestation = :manifestation')
            ->andWhere('r.member = :member')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('manifestation', $manifestation)
            ->setParameter('member', $member)
            ->setParameter('statuses', [Reservation::STATUS_WAITING, Reservation::STATUS_AVAILABLE])
            ->orderBy('r.reserved_at', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
