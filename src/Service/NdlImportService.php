<?php

namespace App\Service;

use App\Entity\Manifestation;
use Doctrine\ORM\EntityManagerInterface;

class NdlImportService
{
    private NdlSearchService $ndlSearchService;
    private EntityManagerInterface $entityManager;

    public function __construct(NdlSearchService $ndlSearchService, EntityManagerInterface $entityManager)
    {
        $this->ndlSearchService = $ndlSearchService;
        $this->entityManager = $entityManager;
    }

    /**
     * ISBN（生入力OK）からNDL検索→Manifestation作成→DB保存まで行う
     * 見つからない/不正な場合はnull
     */
    public function importByIsbn(string $rawIsbn): ?Manifestation
    {
        $isbn = preg_replace('/[^0-9X]/', '', $rawIsbn) ?? '';
        if ($isbn === '') {
            return null;
        }

        $bookData = $this->ndlSearchService->searchByIsbn($isbn);
        if ($bookData === null) {
            return null;
        }

        $manifestation = $this->ndlSearchService->createManifestation($bookData);

        $this->entityManager->persist($manifestation);
        $this->entityManager->flush();

        return $manifestation;
    }
}
