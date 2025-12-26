<?php

namespace App\Controller;

use App\Form\ManifestationFileExportFormType;
use App\Form\ManifestationFileImportFormType;
use App\Repository\ManifestationRepository;
use App\Service\FileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ManifestationExportImportController extends AbstractController
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/file/import', name: 'app_manifestation_file_import', methods: ['GET', 'POST'])]
    public function import(Request $request, FileService $fileService): Response
    {
        $form = $this->createForm(ManifestationFileImportFormType::class);
        $form->handleRequest($request);

        $result = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadFile = $form->get('uploadFile')->getData();

            if ($uploadFile) {
                $result = $fileService->importManifestationsFromFile($uploadFile);

                if ($result['errors'] === 0 && $result['success'] > 0) {
                    $this->addFlash('success', sprintf('インポート完了: %d件（スキップ: %d件）', $result['success'], $result['skipped']));
                } elseif ($result['errors'] > 0) {
                    $this->addFlash('danger', 'インポート中にエラーが発生しました。結果を確認してください。');
                } else {
                    $this->addFlash('warning', 'インポート対象がありませんでした。');
                }
            } else {
                $this->addFlash('warning', 'ファイルを選択してください。');
            }
        }

        return $this->render('import/file.html.twig', [
            'form' => $form->createView(),
            'result' => $result,
        ]);
    }

    #[Route('/file/export', name: 'app_manifestation_file_export', methods: ['GET', 'POST'])]
    public function export(Request $request, ManifestationRepository $manifestationRepository, FileService $fileService): Response
    {
        $form = $this->createForm(ManifestationFileExportFormType::class);
        $form->handleRequest($request);

        // GET: まず画面表示（検索条件があればそのまま引き継ぐ）
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('export/file.html.twig', [
                'form' => $form->createView(),
                'query' => $request->query->all(),
            ]);
        }

        $this->logger->info('エクスポート処理を開始');
        
        $format = (string) $form->get('format')->getData(); // xlsx|csv

        // 検索条件の取得（画面表示時は query、POST時も query の条件を引き継ぐ）
        $searchTitle = $request->query->get('title');
        $searchAuthor = $request->query->get('author');
        $searchPublisher = $request->query->get('publisher');
        $searchIsbn = $request->query->get('isbn');

        if ($searchTitle || $searchAuthor || $searchPublisher || $searchIsbn) {
            $manifestations = $manifestationRepository->findBySearchCriteria(
                $searchTitle,
                $searchAuthor,
                $searchPublisher,
                $searchIsbn
            );
        } else {
            $manifestations = $manifestationRepository->findAll();
        }

        $columns = $form->get('columns')->getData();

        $this->logger->info(var_export($columns, true));

        $tempFile = $fileService->generateExportFile($manifestations, $format, $columns);

        $fileNameParts = ['manifestations'];
        if ($searchTitle) $fileNameParts[] = 'title-' . substr(preg_replace('/[^a-z0-9]/i', '', (string)$searchTitle), 0, 10);
        if ($searchAuthor) $fileNameParts[] = 'author-' . substr(preg_replace('/[^a-z0-9]/i', '', (string)$searchAuthor), 0, 10);

        $baseName = implode('_', $fileNameParts) . '_' . date('Y-m-d_H-i-s');
        $fileName = $baseName . ($format === 'csv' ? '.csv' : '.xlsx');

        return $this->file($tempFile, $fileName, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }
}
