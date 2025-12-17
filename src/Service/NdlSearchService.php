<?php

namespace App\Service;

use App\Entity\Manifestation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NdlSearchService
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $apiUrl = 'https://ndlsearch.ndl.go.jp/api/opensearch';

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
        $this->logger->debug('NDLサーチAPI スタート');

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

            $namespaces = $xml->getNamespaces(true);
            $item = $xml->channel->item;

            $result = [
                'title' => (string) $item->title,
                'description' => (string) $item->description,
                'identifier' => $isbn,
            ];

            if (isset($namespaces['dc'])) {
                $dc = $item->children($namespaces['dc']);
                if (isset($dc->creator)) {
                    $result['creator'] = (string) $dc->creator;
                }
                if (isset($dc->publisher)) {
                    $result['publisher'] = (string) $dc->publisher;
                }
                if (isset($dc->date)) {
                    $result['date'] = (string) $dc->date;
                }
            }

            return $result;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('NDLサーチAPIエラー(TransportException): ' . $e->getMessage());
            return null;
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
        return $this->hydrateManifestation($manifestation, $bookData);
    }

    /**
     * bookDataをManifestationへ反映（新規/更新どちらにも使う）
     */
    public function hydrateManifestation(Manifestation $manifestation, array $bookData): Manifestation
    {
        $manifestation->setTitle($bookData['title'] ?? '');
        $manifestation->setDescription($bookData['description'] ?? '');
        $manifestation->setIdentifier($bookData['identifier']);

        $manifestation->setExternalIdentifier1($bookData['identifier']);
        $manifestation->setType1('book');

        if (isset($bookData['creator'])) {
            $description = $manifestation->getDescription() ?? '';
            $manifestation->setDescription(trim($description . "\n\n作者: " . $bookData['creator']));
        }

        if (isset($bookData['publisher'])) {
            $manifestation->setTitleTranscription($bookData['publisher']);
        }

        return $manifestation;
    }
}