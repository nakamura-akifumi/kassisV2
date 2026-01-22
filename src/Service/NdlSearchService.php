<?php

namespace App\Service;

use App\Entity\Manifestation;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NdlSearchService
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $sruUrl = 'https://ndlsearch.ndl.go.jp/api/sru';

    public function __construct(HttpClientInterface $httpClient, LoggerInterface $logger)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * SRU APIを使用してISBNで書籍情報を検索する
     */
    public function searchByIsbnSru(string $isbn): ?array
    {
        $this->logger->debug('NDL SRU API スタート');

        try {
            // CQLクエリ: isbn="[ISBN番号]"
            $query = sprintf('isbn="%s"', $isbn);

            $response = $this->httpClient->request('GET', $this->sruUrl, [
                'query' => [
                    'operation' => 'searchRetrieve',
                    'version' => '1.2',
                    'query' => $query,
                    'maximumRecords' => 1,
                    'recordSchema' => 'dcndl',
                ],
            ]);

            $content = $response->getContent();

            $xml = simplexml_load_string($content);

            if ($xml === false || !isset($xml->numberOfRecords)) {
                return null;
            }

            $namespaces = $xml->getNamespaces(true);
            $numberOfRecords = $xml->numberOfRecords;
            if ((string)$numberOfRecords === '0') {
                return null;
            }

            $recordsData = $xml->records->record->recordData;
            $tempRecord = html_entity_decode($recordsData[0]->asXML());
            if (preg_match('/<rdf:RDF[^>]*>.*?<\/rdf:RDF>/s', $tempRecord, $matches)) {
                $innerXml = $matches[0];
                $xmlRdf = simplexml_load_string($innerXml);
                $namespaces = $xmlRdf->getNamespaces(true);
                $dcndl = $xmlRdf->children($namespaces['dcndl'] ?? 'http://ndl.go.jp/dcndl/terms/');

                if ($dcndl) {
                    return $this->parseSruResponse($dcndl->BibResource);
                }
            }

            return null;

        } catch (Exception $e) {
            $this->logger->error('NDL SRU APIエラー: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * SRUのBibResource要素をパースして共通配列形式にする
     */
    private function parseSruResponse(\SimpleXMLElement $bibResource): array
    {
        $namespaces = $bibResource->getDocNamespaces(true);

        $dc = $bibResource->children($namespaces['dc'] ?? 'http://purl.org/dc/elements/1.1/');
        $dcndl = $bibResource->children($namespaces['dcndl'] ?? 'http://ndl.go.jp/dcndl/terms/');
        $dcterms = $bibResource->children($namespaces['dcterms'] ?? 'http://purl.org/dc/terms/');
        $rdfNs = $namespaces['rdf'] ?? 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
        $xsi = $namespaces['xsi'] ?? 'http://www.w3.org/2001/XMLSchema-instance';
        $foafNs = 'http://xmlns.com/foaf/0.1/';

        if ($dc === false || $dcndl === false || $dcterms === false) {
            return [];
        }

        // --- タイトルとよみの取得 ---
        $title = '';
        $titleTranscription = '';

        if (isset($dc->title)) {
            $titleElement = $dc->title;
            $rdfChild = $titleElement->children($namespaces['rdf'] ?? 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');

            if (isset($rdfChild->Description)) {
                // <rdf:Description> がある構造の場合
                $desc = $rdfChild->Description;
                $title = (string)($desc->children($namespaces['rdf'] ?? '')->value ?? '');

                // タイトルよみもここから取得
                $titleTranscription = (string)($desc->children($namespaces['dcndl'] ?? '')->transcription ?? '');
            } else {
                // 通常の文字列のみの場合
                $title = (string)$titleElement;
            }
        }
        // 巻号があれば付与
        $volumeTitle = '';
        $volumeTranscription = '';
        if (isset($dcndl->volume)) {
            $volumeElement = $dcndl->volume;
            $rdfChild = $volumeElement->children($namespaces['rdf'] ?? 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');

            if (isset($rdfChild->Description)) {
                // <rdf:Description> がある構造の場合
                $desc = $rdfChild->Description;
                $volumeTitle = (string)($desc->children($namespaces['rdf'] ?? '')->value ?? '');

                // タイトルよみもここから取得
                $volumeTranscription = (string)($desc->children($namespaces['dcndl'] ?? '')->transcription ?? '');
            }
        }
        if ($volumeTitle !== '') {
            $title .= '. ' . $volumeTitle;
            $titleTranscription .= ' ' . $volumeTranscription;
        }

        $creators = [];
        foreach ($dc->creator as $c) {
            $creators[] = (string)$c;
        }
        $creatorStr = implode(' | ', $creators);

        // dcndl:BibResource の rdf:about 属性を取得
        $linkaddress = '';
        $bibAttributes = $bibResource->attributes($rdfNs);
        if (isset($bibAttributes['about'])) {
            $linkaddress = (string)$bibAttributes['about'];
            $linkaddress = explode('#', $linkaddress)[0];
        }

        // 出版社の取得
        $publisher = '';
        if (isset($dcterms->publisher)) {
            $publisherElement = $dcterms->publisher;
            $foaf = $publisherElement->children($foafNs);

            if (isset($foaf->Agent)) {
                $agent = $foaf->Agent;
                $agentFoaf = $agent->children($foafNs);
                if (isset($agentFoaf->name)) {
                    $publisher = (string)$agentFoaf->name;
                }
            }

            // foaf:name が見つからない場合のフォールバック
            if ($publisher === '') {
                $publisher = (string)$publisherElement;
            }
        }

        $result = [
            'title' => $title,
            'title_transcription' => $titleTranscription,
            'publisher' => $publisher,
            'date' => (string)($dcterms->date ?? $dcterms->issued ?? ''),
            'creator' => $creatorStr,
            'link' => $linkaddress,
        ];

        // 識別子の処理 (dc:identifier と dcterms:identifier の両方に対応)
        $identifiers = [];
        //foreach ($dc->identifier as $id) { $identifiers[] = $id; }
        foreach ($dcterms->identifier as $id) { $identifiers[] = $id; }

        foreach ($identifiers as $id) {
            $rdfAttr = $id->attributes($rdfNs);
            $xsiAttr = $id->attributes($xsi);

            // rdf:datatype または xsi:type から識別子の種類を判定
            $type = (string)($rdfAttr['datatype'] ?? $xsiAttr['type'] ?? '');
            $val = (string)$id;

            if (str_ends_with($type, 'ISBN')) {
                $result['identifier'] = $val;
                $result['external_identifier1'] = str_replace('-', '', IsbnService::convertToIsbn13($val));
            }
        }

        // NDCの取得
        if (isset($dcterms->subject)) {
            $ndcs = [];
            foreach ($dcterms->subject as $subject) {
                $rdfAttr = $subject->attributes($rdfNs);
                $resourceUri = (string)($rdfAttr['resource'] ?? '');

                // http://id.ndl.go.jp/class/ndc9/402 のようなURIから情報を抽出
                $uriParts = explode('/', $resourceUri);
                if (count($uriParts) < 6) {
                    continue;
                }

                $classificationScheme = $uriParts[4]; // ndc8, ndc9, ndc10 など
                $classCode = $uriParts[5];           // 分類記号

                if (in_array($classificationScheme, ['ndc8', 'ndc9', 'ndc10'], true)) {
                    $ndcs[$classificationScheme] = $classCode;
                }
            }

            if (!empty($ndcs)) {
                krsort($ndcs); // 版の降順（ndc10 -> ndc9 -> ndc8）
                $formattedNdcs = [];
                foreach ($ndcs as $scheme => $code) {
                    $formattedNdcs[] = "$scheme/$code";
                }
                $result['ndc'] = $formattedNdcs;
            }
        }

        return $result;
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
     * bookDataをManifestationへ反映（テストの期待値に完全準拠）
     */
    public function hydrateManifestation(Manifestation $manifestation, array $bookData): Manifestation
    {
        $manifestation->setTitle($bookData['title'] ?? '');
        $manifestation->setTitleTranscription($bookData['title_transcription'] ?? '');
        $manifestation->setIdentifier($bookData['identifier'] ?? '');
        $manifestation->setExternalIdentifier1($bookData['external_identifier1'] ?? '');
        $manifestation->setRecordSource($bookData['link'] ?? '');
        $manifestation->setType1('図書');
        $manifestation->setType2('紙');
        $manifestation->setContributor1($bookData['creator'] ?? '');
        $manifestation->setContributor2($bookData['publisher'] ?? '');
        $manifestation->setReleaseDateString($bookData['date'] ?? '');

        if (isset($bookData['ndc']) && count($bookData['ndc']) > 0) {
            $manifestation->setClass1($bookData['ndc'][0]);
            if (count($bookData['ndc']) > 1) {
                $manifestation->setClass2($bookData['ndc'][1]);
            }
        }

        return $manifestation;
    }
}