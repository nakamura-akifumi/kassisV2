<?php

namespace App\Tests\Service;

use App\Entity\Manifestation;
use App\Service\AmazonImportService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AmazonImportServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AmazonImportService $amazonImportService;
    private string $projectDir;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->amazonImportService = $container->get(AmazonImportService::class);
        $this->projectDir = $container->getParameter('kernel.project_dir');

        // テスト前にデータベースのスキーマを初期化
        $this->initDatabase();
    }

    private function initDatabase(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        if (!empty($metadata)) {
            $tool = new SchemaTool($this->entityManager);
            $tool->dropSchema($metadata);
            $tool->createSchema($metadata);
        }
    }

    private function clearDatabase(): void
    {
        $repository = $this->entityManager->getRepository(Manifestation::class);
        foreach ($repository->findAll() as $entity) {
            $this->entityManager->remove($entity);
        }
        $this->entityManager->flush();
    }

    public function testProcessFileIsbnOnly(): void
    {
        // テスト用ファイルのパス（オリジナル）
        // デジタル5件(うち 1件 Not Applicable）、リテール6件（うち4件ISBNあり）
        $originalZipPath = $this->projectDir . '/tests/resources/amazon_orders_test1.zip';

        if (!file_exists($originalZipPath)) {
            $this->markTestSkipped('テスト用の ZIP ファイルが見つかりません: ' . $originalZipPath);
        }

        // サービス側でファイルを move/delete してしまうため、一時的なコピーを作成する
        $zipPath = tempnam(sys_get_temp_dir(), 'amazon_test_');
        copy($originalZipPath, $zipPath);

        // UploadedFile をシミュレート（コピーしたパスを使用）
        $uploadedFile = new UploadedFile(
            $zipPath,
            'amazon_orders_test1.zip',
            'application/zip',
            null,
            true // テストモード
        );

        // 実行
        $result = $this->amazonImportService->processFile($uploadedFile, true);

        // アサーション
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertEquals(4, $result['success'], '4件のインポートに成功する必要があります。');
        $this->assertEquals(7, $result['skipped'], '7件のスキップが発生します。');
        $this->assertEquals(0, $result['errors'], 'エラーが発生してはいけません。');

        // データベースに保存されているか確認
        $repository = $this->entityManager->getRepository(Manifestation::class);

        $manifestations = $repository->findAll();
        $this->assertCount($result['success'], $manifestations);

    }

    public function testProcessFileWithRealZip(): void
    {
        // テスト用ファイルのパス（オリジナル）
        // デジタル5件（うちスキップ1）、リテール6件（うち4件ISBNあり）
        $originalZipPath = $this->projectDir . '/tests/resources/amazon_orders_test1.zip';

        if (!file_exists($originalZipPath)) {
            $this->markTestSkipped('テスト用の ZIP ファイルが見つかりません: ' . $originalZipPath);
        }

        // サービス側でファイルを move/delete してしまうため、一時的なコピーを作成する
        $zipPath = tempnam(sys_get_temp_dir(), 'amazon_test_');
        copy($originalZipPath, $zipPath);

        // UploadedFile をシミュレート（コピーしたパスを使用）
        $uploadedFile = new UploadedFile(
            $zipPath,
            'amazon_orders_test1.zip',
            'application/zip',
            null,
            true // テストモード
        );

        // 実行
        $result = $this->amazonImportService->processFile($uploadedFile, false);

        // アサーション
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertEquals(10, $result['success'], '6件のインポートに成功する必要があります。');
        $this->assertEquals(1, $result['skipped'], '1件のスキップが発生します。');
        $this->assertEquals(0, $result['errors'], 'エラーが発生してはいけません。');

        // データベースに保存されているか確認
        $repository = $this->entityManager->getRepository(Manifestation::class);

        $manifestations = $repository->findAll();
        $this->assertCount($result['success'], $manifestations);

        $manifestation = $repository->findOneBy(["identifier" => "9784284001250"]);
        $this->assertSame("ふわふわとちくちく: ことばえらびえほん", $manifestation->getTitle());
        $this->assertSame("フワフワ ト チクチク : コトバエラビ エホン", $manifestation->getTitleTranscription());
        $this->assertSame("9784284001250", $manifestation->getExternalIdentifier1());
        $this->assertNull($manifestation->getExternalIdentifier2());
        $this->assertSame("4284001256", $manifestation->getExternalIdentifier3());
        $this->assertNull($manifestation->getDescription());
        $this->assertSame("Amazon.co.jp", $manifestation->getBuyer());
        $this->assertSame("250-2959837-9386246_4284001256", $manifestation->getBuyerIdentifier());
        $this->assertSame("2025/04/24", $manifestation->getPurchaseDate()->format('Y/m/d'));
        $this->assertStringContainsString('Amazon購入履歴', $manifestation->getRecordSource());
        $this->assertSame("図書", $manifestation->getType1());
        $this->assertNull($manifestation->getType2());
        $this->assertNull($manifestation->getType3());
        $this->assertNull($manifestation->getType4());
        $this->assertNull($manifestation->getLocation1());
        $this->assertNull($manifestation->getLocation2());
        $this->assertSame("齋藤孝 監修 | 川原瑞丸 絵", $manifestation->getContributor1());
        $this->assertSame("日本図書センター", $manifestation->getContributor2());
        $this->assertSame("2023.7", $manifestation->getReleaseDateString());
        $this->assertSame("1430", $manifestation->getPrice());
        $this->assertSame("new", $manifestation->getStatus1());
        $this->assertNull($manifestation->getStatus2());
        $this->assertSame("ndc10/726.6", $manifestation->getClass1());
        $this->assertNull($manifestation->getClass2());

        $manifestation = $repository->findOneBy(["identifier" => "B07LC4PP28"]);
        $this->assertSame("BenQ GW2280 アイケア ウルトラスリムベゼルモニター (21.5インチ/フルHD/VA/輝度自動調整機能(B.I.)搭載/ブルーライト軽減/フリッカーフリー)", $manifestation->getTitle());
        $this->assertNull($manifestation->getTitleTranscription());
        $this->assertNull($manifestation->getExternalIdentifier1());
        $this->assertNull($manifestation->getExternalIdentifier2());
        $this->assertSame("B07LC4PP28", $manifestation->getExternalIdentifier3());
        $this->assertNull($manifestation->getDescription());
        $this->assertSame("Amazon.co.jp", $manifestation->getBuyer());
        $this->assertSame("249-7612059-5168651_B07LC4PP28", $manifestation->getBuyerIdentifier());
        $this->assertSame("2022/12/17", $manifestation->getPurchaseDate()->format('Y/m/d'));
        $this->assertStringContainsString('Amazon購入履歴', $manifestation->getRecordSource());
        $this->assertNull($manifestation->getType1());
        $this->assertNull($manifestation->getType2());
        $this->assertNull($manifestation->getType3());
        $this->assertNull($manifestation->getType4());
        $this->assertNull($manifestation->getLocation1());
        $this->assertNull($manifestation->getLocation2());
        $this->assertNull($manifestation->getContributor1());
        $this->assertNull($manifestation->getContributor2());
        $this->assertNull($manifestation->getReleaseDateString());
        $this->assertSame("8975", $manifestation->getPrice());
        $this->assertSame("new", $manifestation->getStatus1());
        $this->assertNull($manifestation->getStatus2());

        // 再処理でスキップするのを確認する
        $zipPath = tempnam(sys_get_temp_dir(), 'amazon_test_');
        copy($originalZipPath, $zipPath);

        // UploadedFile をシミュレート（コピーしたパスを使用）
        $uploadedFile = new UploadedFile(
            $zipPath,
            'amazon_orders_test1.zip',
            'application/zip',
            null,
            true // テストモード
        );

        $result = $this->amazonImportService->processFile($uploadedFile, false);

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertEquals(0, $result['success'], '0件のインポートに成功する必要があります。');
        $this->assertEquals(11, $result['skipped'], '11件のインポートにスキップする必要があります。');
        $this->assertEquals(0, $result['errors'], 'エラーが発生してはいけません。');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}
