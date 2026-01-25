<?php

namespace App\Tests\Service;

use App\Service\NumberingService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class NumberingServiceTest extends TestCase
{
    public function testGenerateNumberingInsertsWhenMissing(): void
    {
        $connection = $this->createMock(Connection::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('fetchAssociative')
            ->with(
                $this->stringContains('FROM code'),
                $this->equalTo(['numbering', 'manifestation_identifier'])
            )
            ->willReturn(false);
        $connection->expects($this->once())->method('insert')
            ->with(
                $this->equalTo('code'),
                $this->callback(function (array $data): bool {
                    return $data['type'] === 'numbering'
                        && $data['identifier'] === 'manifestation_identifier'
                        && $data['value'] === 1
                        && array_key_exists('created_at', $data)
                        && array_key_exists('updated_at', $data);
                })
            );
        $connection->expects($this->once())->method('commit');

        $service = new NumberingService($entityManager, 'numbering', '%010d');

        $result = $service->generateIdentifier(null);

        $this->assertSame('0000000001', $result);
    }

    public function testGenerateNumberingUpdatesWhenExists(): void
    {
        $connection = $this->createMock(Connection::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('fetchAssociative')
            ->willReturn(['value' => 41]);
        $connection->expects($this->once())->method('update')
            ->with(
                $this->equalTo('code'),
                $this->callback(fn (array $data): bool => $data['value'] === 42),
                $this->equalTo([
                    'type' => 'numbering',
                    'identifier' => 'manifestation_identifier',
                ])
            );
        $connection->expects($this->once())->method('commit');

        $service = new NumberingService($entityManager, 'numbering', '%010d');

        $result = $service->generateIdentifier(null);

        $this->assertSame('0000000042', $result);
    }

    public function testGenerateUuidV7HasNoPrefix(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $service = new NumberingService($entityManager, 'uuidv7', '%010d');

        $result = $service->generateIdentifier(null);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', strtolower($result));
    }

    public function testGenerateRandomWithoutIsbn(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $service = new NumberingService($entityManager, 'random', '%010d');

        $result = $service->generateIdentifier(null);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', strtolower($result));
    }

    public function testGenerateRandomWithIsbn(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $service = new NumberingService($entityManager, 'random', '%010d');

        $result = $service->generateIdentifier('978-4-00-310101-8');

        $this->assertMatchesRegularExpression('/^9784003101018-[0-9a-f]{8}$/', strtolower($result));
    }
}
