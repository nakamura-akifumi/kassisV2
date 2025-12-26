<?php

namespace App\Tests\Service;

use App\Entity\Manifestation;
use App\Service\FileService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Psr\Log\LoggerInterface;

class FileServiceTest extends TestCase
{
    private $entityManager;
    private $logger;
    private FileService $fileService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->fileService = new FileService($this->entityManager, $this->logger);
    }

    public function testGenerateExportFileMinimumColumnXlsx(): void
    {
        // 準備
        $m = new Manifestation();
        $manifestations = [$this->createManifestationMock(1, 'テストタイトル', '著者A')];

        // 実行
        $filePath = $this->fileService->generateExportFile($manifestations, 'xlsx');

        // 検証
        $this->assertFileExists($filePath);
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertEquals('ID', $sheet->getCell('A1')->getValue());
        $this->assertEquals('タイトル', $sheet->getCell('B1')->getValue());
        $this->assertEquals('識別子', $sheet->getCell('C1')->getValue());

        unlink($filePath); // 後片付け
    }

    public function testGenerateExportFileAllColumnXlsx(): void
    {
        // 準備
        $m = new Manifestation();
        $manifestations = [$this->createManifestationMock(1, 'テストタイトル', '著者A')];

        $columns = ['id', 'title', 'titleTranscription', 'identifier', 'externalIdentifier1',
            'externalIdentifier2', 'externalIdentifier3', 'description', 'buyer',
            'buyerIdentifier', 'purchaseDate', 'recordSource', 'type1', 'type2',
            'type3', 'type4', 'location1', 'location2', 'contributor1', 'contributor2',
            'status1', 'status2', 'releaseDateString', 'price', 'createdAt', 'updatedAt'];

        // 実行
        $filePath = $this->fileService->generateExportFile($manifestations, 'xlsx', $columns);

        // 検証
        $this->assertFileExists($filePath);
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertEquals('ID', $sheet->getCell('A1')->getValue());
        $this->assertEquals('タイトル', $sheet->getCell('B1')->getValue());
        $this->assertEquals('ヨミ', $sheet->getCell('C1')->getValue());
        $this->assertEquals('識別子', $sheet->getCell('D1')->getValue());
        $this->assertEquals('外部識別子１', $sheet->getCell('E1')->getValue());
        $this->assertEquals('外部識別子２', $sheet->getCell('F1')->getValue());
        $this->assertEquals('外部識別子３', $sheet->getCell('G1')->getValue());
        $this->assertEquals('説明', $sheet->getCell('H1')->getValue());
        $this->assertEquals('購入先', $sheet->getCell('I1')->getValue());
        $this->assertEquals('購入先識別子', $sheet->getCell('J1')->getValue());
        $this->assertEquals('購入日', $sheet->getCell('K1')->getValue());
        $this->assertEquals('情報取得元', $sheet->getCell('L1')->getValue());
        $this->assertEquals('分類１', $sheet->getCell('M1')->getValue());
        $this->assertEquals('分類２', $sheet->getCell('N1')->getValue());
        $this->assertEquals('分類３', $sheet->getCell('O1')->getValue());
        $this->assertEquals('分類４', $sheet->getCell('P1')->getValue());
        $this->assertEquals('場所１', $sheet->getCell('Q1')->getValue());
        $this->assertEquals('場所２', $sheet->getCell('R1')->getValue());
        $this->assertEquals('貢献者１', $sheet->getCell('S1')->getValue());
        $this->assertEquals('貢献者２', $sheet->getCell('T1')->getValue());
        $this->assertEquals('ステータス１', $sheet->getCell('U1')->getValue());
        $this->assertEquals('ステータス２', $sheet->getCell('V1')->getValue());
        $this->assertEquals('発売日', $sheet->getCell('W1')->getValue());
        $this->assertEquals('金額', $sheet->getCell('X1')->getValue());
        $this->assertEquals('作成日時', $sheet->getCell('Y1')->getValue());
        $this->assertEquals('更新日時', $sheet->getCell('Z1')->getValue());
        $this->assertEquals(1, $sheet->getCell('A2')->getValue());
        $this->assertEquals('テストタイトル', $sheet->getCell('B2')->getValue());

        unlink($filePath); // 後片付け
    }

    public function testGenerateExportFileMinimumColumnCsv(): void
    {
        // 準備
        $manifestations = [$this->createManifestationMock(2, 'CSVタイトル', '著者B')];

        // 実行
        $filePath = $this->fileService->generateExportFile($manifestations, 'csv');

        // 検証
        $this->assertFileExists($filePath);
        $content = file_get_contents($filePath);

        $this->assertStringContainsString('"ID","タイトル","識別子"', $content);
        $this->assertStringContainsString('"2","CSVタイトル","IDENTIFIER"', $content);

        unlink($filePath);
    }

    public function testGenerateExportFileAllColumnCsv(): void
    {
        // 準備
        $manifestations = [$this->createManifestationMock(2, 'CSVタイトル', '著者B')];

        // 実行
        $columns = ['id', 'title', 'titleTranscription', 'identifier', 'externalIdentifier1',
            'externalIdentifier2', 'externalIdentifier3', 'description', 'buyer',
            'buyerIdentifier', 'purchaseDate', 'recordSource', 'type1', 'type2',
            'type3', 'type4', 'location1', 'location2', 'contributor1', 'contributor2',
            'status1', 'status2', 'releaseDateString', 'price', 'createdAt', 'updatedAt'];

        $filePath = $this->fileService->generateExportFile($manifestations, 'csv', $columns);

        // 検証
        $this->assertFileExists($filePath);
        $content = file_get_contents($filePath);
        // BOM(EF BB BF) + ヘッダー + データ
        $this->assertStringContainsString('"ID","タイトル","ヨミ","識別子","外部識別子１","外部識別子２","外部識別子３","説明","購入先","購入先識別子","購入日","情報取得元","分類１","分類２","分類３","分類４","場所１","場所２","貢献者１","貢献者２","ステータス１","ステータス２","発売日","金額","作成日時","更新日時"', $content);
        $this->assertStringContainsString('"2","CSVタイトル","タイトルヨミ","IDENTIFIER","1234567890X1","1234567890X2","1234567890X3","","購入元商店","BUYERIDENTIFIER","","https://shop.example.com/A/B/C/DEFGH","1234567890T1","1234567890T2","1234567890T3","1234567890T4","","","著者B","出版社X","STATUS1","STATUS2","2023","","2022-12-13 22:23:34","2022-12-14 11:23:34"', $content);

        unlink($filePath);

    }

    private function createManifestationMock(int $id, string $title, string $author): Manifestation
    {
        $m = $this->createMock(Manifestation::class);
        $m->method('getId')->willReturn($id);
        $m->method('getTitle')->willReturn($title);
        $m->method('getTitleTranscription')->willReturn("タイトルヨミ");
        $m->method('getContributor1')->willReturn($author);
        $m->method('getContributor2')->willReturn('出版社X');
        $m->method('getReleaseDateString')->willReturn('2023');
        $m->method('getIdentifier')->willReturn('IDENTIFIER');
        $m->method('getExternalIdentifier1')->willReturn('1234567890X1');
        $m->method('getExternalIdentifier2')->willReturn('1234567890X2');
        $m->method('getExternalIdentifier3')->willReturn('1234567890X3');
        $m->method('getStatus1')->willReturn('STATUS1');
        $m->method('getStatus2')->willReturn('STATUS2');
        $m->method('getType1')->willReturn('1234567890T1');
        $m->method('getType2')->willReturn('1234567890T2');
        $m->method('getType3')->willReturn('1234567890T3');
        $m->method('getType4')->willReturn('1234567890T4');
        $m->method('getBuyer')->willReturn('購入元商店');
        $m->method('getBuyerIdentifier')->willReturn('BUYERIDENTIFIER');
        $m->method('getRecordSource')->willReturn('https://shop.example.com/A/B/C/DEFGH');
        $m->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2022-12-13 22:23:34'));
        $m->method('getUpdatedAt')->willReturn(new \DateTimeImmutable('2022-12-14 11:23:34'));

        return $m;
    }
}
