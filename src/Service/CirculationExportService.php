<?php

namespace App\Service;

use App\Entity\Checkout;
use App\Entity\Reservation;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class CirculationExportService
{
    /**
     * @param Reservation[] $reservations
     * @param Checkout[] $checkouts
     */
    public function generateStatusExportFile(string $type, array $reservations, array $checkouts): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $rows = $this->buildRows($type, $reservations, $checkouts);

        $sheet->fromArray($rows, null, 'A1', true);

        $lastColLetter = Coordinate::stringFromColumnIndex(count($rows[0] ?? ['A']));
        $sheet->getStyle("A1:{$lastColLetter}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColLetter}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DDDDDD');

        for ($i = 1, $iMax = count($rows[0] ?? ['A']); $i <= $iMax; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'circulation_export_');
        if ($tempFile === false) {
            throw new RuntimeException('一時ファイルの作成に失敗しました。');
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    /**
     * @param Reservation[] $reservations
     * @param Checkout[] $checkouts
     * @return array<int, array<int, string>>
     */
    private function buildRows(string $type, array $reservations, array $checkouts): array
    {
        if ($type === 'reserve') {
            $rows = [[
                '予約日時',
                '利用者ID',
                '利用者氏名',
                '資料ID',
                '資料名',
                'ステータス',
                '予約期限',
            ]];
            foreach ($reservations as $reservation) {
                $rows[] = [
                    $this->formatUnixDateTime($reservation->getReservedAt()),
                    (string) $reservation->getMember()?->getIdentifier(),
                    (string) $reservation->getMember()?->getFullName(),
                    (string) $reservation->getManifestation()?->getIdentifier(),
                    (string) $reservation->getManifestation()?->getTitle(),
                    $reservation->getStatus(),
                    $this->formatUnixDateTime($reservation->getExpiryDate()),
                ];
            }
            return $rows;
        }

        if ($type === 'checkout') {
            $rows = [[
                '貸出日時',
                '利用者ID',
                '利用者氏名',
                '資料ID',
                '資料名',
                '返却期限',
                'ステータス',
            ]];
            foreach ($checkouts as $checkout) {
                $rows[] = [
                    $this->formatDateTime($checkout->getCheckedOutAt()),
                    (string) $checkout->getMember()?->getIdentifier(),
                    (string) $checkout->getMember()?->getFullName(),
                    (string) $checkout->getManifestation()?->getIdentifier(),
                    (string) $checkout->getManifestation()?->getTitle(),
                    $this->formatDateTime($checkout->getDueDate(), 'Y-m-d'),
                    $this->normalizeCheckoutStatus($checkout->getStatus()),
                ];
            }
            return $rows;
        }

        $rows = [[
            '返却日時',
            '利用者ID',
            '利用者氏名',
            '資料ID',
            '資料名',
            '貸出日時',
            'ステータス',
        ]];
        foreach ($checkouts as $checkout) {
            $rows[] = [
                $this->formatDateTime($checkout->getCheckedInAt()),
                (string) $checkout->getMember()?->getIdentifier(),
                (string) $checkout->getMember()?->getFullName(),
                (string) $checkout->getManifestation()?->getIdentifier(),
                (string) $checkout->getManifestation()?->getTitle(),
                $this->formatDateTime($checkout->getCheckedOutAt()),
                $this->normalizeCheckoutStatus($checkout->getStatus()),
            ];
        }
        return $rows;
    }

    private function formatUnixDateTime(?int $timestamp): string
    {
        if ($timestamp === null || $timestamp <= 0) {
            return '';
        }
        return date('Y-m-d H:i', $timestamp);
    }

    private function formatDateTime(?\DateTimeInterface $dateTime, string $format = 'Y-m-d H:i'): string
    {
        if ($dateTime === null) {
            return '';
        }
        return $dateTime->format($format);
    }

    private function normalizeCheckoutStatus(string $status): string
    {
        return match ($status) {
            Checkout::STATUS_CHECKED_OUT => '貸出中',
            Checkout::STATUS_RETURNED => '返却済',
            default => $status,
        };
    }
}
