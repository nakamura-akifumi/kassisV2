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

        $httpClient = static::getContainer()->get(HttpClientInterface::class);
        $logger = static::getContainer()->get(LoggerInterface::class);

        $ndlSearchService = new NdlSearchService($httpClient, $logger);
        $service = new NdlImportService($ndlSearchService, $this->entityManager);

        $isbn = '9784003230817';
        $manifestation = $service->importByIsbn($isbn);

        self::assertNotNull($manifestation);
        self::assertSame("白鯨. 上", $manifestation->getTitle());
        self::assertSame("ハクゲイ ジョウ", $manifestation->getTitleTranscription());
        self::assertSame("4-00-323081-7", $manifestation->getIdentifier());
        self::assertSame("9784003230817", $manifestation->getExternalIdentifier1());
        self::assertSame("https://ndlsearch.ndl.go.jp/books/R100000002-I000007478705", $manifestation->getRecordSource());
        self::assertSame("図書", $manifestation->getType1());
        self::assertSame("メルヴィル 作 | 八木敏雄 訳", $manifestation->getContributor1());
        self::assertSame("岩波書店", $manifestation->getContributor2());
        self::assertSame("2004.8", $manifestation->getReleaseDateString());
        self::assertSame("ndc9/933.6", $manifestation->getClass1());
        self::assertNull($manifestation->getClass2());

        $isbn = '9784140811016';
        $manifestation = $service->importByIsbn($isbn);
        self::assertNotNull($manifestation);
        self::assertSame("人類が知っていることすべての短い歴史", $manifestation->getTitle());
        self::assertSame("ジンルイ ガ シッテイル コト スベテ ノ ミジカイ レキシ", $manifestation->getTitleTranscription());
        self::assertSame("4-14-081101-3", $manifestation->getIdentifier());
        self::assertSame("9784140811016", $manifestation->getExternalIdentifier1());
        self::assertSame("https://ndlsearch.ndl.go.jp/books/R100000002-I000008142306", $manifestation->getRecordSource());
        self::assertSame("図書", $manifestation->getType1());
        self::assertSame("ビル・ブライソン 著 | 楡井浩一 訳", $manifestation->getContributor1());
        self::assertSame("日本放送出版協会", $manifestation->getContributor2());
        self::assertSame("2006.3", $manifestation->getReleaseDateString());
        self::assertSame("ndc9/402", $manifestation->getClass1());
        self::assertNull($manifestation->getClass2());

        $isbn = '9784805455425';
        $manifestation = $service->importByIsbn($isbn);

        self::assertNotNull($manifestation);
        self::assertSame("うみまでいけるかな?", $manifestation->getTitle());
        self::assertSame("ウミ マデ イケル カナ", $manifestation->getTitleTranscription());
        self::assertSame("978-4-8054-5542-5", $manifestation->getIdentifier());
        self::assertSame("9784805455425", $manifestation->getExternalIdentifier1());
        self::assertSame("https://ndlsearch.ndl.go.jp/books/R100000002-I032849113", $manifestation->getRecordSource());
        self::assertSame("図書", $manifestation->getType1());
        self::assertSame("新井洋行 さく | 小林ゆき子 え", $manifestation->getContributor1());
        self::assertSame("チャイルド本社", $manifestation->getContributor2());
        self::assertSame("2023.7", $manifestation->getReleaseDateString());
        self::assertSame("ndc10/726.6", $manifestation->getClass1());
        self::assertNull($manifestation->getClass2());

    }

    public function testImportByIsbnReturnsNull(): void
    {
        $httpClient = static::getContainer()->get(HttpClientInterface::class);
        $logger = static::getContainer()->get(LoggerInterface::class);

        $ndlSearchService = new NdlSearchService($httpClient, $logger);
        $service = new NdlImportService($ndlSearchService, $this->entityManager);

        $isbn = ''; // 空文字
        $manifestation = $service->importByIsbn($isbn);

        self::assertNull($manifestation);

        $isbn = '978414081101X'; // 不正なISBN
        $manifestation = $service->importByIsbn($isbn);
        self::assertNull($manifestation);
    }
}
