<?php

namespace App\Service;

use App\Entity\Manifestation;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class NdlSearchService
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $apiUrl = 'https://iss.ndl.go.jp/api/opensearch';

    public function __construct(HttpClientInterface $httpClient, LoggerInterface $logger)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * ISBNで書籍情報を検索する
     */
    public function searchByIsbn(string $isbn): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->apiUrl, [
                'query' => [
                    'isbn' => $isbn,
                    'cnt' => 1,
                ],
            ]);

            $content = $response->getContent();
            $xml = simplexml_load_string($content);

            if ($xml === false || !isset($xml->channel->item)) {
                return null;
            }

            // 名前空間の登録
            $namespaces = $xml->getNamespaces(true);
            
            // 最初のアイテムを取得
            $item = $xml->channel->item;
            
            // データの抽出
            $result = [
                'title' => (string)$item->title,
                'description' => (string)$item->description,
                'identifier' => $isbn,
            ];
            
            // dc名前空間からのデータ抽出
            if (isset($namespaces['dc'])) {
                $dc = $item->children($namespaces['dc']);
                if (isset($dc->creator)) {
                    $result['creator'] = (string)$dc->creator;
                }
                if (isset($dc->publisher)) {
                    $result['publisher'] = (string)$dc->publisher;
                }
                if (isset($dc->date)) {
                    $result['date'] = (string)$dc->date;
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('NDLサーチAPIエラー: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 検索結果からManifestationエンティティを作成
     */
    public function createManifestation(array $bookData): Manifestation
    {
        $manifestation = new Manifestation();
        $manifestation->setTitle($bookData['title'] ?? '');
        $manifestation->setDescription($bookData['description'] ?? '');
        $manifestation->setIdentifier($bookData['identifier']);
        
        // 外部識別子としてISBNを設定
        $manifestation->setExternalIdentifier1($bookData['identifier']);
        
        // type1に'book'を設定
        $manifestation->setType1('book');
        
        // 追加情報があれば設定
        if (isset($bookData['creator'])) {
            // 適切なフィールドに作者情報を保存
            // 例えば、descriptionに追記するなど
            $description = $manifestation->getDescription() ?? '';
            $manifestation->setDescription(trim($description . "\n\n作者: " . $bookData['creator']));
        }
        
        if (isset($bookData['publisher'])) {
            // 出版社情報を保存（例：タイトル転記フィールドに）
            $manifestation->setTitleTranscription($bookData['publisher']);
        }
        
        return $manifestation;
    }
}