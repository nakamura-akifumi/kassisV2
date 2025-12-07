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
}