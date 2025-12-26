<?php

namespace App\Service;

use App\Entity\Manifestation;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

class FileService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface        $logger,
    ) {
    }

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

        // 必須項目をマージし、順序を維持
        $required = ['id', 'title', 'identifier'];
        $selectedColumns = array_unique(array_merge($required, $columns));

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
                    if ($value instanceof \DateTimeInterface) {
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

            for ($i = 1; $i <= count($selectedColumns); $i++) {
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
            $writer->setEnclosure('"');
            $writer->setLineEnding("\n");
            $writer->setUseBOM(true);
            $writer->save($tempFile);
        } else {
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempFile);
        }

        return $tempFile;
    }

    /**
     * /file/export のフォーマット（ID, タイトル, 著者, 出版社, 出版年, ISBN）を取り込む。
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
            if ($colMap['title'] === null) {
                $result['errors']++;
                $result['errorMessages'][] = 'ヘッダーに「タイトル」列が見つかりません。/file/export の形式で出力したファイルを使ってください。';
                return $result;
            }

            // データ行（2行目以降）
            for ($i = 2, $iMax = count($rows); $i <= $iMax; $i++) {
                $row = $rows[$i] ?? null;
                if ($row === null) {
                    continue;
                }

                $idRaw = $this->getCellValue($row, $colMap['id']);
                $title = $this->getCellValue($row, $colMap['title']);
                $author = $this->getCellValue($row, $colMap['author']);
                $publisher = $this->getCellValue($row, $colMap['publisher']);
                $publicationYear = $this->getCellValue($row, $colMap['publication_year']);
                $isbn = $this->getCellValue($row, $colMap['isbn']);

                // 空行はスキップ（タイトルもIDもない等）
                if ($this->isBlank($title) && $this->isBlank($idRaw) && $this->isBlank($isbn)) {
                    $result['skipped']++;
                    continue;
                }

                if ($this->isBlank($title)) {
                    $result['errors']++;
                    $result['errorMessages'][] = sprintf('%d行目: タイトルが空のため取り込みできません。', $i);
                    continue;
                }

                try {
                    $manifestation = null;

                    // IDがあれば既存更新を試みる
                    $id = $this->parseIntOrNull($idRaw);
                    if ($id !== null) {
                        $manifestation = $this->entityManager->getRepository(Manifestation::class)->find($id);
                    }

                    if (!$manifestation instanceof Manifestation) {
                        $manifestation = new Manifestation();
                        // identifier が必須なので新規時は必ずセット
                        $manifestation->setIdentifier($this->generateIdentifier($title, $isbn));
                    }

                    $manifestation->setTitle($title);

                    // Export側の列名（著者/出版社/出版年/ISBN）を、Entityの既存フィールドへマッピング
                    // 著者 -> contributor1
                    $manifestation->setContributor1($this->nullIfBlank($author));

                    // 出版社 -> contributor2（暫定：現状のEntityに publisher 専用が無い為）
                    $manifestation->setContributor2($this->nullIfBlank($publisher));

                    // 出版年 -> release_date_string（暫定）
                    $manifestation->setReleaseDateString($this->nullIfBlank($publicationYear));

                    // ISBN -> external_identifier1（暫定：現在 export もここを使っている）
                    $manifestation->setExternalIdentifier1($this->nullIfBlank($isbn));

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
     * @return array{id:?string, title:?string, author:?string, publisher:?string, publication_year:?string, isbn:?string}
     */
    private function buildColumnMapFromHeader(array $headerRow): array
    {
        // 期待ヘッダー（/file/export）
        // A1: ID, B1: タイトル, C1: 著者, D1: 出版社, E1: 出版年, F1: ISBN
        $map = [
            'id' => null,
            'title' => null,
            'title_transcription' => null,
            'identifier' => null,
            'external_identifier_1' => null,
            'external_identifier_2' => null,
            'external_identifier_3' => null,
            'description' => null,
            'buyer' => null,
            'buyer_identifier' => null,
            'purchase_date' => null,
            'record_source' => null,
            'type1' => null,
            'type2' => null,
            'type3' => null,
            'type4' => null,
            'location1' => null,
            'location2' => null,
            'contributor1' => null,
            'contributor2' => null,
            'status1' => null,
            'status2' => null,
            'release_date_string' => null,
            'price' => null,
            'created_at' => null,
            'updated_at' => null,
        ];

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

    private function generateIdentifier(string $title, ?string $isbn): string
    {
        $base = null;

        if (!$this->isBlank($isbn)) {
            $base = 'isbn-' . preg_replace('/[^0-9Xx]/', '', (string) $isbn);
        } else {
            $t = mb_substr(trim($title), 0, 40);
            $t = preg_replace('/\s+/', '-', $t);
            $t = preg_replace('/[^a-zA-Z0-9\-\_一-龠ぁ-んァ-ヶー]/u', '', (string) $t);
            $base = 'title-' . $t;
        }

        // 衝突を避けるため末尾にユニーク値を付与（identifier が unique のため）
        return rtrim((string) $base, '-') . '-' . bin2hex(random_bytes(4));
    }
}