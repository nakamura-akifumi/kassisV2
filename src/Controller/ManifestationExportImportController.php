<?php

namespace App\Controller;

use App\Form\ManifestationFileExportFormType;
use App\Form\ManifestationFileImportFormType;
use App\Repository\ManifestationRepository;
use App\Service\ExcelFileService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ManifestationExportImportController extends AbstractController
{
    #[Route('/file/import', name: 'app_manifestation_file_import', methods: ['GET', 'POST'])]
    public function import(Request $request, ExcelFileService $excelFileService): Response
    {
        // ... existing code ...
        $form = $this->createForm(ManifestationFileImportFormType::class);
        $form->handleRequest($request);

        $result = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadFile = $form->get('uploadFile')->getData();

            if ($uploadFile) {
                $result = $excelFileService->importManifestationsFromFile($uploadFile);

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
        // ... existing code ...
    }

    #[Route('/file/export', name: 'app_manifestation_file_export', methods: ['GET', 'POST'])]
    public function export(Request $request, ManifestationRepository $manifestationRepository): Response
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

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', 'タイトル');
        $sheet->setCellValue('C1', '著者');
        $sheet->setCellValue('D1', '出版社');
        $sheet->setCellValue('E1', '出版年');
        $sheet->setCellValue('F1', 'ISBN');

        $row = 2;
        foreach ($manifestations as $manifestation) {
            $sheet->setCellValue('A' . $row, $manifestation->getId());
            $sheet->setCellValue('B' . $row, $manifestation->getTitle());
            $sheet->setCellValue('C' . $row, $manifestation->getContributor1());
            $sheet->setCellValue('D' . $row, $manifestation->getContributor2());
            $sheet->setCellValue('E' . $row, $manifestation->getReleaseDateString());
            $sheet->setCellValue('F' . $row, $manifestation->getExternalIdentifier1());
            $row++;
        }

        // xlsx の場合だけ見栄えを整える（csvには不要）
        if ($format === 'xlsx') {
            $sheet->getStyle('A1:F1')->getFont()->setBold(true);
            $sheet->getStyle('A1:F1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('DDDDDD');

            foreach (range('A', 'F') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        $fileNameParts = ['manifestations'];
        if ($searchTitle) $fileNameParts[] = 'title-' . substr(preg_replace('/[^a-z0-9]/i', '', $searchTitle), 0, 10);
        if ($searchAuthor) $fileNameParts[] = 'author-' . substr(preg_replace('/[^a-z0-9]/i', '', $searchAuthor), 0, 10);

        $baseName = implode('_', $fileNameParts) . '_' . date('Y-m-d_H-i-s');
        $fileName = $baseName . ($format === 'csv' ? '.csv' : '.xlsx');

        $tempFile = tempnam(sys_get_temp_dir(), 'export_');
        if ($tempFile === false) {
            throw new \RuntimeException('一時ファイルの作成に失敗しました。');
        }

        if ($format === 'csv') {
            $writer = new Csv($spreadsheet);
            $writer->setDelimiter(',');
            $writer->setEnclosure('"');
            $writer->setLineEnding("\n");
            $writer->setUseBOM(true); // Excelで文字化けしにくくする
            $writer->save($tempFile);
        } else {
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempFile);
        }

        return $this->file($tempFile, $fileName, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }
}
