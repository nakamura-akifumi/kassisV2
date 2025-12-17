<?php

namespace App\Tests\Service;

use App\Service\NdlImportService;
use App\Service\NdlSearchService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NdlImportServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        // テスト用DBを毎回クリーンに（SQLiteでも確実に回る）
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        if ($metadata !== []) {
            $tool = new SchemaTool($this->entityManager);
            $tool->dropSchema($metadata);
            $tool->createSchema($metadata);
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->entityManager->clear();
            $this->entityManager->getConnection()->close();
        }

        parent::tearDown();
    }

    public function testImportByIsbnReturnsBook(): void
    {
        $isbn = '9784003230817';

        $httpClient = static::getContainer()->get(HttpClientInterface::class);
        $logger = static::getContainer()->get(LoggerInterface::class);

        $ndlSearchService = new NdlSearchService($httpClient, $logger);
        $service = new NdlImportService($ndlSearchService, $this->entityManager);

        $manifestation = $service->importByIsbn($isbn);

        self::assertNotNull($manifestation);
    }
}
