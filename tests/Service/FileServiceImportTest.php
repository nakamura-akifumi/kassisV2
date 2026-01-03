<?php

namespace App\Tests\Service;

use App\Entity\Manifestation;
use App\Service\FileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileServiceImportTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private FileService $fileService;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->fileService = $container->get(FileService::class);

        $this->initDatabase();
    }
    private function initDatabase(): void
    {
        $metaData = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metaData);
        $schemaTool->createSchema($metaData);
    }

    public function testImportManifestationsFromFile(): void
    {
        // プロジェクトルートからの相対パスでテスト用ファイルを取得
        $filePath = self::getContainer()->getParameter('kernel.project_dir') . '/tests/resources/importtest1.xlsx';
        
        if (!file_exists($filePath)) {
            $this->markTestSkipped('テスト用のExcelファイルが見つかりません: ' . $filePath);
        }

        // UploadedFile をシミュレート
        // 第5引数を true にすることで、テスト環境での移動制限を回避します
        $uploadedFile = new UploadedFile(
            $filePath,
            'importtest1.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        // インポート前の件数を取得
        $repository = $this->entityManager->getRepository(Manifestation::class);
        $initialCount = count($repository->findAll());

        // インポート実行
        $result = $this->fileService->importManifestationsFromFile($uploadedFile);

        // 基本的な結果の検証
        $this->assertSame(2, $result['success'], '2件のインポート成功を期待しています。');
        $this->assertSame(2, $result['errors'], '2件のインポート失敗を期待しています。');
        //$this->assertEquals(0, $result['errors'], 'エラーが発生していないことを期待しています。エラーメッセージ: ' . implode(', ', $result['errorMessages']));

        // データベースにデータが増えているか確認
        $finalCount = count($repository->findAll());
        $this->assertEquals($initialCount + $result['success'], $finalCount);

        // インポートされたデータが正しくマッピングされているか確認
        /** @var Manifestation $manifestation */

        // ファイル１ テストデータ１行目：ISBNからNDLサーチでインポートした情報、ファイルに指定した識別子で取得
        $manifestation = $repository->findOneBy(["identifier" => "24001002-451"]);

        $this->assertNotNull($manifestation);
        $this->assertSame("図解・気象学入門 : 原理からわかる雲・雨・気温・風・天気図", $manifestation->getTitle());
        $this->assertSame("ズカイ キショウガク ニュウモン : ゲンリ カラ ワカル クモ アメ キオン カゼ テンキズ", $manifestation->getTitleTranscription());
        $this->assertSame("24001002-451", $manifestation->getIdentifier());
        $this->assertSame("あいうえお書店", $manifestation->getBuyer());
        $this->assertSame("図書", $manifestation->getType1());
        $this->assertSame("書棚１", $manifestation->getLocation1());
        $this->assertSame("古川武彦, 大木勇人 著", $manifestation->getContributor1());
        $this->assertSame("講談社", $manifestation->getContributor2());
        $this->assertSame("2025/12/10", $manifestation->getPurchaseDate()->format('Y/m/d'));
        $this->assertSame(1200, $manifestation->getPrice());
        $this->assertSame("2023.7", $manifestation->getReleaseDateString());
        $this->assertSame("active", $manifestation->getStatus1());

        // ファイル１ テストデータ２行目：ファイル記載の情報でインポート処理をしたレコードの検証（identifier は自動採番、 '9784120042591-' から始まるレコードを取得）
        $manifestation = $repository->createQueryBuilder('m')
            ->where('m.identifier LIKE :identifier')
            ->setParameter('identifier', '9784120042591-%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        $this->assertNotNull($manifestation);
        $this->assertSame("謙信の軍配者", $manifestation->getTitle());
        $this->assertSame("ケンシン ノ グンバイシャ", $manifestation->getTitleTranscription());
        $this->assertStringContainsString("9784120042591-", $manifestation->getIdentifier());
        $this->assertSame("978-4120042591", $manifestation->getExternalIdentifier1());
        //TODO: 自動取込しない場合のType1はISBN有無で自動設定するべきか。
        //$this->assertSame("図書", $manifestation2->getType1());
        $this->assertSame("書棚１", $manifestation->getLocation1());
        $this->assertSame("2023/12/31", $manifestation->getPurchaseDate()->format('Y/m/d'));
        $this->assertSame("active", $manifestation->getStatus1());
        $this->assertSame(900, $manifestation->getPrice());

        // ファイル２ テストデータ1行目：全カラム確認
        $filePath = self::getContainer()->getParameter('kernel.project_dir') . '/tests/resources/importtest2.xlsx';

        if (!file_exists($filePath)) {
            $this->markTestSkipped('テスト用のExcelファイルが見つかりません: ' . $filePath);
        }

        $uploadedFile = new UploadedFile(
            $filePath,
            'importtest1.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        // インポート実行
        $result = $this->fileService->importManifestationsFromFile($uploadedFile);

        $manifestation = $repository->findOneBy(["identifier" => "NINJA-HIMITSU"]);
        $this->assertNotNull($manifestation);
        $this->assertEquals("忍者のひみつ", $manifestation->getTitle());
        $this->assertEquals("ニンジャノヒミツ", $manifestation->getTitleTranscription());
        $this->assertEquals("NINJA-HIMITSU", $manifestation->getIdentifier());
        $this->assertEquals("EXIDENTIFIER1", $manifestation->getExternalIdentifier1());
        $this->assertEquals("EXIDENTIFIER2", $manifestation->getExternalIdentifier2());
        $this->assertEquals("EXIDENTIFIER3", $manifestation->getExternalIdentifier3());
        $this->assertEquals("説明欄です。", $manifestation->getDescription());
        $this->assertEquals("ほいほいブックストア", $manifestation->getBuyer());
        $this->assertEquals("BUYERIDENTIFIER", $manifestation->getBuyerIdentifier());
        $this->assertEquals("2025/10/11", $manifestation->getPurchaseDate()->format('Y/m/d'));
        $this->assertEquals("importtest2.xlsx", $manifestation->getRecordSource());
        $this->assertEquals("図書", $manifestation->getType1());
        $this->assertEquals("レーザーディスク", $manifestation->getType2());
        $this->assertEquals("禁帯", $manifestation->getType3());
        $this->assertEquals("ぶんぶんぶん４", $manifestation->getType4());
        $this->assertEquals("日本国内", $manifestation->getLocation1());
        $this->assertEquals("北日本", $manifestation->getLocation2());
        $this->assertEquals("マーガレット", $manifestation->getContributor1());
        $this->assertEquals("にう出版", $manifestation->getContributor2());
        $this->assertEquals("new", $manifestation->getStatus1());
        $this->assertEquals("archive", $manifestation->getStatus2());
        $this->assertEquals("2025.1", $manifestation->getReleaseDateString());
        $this->assertEquals(1700, $manifestation->getPrice());

        // ファイル３ テストデータ１行目：同じ識別子で上書き確認

        // ファイル３ テストデータ２行目：同じIDで上書き確認

    }

    public function testImportEmptyFileReturnsSkipped(): void
    {
        // 空のCSVファイルなど、ヘッダーのみの場合の挙動テスト
        $tempFile = tempnam(sys_get_temp_dir(), 'test_empty_');
        file_put_contents($tempFile, "ID,タイトル,著者\n"); // ヘッダーのみ

        $uploadedFile = new UploadedFile($tempFile, 'empty.csv', 'text/csv', null, true);
        
        $result = $this->fileService->importManifestationsFromFile($uploadedFile);
        
        $this->assertEquals(1, $result['skipped']);
        $this->assertStringContainsString('データ行がありません', $result['errorMessages'][0]);
        
        unlink($tempFile);
    }
}
