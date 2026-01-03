<?php

namespace App\Service;

use App\Entity\Manifestation;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

/**
 * @property $ndlSearchService
 */
class FileService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface        $logger,
    ) {
    }

    private array $keyList = ['id','title','title_transcription','identifier',
                'external_identifier_1','external_identifier_2','external_identifier_3',
                'description',
                'buyer','buyer_identifier','purchase_date','record_source',
                'type1','type2','type3','type4',
                'location1','location2', 'contributor1','contributor2',
                'release_date_string',
                'status1','status2','price',
                'created_at','updated_at'
        ];

    /**
     * Manifestation のリストからエクスポートファイルを生成し、一時ファイルのパスを返す。
     *
     * @param Manifestation[] $manifestations
     * @param string[] $columns 選択された項目キーの配列
     */
    public function generateExportFile(array $manifestations, string $format, array $columns = []): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 項目定義とラベルのマッピング
        $allColumns = [
            'id' => ['label' => 'ID', 'getter' => 'getId'],
            'title' => ['label' => 'タイトル', 'getter' => 'getTitle'],
            'titleTranscription' => ['label' => 'ヨミ', 'getter' => 'getTitleTranscription'],
            'identifier' => ['label' => '識別子', 'getter' => 'getIdentifier'],
            'externalIdentifier1' => ['label' => '外部識別子１', 'getter' => 'getExternalIdentifier1'],
            'externalIdentifier2' => ['label' => '外部識別子２', 'getter' => 'getExternalIdentifier2'],
            'externalIdentifier3' => ['label' => '外部識別子３', 'getter' => 'getExternalIdentifier3'],
            'description' => ['label' => '説明', 'getter' => 'getDescription'],
            'buyer' => ['label' => '購入先', 'getter' => 'getBuyer'],
            'buyerIdentifier' => ['label' => '購入先識別子', 'getter' => 'getBuyerIdentifier'],
            'purchaseDate' => ['label' => '購入日', 'getter' => 'getPurchaseDate'],
            'recordSource' => ['label' => '情報取得元', 'getter' => 'getRecordSource'],
            'type1' => ['label' => '分類１', 'getter' => 'getType1'],
            'type2' => ['label' => '分類２', 'getter' => 'getType2'],
            'type3' => ['label' => '分類３', 'getter' => 'getType3'],
            'type4' => ['label' => '分類４', 'getter' => 'getType4'],
            'location1' => ['label' => '場所１', 'getter' => 'getLocation1'],
            'location2' => ['label' => '場所２', 'getter' => 'getLocation2'],
            'contributor1' => ['label' => '貢献者１', 'getter' => 'getContributor1'],
            'contributor2' => ['label' => '貢献者２', 'getter' => 'getContributor2'],
            'status1' => ['label' => 'ステータス１', 'getter' => 'getStatus1'],
            'status2' => ['label' => 'ステータス２', 'getter' => 'getStatus2'],
            'releaseDateString' => ['label' => '発売日', 'getter' => 'getReleaseDateString'],
            'price' => ['label' => '金額', 'getter' => 'getPrice'],
            'createdAt' => ['label' => '作成日時', 'getter' => 'getCreatedAt'],
            'updatedAt' => ['label' => '更新日時', 'getter' => 'getUpdatedAt'],
        ];

        // 必須項目
        $required = ['id', 'title', 'identifier'];
        // 順番維持のため指示カラム列に必須列を追加する。
        $selectedColumns = array_unique(array_merge($columns, $required));
        // ヘッダー行の書き込み
        $colIndex = 1;
        foreach ($selectedColumns as $key) {
            if (isset($allColumns[$key])) {
                $sheet->setCellValue([$colIndex, 1], $allColumns[$key]['label']);
                $colIndex++;
            }
        }

        // データ行の書き込み
        $row = 2;
        foreach ($manifestations as $manifestation) {
            $colIndex = 1;
            foreach ($selectedColumns as $key) {
                if (isset($allColumns[$key])) {
                    $getter = $allColumns[$key]['getter'];
                    $value = $manifestation->$getter();
                    
                    // DateTime などのオブジェクト処理
                    if ($value instanceof DateTimeInterface) {
                        $value = $value->format('Y-m-d H:i:s');
                    }

                    $sheet->setCellValue([$colIndex, $row], $value);
                    $colIndex++;
                }
            }
            $row++;
        }

        // スタイル調整（xlsxのみ）
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($selectedColumns));
        if ($format === 'xlsx') {
            $sheet->getStyle("A1:{$lastColLetter}1")->getFont()->setBold(true);
            $sheet->getStyle("A1:{$lastColLetter}1")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('DDDDDD');

            for ($i = 1, $iMax = count($selectedColumns); $i <= $iMax; $i++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($colLetter)->setAutoSize(true);
            }
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'export_');
        if ($tempFile === false) {
            throw new \RuntimeException('一時ファイルの作成に失敗しました。');
        }

        if ($format === 'csv') {
            $writer = new Csv($spreadsheet);
            $writer->setDelimiter(',');
            $writer->setLineEnding("\n");
            $writer->setUseBOM(false);
        } else {
            $writer = new Xlsx($spreadsheet);
        }
        $writer->save($tempFile);

        return $tempFile;
    }

    private function generateFreshHash(): array
    {
        $hash = [];
        foreach($this->keyList as $val){
            $hash[$val] = null;
        }
        return $hash;
    }
    /**
     * /file/export のフォーマット（ID, タイトル, 著者, 出版社, 出版年, ISBN ...）を取り込む。
     *
     * @return array{success:int, skipped:int, errors:int, errorMessages: string[]}
     */
    public function importManifestationsFromFile(UploadedFile $file): array
    {
        $result = [
            'success' => 0,
            'skipped' => 0,
            'errors' => 0,
            'errorMessages' => [],
        ];

        try {
            $spreadsheet = $this->loadSpreadsheet($file);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true); // A,B,C... の連想配列

            if (count($rows) < 2) {
                $result['skipped']++;
                $result['errorMessages'][] = 'データ行がありません（ヘッダーのみ、または空ファイルです）。';
                return $result;
            }

            $headerRow = $rows[1];
            $colMap = $this->buildColumnMapFromHeader($headerRow);

            // 必須列（最低限タイトルがないとEntityが作れない）
            if ($colMap['title'] === null && $colMap['identifier'] === null && $colMap['external_identifier_1'] === null) {
                $result['errors']++;
                $result['errorMessages'][] = 'ヘッダーに「タイトル」、「識別子」、「外部識別子１」のいずれかが見つかりません。特定のフォーマットで出力したファイルを使ってください。';
                return $result;
            }

            $httpClient = HttpClient::create();
            $ndlSearchService = new \App\Service\NdlSearchService($httpClient, $this->logger);

            // データ行（2行目以降）
            for ($i = 2, $iMax = count($rows); $i <= $iMax; $i++) {
                $row = $rows[$i] ?? null;
                if ($row === null) {
                    continue;
                }

                $cellvals = $this->generateFreshHash();
                foreach($this->keyList as $val){
                    if (isset($colMap[$val])) {
                        $cellvals[$val] = $this->getCellValue($row, $colMap[$val]);
                    }
                }

                // 空行はスキップ（title, identifier, external_identifier_1のいずれも無い）
                if ($this->isBlank($cellvals['title']) && $this->isBlank($cellvals['identifier']) && $this->isBlank($cellvals['external_identifier_1'])) {
                    $result['skipped']++;
                    continue;
                }

                try {
                    $manifestation = null;

                    // IDがあれば既存更新を試みる
                    $id = $this->parseIntOrNull($cellvals['id']);
                    if ($id !== null) {
                        $manifestation = $this->entityManager->getRepository(Manifestation::class)->find($id);
                    }

                    if (!isset($cellvals['title']) &&  isset($cellvals['external_identifier_1'])) {
                        // タイトルなし、外部識別子１（ISBN）あり
                        $bookData = $ndlSearchService->searchByIsbnSru($cellvals['external_identifier_1']);
                        if ($bookData === null) {
                            $msg = "NDLサーチから取得できませんでした。ISBN: {$cellvals['external_identifier_1']}";
                            $this->logger->error('Import row failed', [
                                'row' => $i,
                                'error' => $msg,
                            ]);
                            $result['errors']++;
                            $result['errorMessages'][] = sprintf('%d行目: 取り込みに失敗しました（NDL取得失敗/不正なISBN記号）', $i);
                            continue;
                        }

                        $manifestation = $ndlSearchService->createManifestation($bookData);
                        if (isset($cellvals['identifier'])) {
                            $manifestation->setIdentifier($cellvals['identifier']);
                        } else {
                            $manifestation->setIdentifier($this->generateIdentifier($cellvals['external_identifier_1']));
                        }
                    } else {
                        if ($manifestation === null) {
                            $manifestation = new Manifestation();
                        }
                        $manifestation->setTitle($cellvals['title']);
                        $manifestation->setTitleTranscription($cellvals['title_transcription']);
                        if (isset($cellvals['identifier'])) {
                            $manifestation->setIdentifier($cellvals['identifier']);
                        } else {
                            $manifestation->setIdentifier($this->generateIdentifier($cellvals['external_identifier_1']));
                        }
                        $manifestation->setExternalIdentifier1($cellvals['external_identifier_1']);
                        $manifestation->setType1($cellvals['type1']);
                        $manifestation->setContributor1($cellvals['contributor1']);
                        $manifestation->setContributor2($cellvals['contributor2']);
                    }

                    if (isset($cellvals['external_identifier_2'])) {
                        $manifestation->setExternalIdentifier2($cellvals['external_identifier_2']);
                    }
                    if (isset($cellvals['external_identifier_3'])) {
                        $manifestation->setExternalIdentifier3($cellvals['external_identifier_3']);
                    }
                    if (isset($cellvals['description'])) {
                        $manifestation->setDescription($cellvals['description']);
                    }
                    if (isset($cellvals['buyer'])) {
                        $manifestation->setBuyer($cellvals['buyer']);
                    }
                    if (isset($cellvals['buyer_identifier'])) {
                        $manifestation->setBuyerIdentifier($cellvals['buyer_identifier']);
                    }
                    if (isset($cellvals['purchase_date'])) {
                        $purchaseDateRaw = $this->getCellValue($row, $colMap['purchase_date']);
                        try {
                            $manifestation->setPurchaseDate(new \DateTime($purchaseDateRaw));
                        } catch (\Exception $e) {
                            // 日付形式が不正な場合は該当行はエラー扱い
                            $this->logger->warning('Invalid date format during import', ['value' => $purchaseDateRaw]);
                            throw $e;
                        }
                    }
                    if (isset($cellvals['record_source'])) {
                        $manifestation->setRecordSource($cellvals['record_source']);
                    }
                    if (isset($cellvals['type2'])) {
                        $manifestation->setType2($cellvals['type2']);
                    }
                    if (isset($cellvals['type3'])) {
                        $manifestation->setType3($cellvals['type3']);
                    }
                    if (isset($cellvals['type4'])) {
                        $manifestation->setType4($cellvals['type4']);
                    }
                    if (isset($cellvals['location1'])) {
                        $manifestation->setLocation1($cellvals['location1']);
                    }
                    if (isset($cellvals['location2'])) {
                        $manifestation->setLocation2($cellvals['location2']);
                    }
                    if (isset($cellvals['status1'])) {
                        $manifestation->setStatus1($cellvals['status1']);
                    }
                    if (isset($cellvals['status2'])) {
                        $manifestation->setStatus2($cellvals['status2']);
                    }
                    if (isset($cellvals['release_date_string'])) {
                        $manifestation->setReleaseDateString($cellvals['release_date_string']);
                    }
                    if (isset($cellvals['price'])) {
                        $manifestation->setPrice($cellvals['price']);
                    }

                    $this->entityManager->persist($manifestation);
                    $result['success']++;
                } catch (Throwable $e) {
                    $this->logger->error('Import row failed', [
                        'row' => $i,
                        'error' => $e->getMessage(),
                    ]);
                    $result['errors']++;
                    $result['errorMessages'][] = sprintf('%d行目: 取り込みに失敗しました（%s）', $i, $e->getMessage());
                }
            }

            $this->entityManager->flush();
            return $result;
        } catch (Throwable $e) {
            $this->logger->error('Import failed', ['error' => $e->getMessage()]);
            $result['errors']++;
            $result['errorMessages'][] = 'ファイルの読み込みに失敗しました: ' . $e->getMessage();
            return $result;
        }
    }

    private function loadSpreadsheet(UploadedFile $file): Spreadsheet
    {
        $path = $file->getPathname();
        $ext = strtolower($file->getClientOriginalExtension());

        if ($ext === 'csv') {
            $reader = IOFactory::createReader('Csv');
            $reader->setInputEncoding('UTF-8');
            $reader->setDelimiter(',');
            $reader->setEnclosure('"');
            $reader->setSheetIndex(0);
            return $reader->load($path);
        }

        // xlsx想定（/file/export が xlsx なので基本はここ）
        return IOFactory::load($path);
    }

    /**
     * @param array<string, mixed> $headerRow
     */
    private function buildColumnMapFromHeader(array $headerRow): array
    {
        $map = $this->generateFreshHash();
        foreach ($headerRow as $col => $value) {
            $label = trim((string) $value);

            if ($label === 'ID') $map['id'] = $col;
            if ($label === 'タイトル') $map['title'] = $col;
            if ($label === 'ヨミ') $map["title_transcription"] = $col;
            if ($label === '識別子') $map['identifier'] = $col;
            if ($label === '外部識別子１') $map['external_identifier_1'] = $col;
            if ($label === '外部識別子２') $map['external_identifier_2'] = $col;
            if ($label === '外部識別子３') $map['external_identifier_3'] = $col;
            if ($label === '説明') $map['description'] = $col;
            if ($label === '購入先') $map['buyer'] = $col;
            if ($label === '購入先識別子') $map['buyer_identifier'] = $col;
            if ($label === '購入日') $map['purchase_date'] = $col;
            if ($label === '情報取得元') $map['record_source'] = $col;
            if ($label === '分類１') $map['type1'] = $col;
            if ($label === '分類２') $map['type2'] = $col;
            if ($label === '分類３') $map['type3'] = $col;
            if ($label === '分類４') $map['type4'] = $col;
            if ($label === '場所１') $map['location1'] = $col;
            if ($label === '場所２') $map['location2'] = $col;
            if ($label === '貢献者１') $map['contributor1'] = $col;
            if ($label === '貢献者２') $map['contributor2'] = $col;
            if ($label === 'ステータス１') $map['status1'] = $col;
            if ($label === 'ステータス２') $map['status2'] = $col;
            if ($label === '発売日') $map['release_date_string'] = $col;
            if ($label === '金額') $map['price'] = $col;
            if ($label === '作成日時') $map['created_at'] = $col;
            if ($label === '更新日時') $map['updated_at'] = $col;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function getCellValue(array $row, ?string $col): ?string
    {
        if ($col === null) {
            return null;
        }
        $v = $row[$col] ?? null;
        $s = trim((string) $v);
        return $s === '' ? null : $s;
    }

    private function parseIntOrNull(?string $v): ?int
    {
        if ($v === null) {
            return null;
        }
        if (!preg_match('/^\d+$/', $v)) {
            return null;
        }
        return (int) $v;
    }

    private function isBlank(?string $v): bool
    {
        return $v === null || trim($v) === '';
    }

    private function nullIfBlank(?string $v): ?string
    {
        return $this->isBlank($v) ? null : $v;
    }

    private function generateIdentifier(?string $isbn): string
    {
        $base = null;

        if (!$this->isBlank($isbn)) {
            $base = preg_replace('/[^0-9Xx]/', '', (string) $isbn);
        }

        // 衝突を避けるため末尾にユニーク値を付与（identifier が unique のため）
        return rtrim((string) $base, '-') . '-' . bin2hex(random_bytes(4));
    }
}