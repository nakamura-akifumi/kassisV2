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

    public function createManifestationByIsbn(string $isbn): ?Manifestation
    {
        $isbn = preg_replace('/[^0-9X]/', '', $isbn) ?? '';
        if ($isbn === '') {
            return null;
        }

        $bookData = $this->ndlSearchService->searchByIsbnSru($isbn);
        if ($bookData === null) {
            return null;
        }

        return $this->ndlSearchService->createManifestation($bookData);
    }

    public function findExistingByIsbn(string $rawIsbn): ?Manifestation
    {
        $normalizedIsbn = IsbnService::convertToIsbn13($rawIsbn);
        if ($normalizedIsbn === null) {
            return null;
        }

        $repository = $this->entityManager->getRepository(Manifestation::class);

        $existing = $repository->findOneBy(['external_identifier1' => $normalizedIsbn]);
        if ($existing !== null) {
            return $existing;
        }

        $rawIsbn = trim($rawIsbn);
        $existing = $repository->findOneBy(['identifier' => $rawIsbn]);
        if ($existing !== null) {
            return $existing;
        }

        return $repository->findOneBy(['identifier' => $normalizedIsbn]);
    }
    /**
     * ISBN（生入力OK）からNDL検索→Manifestation作成→DB保存まで行う
     * 見つからない/不正な場合はnull
     */
    public function importByIsbn(string $rawIsbn): ?Manifestation
    {
        $manifestation = $this->createManifestationByIsbn($rawIsbn);
        if ($manifestation === null) {
            return null;
        }
        $this->entityManager->persist($manifestation);
        $this->entityManager->flush();

        return $manifestation;
    }
}
