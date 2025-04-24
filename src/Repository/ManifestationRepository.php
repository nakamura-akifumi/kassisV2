<?php

namespace App\Repository;

use App\Entity\Manifestation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Manifestation>
 */
class ManifestationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Manifestation::class);
    }

    public function advancedSearch($q = null, $title = null, $identifier = null, $external_id1 = null,
                                   $external_id2 = null, $external_id3 = null, $description = null,
                                   $purchase_date_from = null, $purchase_date_to = null): array
    {
        $qb = $this->createQueryBuilder('m');

        // 単純な検索
        if ($q) {
            $qb->andWhere('m.title LIKE :q OR m.identifier LIKE :q OR m.external_identifier1 LIKE :q OR 
                       m.external_identifier2 LIKE :q OR m.external_identifier3 LIKE :q OR m.description LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        // 詳細検索
        if ($title) {
            $qb->andWhere('m.title LIKE :title')
                ->setParameter('title', '%' . $title . '%');
        }

        if ($identifier) {
            $qb->andWhere('m.identifier LIKE :identifier')
                ->setParameter('identifier', '%' . $identifier . '%');
        }

        if ($external_id1) {
            $qb->andWhere('m.external_identifier1 LIKE :external_id1')
                ->setParameter('external_id1', '%' . $external_id1 . '%');
        }

        if ($external_id2) {
            $qb->andWhere('m.external_identifier2 LIKE :external_id2')
                ->setParameter('external_id2', '%' . $external_id2 . '%');
        }

        if ($external_id3) {
            $qb->andWhere('m.external_identifier3 LIKE :external_id3')
                ->setParameter('external_id3', '%' . $external_id3 . '%');
        }

        if ($description) {
            $qb->andWhere('m.description LIKE :description')
                ->setParameter('description', '%' . $description . '%');
        }

        if ($purchase_date_from) {
            $qb->andWhere('m.purchase_date >= :purchase_date_from')
                ->setParameter('purchase_date_from', new \DateTime($purchase_date_from));
        }

        if ($purchase_date_to) {
            $qb->andWhere('m.purchase_date <= :purchase_date_to')
                ->setParameter('purchase_date_to', new \DateTime($purchase_date_to));
        }

        return $qb->orderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
