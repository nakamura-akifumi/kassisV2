<?php

namespace App\Service;

use App\Entity\Manifestation;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class AmazonImportService
{
    private EntityManagerInterface $entityManager;
    private SluggerInterface $slugger;
    private string $projectDir;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        LoggerInterface $logger,
        string $projectDir
    ) {
        $this->entityManager = $entityManager;
        $this->slugger = $slugger;
        $this->logger = $logger;
        $this->projectDir = $projectDir;
    }

    public function processFile(UploadedFile $zipFile): array
    {
        $result = [
            'success' => 0,
            'skipped' => 0,
            'errors' => 0,
            'errorMessages' => []
        ];

        $this->logger->info('Amazon購入履歴のインポートを開始します', [
            'filename' => $zipFile->getClientOriginalName(),
            'size' => $zipFile->getSize()
        ]);

        // 一時ディレクトリを作成
        $tempDir = $this->projectDir . '/var/temp/' . uniqid('amazon_import_');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        // ZIPファイルを一時ディレクトリに保存
        $zipPath = $tempDir . '/' . $zipFile->getClientOriginalName();
        $zipFile->move(dirname($zipPath), basename($zipPath));
        $this->logger->info('ZIPファイルをテンポラリディレクトリに保存しました', ['path' => $zipPath]);

        $this->logger->info("tempDir=".$tempDir." zipPath=".$zipPath);;

        try {
            // ZIPファイルを解凍
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) === true) {
                $zip->extractTo($tempDir);
                $zip->close();
                $this->logger->info('ZIPファイルを解凍しました', ['tempDir' => $tempDir]);

                // CSVファイルを再帰的に探す
                $csvFiles = $this->findCsvFilesRecursively($tempDir);
                if (empty($csvFiles)) {
                    $this->logger->error('ZIPファイル内にCSVファイルが見つかりませんでした');
                    $result['errors']++;
                    $result['errorMessages'][] = 'ZIPファイル内にCSVファイルが見つかりませんでした。';
                    return $result;
                }

                $this->logger->info('CSVファイルが見つかりました', ['files' => $csvFiles]);

                // すべてのCSVファイルを処理する
                foreach ($csvFiles as $csvFile) {
                    $this->logger->info('CSVファイルの処理を開始します', ['file' => $csvFile]);
                    $fileResult = $this->processAmazonCsv($csvFile, [
                        'success' => 0,
                        'skipped' => 0,
                        'errors' => 0,
                        'errorMessages' => []
                    ]);

                    // 結果を集計する
                    $result['success'] += $fileResult['success'];
                    $result['skipped'] += $fileResult['skipped'];
                    $result['errors'] += $fileResult['errors'];
                    $result['errorMessages'] = array_merge(
                        $result['errorMessages'],
                        $fileResult['errorMessages']
                    );
                    $result['processedFiles'][] = [
                        'file' => basename($csvFile),
                        'success' => $fileResult['success'],
                        'skipped' => $fileResult['skipped'],
                        'errors' => $fileResult['errors']
                    ];

                    $this->logger->info('CSVファイルの処理が完了しました', [
                        'file' => $csvFile,
                        'success' => $fileResult['success'],
                        'skipped' => $fileResult['skipped'],
                        'errors' => $fileResult['errors']
                    ]);
                }
            } else {
                $this->logger->error('ZIPファイルを開くことができませんでした', ['path' => $zipPath]);
                $result['errors']++;
                $result['errorMessages'][] = 'ZIPファイルを開くことができませんでした。';
            }
        } catch (\Exception $e) {
            $this->logger->error('エラーが発生しました', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $result['errors']++;
            $result['errorMessages'][] = 'エラーが発生しました: ' . $e->getMessage();
        } finally {
            // 一時ディレクトリを削除
            $this->removeDirectory($tempDir);
            $this->logger->info('テンポラリディレクトリを削除しました', ['tempDir' => $tempDir]);
        }

        $this->logger->info('Amazon購入履歴のインポートが完了しました', [
            'success' => $result['success'],
            'skipped' => $result['skipped'],
            'errors' => $result['errors']
        ]);

        return $result;
    }

    private function findCsvFilesRecursively(string $directory): array
    {
        $csvFiles = [];
        $this->logger->debug('CSVファイルを再帰的に検索します', ['directory' => $directory]);

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            $needFiles = array('Digital Items.csv','Retail.OrderHistory.1.csv','Retail.OrderHistory.3.csv');
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'csv') {
                    if (in_array($file->getFilename(), $needFiles)) {
                        $csvFiles[] = $file->getPathname();
                        $this->logger->debug('CSVファイルを見つけました', ['path' => $file->getPathname()]);
                    }
                }
            }

            $this->logger->info('再帰的検索の結果、CSVファイルが' . count($csvFiles) . '件見つかりました');
        } catch (\Exception $e) {
            $this->logger->error('CSVファイルの再帰的検索中にエラーが発生しました', [
                'error' => $e->getMessage(),
                'directory' => $directory
            ]);
        }

        return $csvFiles;
    }


    private function processAmazonCsv(string $csvPath, array $result): array
    {
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            $this->logger->error('CSVファイルを開くことができませんでした', ['path' => $csvPath]);
            $result['errors']++;
            $result['errorMessages'][] = 'CSVファイルを開くことができませんでした。';
            return $result;
        }

        // ヘッダー行を読み込む
        $headers = fgetcsv($handle);
        if (!$headers) {
            $this->logger->error('CSVファイルの形式が正しくありません', ['path' => $csvPath]);
            $result['errors']++;
            $result['errorMessages'][] = 'CSVファイルの形式が正しくありません。';
            fclose($handle);
            return $result;
        }

        // BOM（Byte Order Mark）の処理
        // UTF-8のBOMは "\xEF\xBB\xBF" で、最初のカラム名の先頭についている可能性がある
        if (!empty($headers[0])) {
            // BOMを検出して削除
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            // その他の不可視文字も削除
            $headers[0] = preg_replace('/^[\x00-\x1F\x7F]/', '', $headers[0]);
        }

        $this->logger->info('CSVヘッダーを読み込みました', ['headers' => $headers]);

        $titleIndex = false;
        $asinIndex = false;
        $purchaseType = "";
        $needDigitalFiles = array('Digital Items.csv');
        $needRetailFiles = array('Retail.OrderHistory.1.csv','Retail.OrderHistory.3.csv');
        $csvFilesWithoutPath = pathinfo($csvPath, PATHINFO_BASENAME);

        // Amazon CSVのヘッダーを確認
        if (in_array($csvFilesWithoutPath, $needDigitalFiles)) {
            // KINDLE
            $purchaseType = "Digital";
            $titleIndex = array_search('ProductName', $headers);
            $asinIndex = array_search('ASIN', $headers);
            $BuyerIndex = array_search('Marketplace', $headers);
            $orderDateIndex = array_search('OrderDate', $headers);
            $orderIdIndex = array_search('OrderID', $headers);
            $QuantityIndex = array_search('Quantity', $headers);
        } elseif (in_array($csvFilesWithoutPath, $needRetailFiles)) {
            // Retail
            $purchaseType = "Retail";
            $titleIndex = array_search('Product Name', $headers);
            $asinIndex = array_search('ASIN', $headers);
            $BuyerIndex = array_search('Website', $headers);
            $orderDateIndex = array_search('Order Date', $headers);
            $orderIdIndex = array_search('Order ID', $headers);
            $QuantityIndex = array_search('Quantity', $headers);
        } else {
            $this->logger->debug('処理対象外のファイルです。'.$csvFilesWithoutPath);
            return $result;
        }

        $this->logger->debug(var_export($headers, true));

        if ($titleIndex === false || $asinIndex === false) {
            $this->logger->error('CSVファイルに必要なカラムが見つかりませんでした', [
                'requiredColumns' => ['Title', 'ASIN', 'Order Date'],
                'foundColumns' => $headers
            ]);
            $result['errors']++;
            $result['errorMessages'][] = 'CSVファイルに必要なカラム（Title、ASIN）が見つかりませんでした。';
            fclose($handle);
            return $result;
        }

        // CSVファイルを行ごとに処理
        $rowCount = 0;
        while (($data = fgetcsv($handle)) !== false) {
            $rowCount++;
            try {
                $buyerIdentifier = $data[$orderIdIndex].":".$data[$asinIndex];
                // すでに同じ購入識別子が存在するか確認
                $existingItem = $this->entityManager->getRepository(Manifestation::class)
                    ->findOneBy(['buyer_identifier' => $buyerIdentifier]);

                if ($existingItem) {
                    $this->logger->debug('同一購入識別子が既に存在するためスキップします', [
                        'buyer_identifier' => $buyerIdentifier,
                        'title' => $title
                    ]);
                    $result['skipped']++;
                    continue;
                }

                $title = $data[$titleIndex] ?? null;
                if (empty($title)) {
                    $this->logger->debug('タイトルが空のため行をスキップします', ['row' => $rowCount]);
                    $result['skipped']++;
                    continue;
                }
                if ($title == 'Not Applicable') {
                    $this->logger->debug('タイトルが[Not Applicable]のため行をスキップします', ['row' => $rowCount]);
                    $result['skipped']++;
                    continue;
                }

                $buyer = null;
                if ($BuyerIndex !== false && !empty($data[$BuyerIndex])) {
                    $buyer = $data[$BuyerIndex];
                }

                // ASIN
                $identifier = null;
                $externalIdentifier1 = null;
                if ($asinIndex !== false && !empty($data[$asinIndex])) {
                    $identifier = $data[$asinIndex];
                    //TODO: 適切な文字列の生成
                    //$identifier = 'AMZ-' . substr($this->slugger->slug($title), 0, 50);
                    $externalIdentifier1 = $data[$asinIndex];
                }

                // 同じIdentifierが存在するか確認
                $existingItemByIdentifier = $this->entityManager->getRepository(Manifestation::class)
                    ->findOneBy(['identifier' => $identifier]);

                if ($existingItemByIdentifier) {
                    $oldIdentifier = $identifier;
                    $identifier = $data[$asinIndex]."-".uniqid('amz_');;
                    $this->logger->debug('同一識別子(identifier)が既に存在するため別の識別子に変更します', [
                        'identifier' => $oldIdentifier,
                        'new_identifier' => $identifier,
                        'title' => $title
                    ]);

                }

                // 購入日の処理
                $purchaseDate = null;
                if ($orderDateIndex !== false && !empty($data[$orderDateIndex])) {
                    try {
                        $purchaseDate = new \DateTime($data[$orderDateIndex]);
                    } catch (\Exception $e) {
                        $this->logger->warning('日付の解析に失敗しました', [
                            'date' => $data[$orderDateIndex],
                            'error' => $e->getMessage()
                        ]);
                        // 日付の解析に失敗した場合はスキップ
                    }
                }

                // Manifestationエンティティを作成
                $manifestation = new Manifestation();
                $manifestation->setTitle($title);
                $manifestation->setIdentifier($identifier);
                $manifestation->setBuyer($buyer);
                $manifestation->setRecordSource('Amazon購入履歴:'.$csvFilesWithoutPath);
                $manifestation->setExternalIdentifier1($externalIdentifier1);

                if ($purchaseDate) {
                    $manifestation->setPurchaseDate($purchaseDate);
                }

                // データベースに保存
                $this->entityManager->persist($manifestation);
                $this->entityManager->flush();
                
                $this->logger->info('新しい書誌情報を登録しました', [
                    'id' => $manifestation->getId(),
                    'title' => $title,
                    'identifier' => $identifier
                ]);
                
                $result['success']++;
            } catch (\Exception $e) {
                $this->logger->error('行の処理中にエラーが発生しました', [
                    'row' => $rowCount,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $result['errors']++;
                $result['errorMessages'][] = '行の処理中にエラーが発生しました: ' . $e->getMessage();
            }
        }

        $this->logger->info('CSVファイルの処理が完了しました', [
            'path' => $csvPath,
            'rowsProcessed' => $rowCount,
            'success' => $result['success'],
            'skipped' => $result['skipped'],
            'errors' => $result['errors']
        ]);

        fclose($handle);
        return $result;
    }

    private function removeDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->removeDirectory($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}