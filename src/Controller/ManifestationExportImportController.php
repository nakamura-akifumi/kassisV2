<?php

namespace App\Controller;

use App\Entity\Manifestation;
use App\Form\ManifestationType;
use App\Repository\ManifestationRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ManifestationExportImportController extends AbstractController
{
    #[Route('/file/export', name: 'app_manifestation_file_export', methods: ['GET'])]
    public function export(Request $request, ManifestationRepository $manifestationRepository): Response
    {
        // 検索条件の取得
        $searchTitle = $request->query->get('title');
        $searchAuthor = $request->query->get('author');
        $searchPublisher = $request->query->get('publisher');
        $searchIsbn = $request->query->get('isbn');
        // その他必要な検索条件を追加

        // 検索条件が存在する場合、それに基づいてデータを取得
        if ($searchTitle || $searchAuthor || $searchPublisher || $searchIsbn) {
            $manifestations = $manifestationRepository->findBySearchCriteria(
                $searchTitle,
                $searchAuthor,
                $searchPublisher,
                $searchIsbn
            // その他の検索条件を追加
            );
        } else {
            // 検索条件がない場合は全データを取得
            $manifestations = $manifestationRepository->findAll();
        }

        // Spreadsheetの生成
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // ヘッダー行の設定
        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', 'タイトル');
        $sheet->setCellValue('C1', '著者');
        $sheet->setCellValue('D1', '出版社');
        $sheet->setCellValue('E1', '出版年');
        $sheet->setCellValue('F1', 'ISBN');
        // 必要に応じて他のフィールドも追加

        // データの書き込み
        $row = 2;
        foreach ($manifestations as $manifestation) {
            $sheet->setCellValue('A' . $row, $manifestation->getId());
            $sheet->setCellValue('B' . $row, $manifestation->getTitle());
            $sheet->setCellValue('C' . $row, $manifestation->getContributor1());
            $sheet->setCellValue('D' . $row, $manifestation->getContributor2());
            $sheet->setCellValue('E' . $row, $manifestation->getReleaseDateString());
            $sheet->setCellValue('F' . $row, $manifestation->getExternalIdentifier1());
            // 他のフィールドも追加
            $row++;
        }

        // ヘッダー行のスタイル設定
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A1:F1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DDDDDD');

        // 列幅の自動調整
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ファイル名に検索条件を反映
        $fileNameParts = ['manifestations'];
        if ($searchTitle) $fileNameParts[] = 'title-' . substr(preg_replace('/[^a-z0-9]/i', '', $searchTitle), 0, 10);
        if ($searchAuthor) $fileNameParts[] = 'author-' . substr(preg_replace('/[^a-z0-9]/i', '', $searchAuthor), 0, 10);

        $fileName = implode('_', $fileNameParts) . '_' . date('Y-m-d_H-i-s') . '.xlsx';

        // ファイルの生成
        $writer = new Xlsx($spreadsheet);
        $temp_file = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($temp_file);

        // レスポンスの返却
        return $this->file($temp_file, $fileName, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

}
