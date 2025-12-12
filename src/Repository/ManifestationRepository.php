<?php

namespace App\Repository;

use App\Entity\Manifestation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ManifestationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Manifestation::class);
    }
    
    public function findBySearchCriteria(?string $title = null, ?string $author = null, ?string $publisher = null, ?string $isbn = null)
    {
        $queryBuilder = $this->createQueryBuilder('m');
        
        if ($title) {
            $queryBuilder
                ->andWhere('m.title LIKE :title')
                ->setParameter('title', '%' . $title . '%');
        }
        
        if ($author) {
            $queryBuilder
                ->andWhere('m.author LIKE :author')
                ->setParameter('author', '%' . $author . '%');
        }
        
        if ($publisher) {
            $queryBuilder
                ->andWhere('m.publisher LIKE :publisher')
                ->setParameter('publisher', '%' . $publisher . '%');
        }
        
        if ($isbn) {
            $queryBuilder
                ->andWhere('m.isbn LIKE :isbn')
                ->setParameter('isbn', '%' . $isbn . '%');
        }
        
        return $queryBuilder
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function advancedSearch(
        ?string $q,
        ?string $title,
        ?string $identifier,
        ?string $external_id1,
        ?string $external_id2,
        ?string $external_id3,
        ?string $description,
        ?string $purchase_date_from,
        ?string $purchase_date_to
    ) {
        $qb = $this->createQueryBuilder('m');

        if ($q) {
            $qb->andWhere('m.title LIKE :q OR m.identifier LIKE :q OR m.description LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

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
                ->setParameter('purchase_date_from', $purchase_date_from);
        }

        if ($purchase_date_to) {
            $qb->andWhere('m.purchase_date <= :purchase_date_to')
                ->setParameter('purchase_date_to', $purchase_date_to);
        }

        return $qb->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}