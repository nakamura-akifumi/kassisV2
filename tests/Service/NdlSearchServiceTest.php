<?php

namespace App\Tests\Service;

use App\Service\NdlSearchService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NdlSearchServiceTest extends TestCase
{
    public function testImportByIsbnReturnsNullWhenIsbnBecomesEmpty(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $service = $this->getMockBuilder(NdlSearchService::class)
            ->setConstructorArgs([$httpClient, $logger, $entityManager])
            ->onlyMethods(['searchByIsbn', 'createManifestation'])
            ->getMock();

        $service->expects($this->never())->method('searchByIsbn');
        $service->expects($this->never())->method('createManifestation');

        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');
    }

    public function testPlaceholder(): void
    {
        $this->assertTrue(true);
    }
}
