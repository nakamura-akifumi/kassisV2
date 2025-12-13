<?php

namespace App\Service;

use App\Entity\Manifestation;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ExcelFileService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
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
            for ($i = 2; $i <= count($rows); $i++) {
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
                } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
            $this->logger->error('Import failed', ['error' => $e->getMessage()]);
            $result['errors']++;
            $result['errorMessages'][] = 'ファイルの読み込みに失敗しました: ' . $e->getMessage();
            return $result;
        }
    }

    private function loadSpreadsheet(UploadedFile $file)
    {
        $path = $file->getPathname();
        $ext = strtolower((string) $file->getClientOriginalExtension());

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
            'author' => null,
            'publisher' => null,
            'publication_year' => null,
            'isbn' => null,
        ];

        foreach ($headerRow as $col => $value) {
            $label = trim((string) $value);

            if ($label === 'ID') $map['id'] = $col;
            if ($label === 'タイトル') $map['title'] = $col;
            if ($label === '著者') $map['author'] = $col;
            if ($label === '出版社') $map['publisher'] = $col;
            if ($label === '出版年') $map['publication_year'] = $col;
            if ($label === 'ISBN') $map['isbn'] = $col;
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