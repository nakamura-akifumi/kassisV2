<?php

namespace App\Tests\Repository;

use App\Entity\Manifestation;
use App\Repository\ManifestationRepository;
use App\Service\ManifestationSearchQuery;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ManifestationRepositoryTest extends KernelTestCase
{
    private ?ManifestationRepository $repository;
    private $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        $this->repository = $this->entityManager->getRepository(Manifestation::class);

        $this->initDatabase();

        // テストデータの投入
        $this->createManifestation('PHP Test Book', 'ID001', '2023-01-01', 'Description A');
        $this->createManifestation('Symfony Guide', 'ID002', '2023-06-01', 'Description B');
        $this->createManifestation('Database Design', 'ID003', '2024-01-01', 'Common Keyword');
        
        $this->entityManager->flush();
    }
    private function initDatabase(): void
    {
        $metaData = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metaData);
        $schemaTool->createSchema($metaData);
    }

    private function createManifestation(string $title, string $identifier, string $date, string $description): void
    {
        $m = new Manifestation();
        $m->setTitle($title);
        $m->setIdentifier($identifier);
        $m->setPurchaseDate(new \DateTime($date));
        $m->setDescription($description);
        $this->entityManager->persist($m);
    }

    public function testSearchByTitle(): void
    {
        $query = new ManifestationSearchQuery(title: 'PHP');
        $results = $this->repository->searchByQuery($query);

        $this->assertCount(1, $results);
        $this->assertEquals('PHP Test Book', $results[0]->getTitle());
    }

    public function testSearchByFullText(): void
    {
        // タイトルや説明に含まれるキーワードで検索
        $query = new ManifestationSearchQuery(q: 'Guide');
        $results = $this->repository->searchByQuery($query);

        $this->assertCount(1, $results);
        $this->assertEquals('Symfony Guide', $results[0]->getTitle());
    }

    public function testSearchByDateRange(): void
    {
        // 2023年内のものを検索
        $query = new ManifestationSearchQuery(
            purchaseDateFrom: '2023-01-01',
            purchaseDateTo: '2023-12-31'
        );
        $results = $this->repository->searchByQuery($query);

        $this->assertCount(2, $results);
    }

    public function testEmptyQueryReturnsAll(): void
    {
        $query = new ManifestationSearchQuery();
        $results = $this->repository->searchByQuery($query);

        $this->assertCount(3, $results);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null;
    }
}
