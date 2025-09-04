<?php

namespace App\Services;

use App\Models\AgreementOverview;
use App\Models\AgreementApproval;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf as PdfWriter;

class AoExcelExportService
{
    public function generate(AgreementOverview $ao): string
    {
        // 1) load template
        $templatePath = storage_path('app/templates/ao_template.xlsx');
        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // 2) ambil data AO & approvals
        $approvals = AgreementApproval::where('agreement_overview_id', $ao->id)->get();

        $fmtDate = fn($date) => $date ? Carbon::parse($date)->isoFormat('D MMMM Y') : '-';

        // helper: ambil approval record by type (string)
        $get = function (string $type) use ($approvals) {
            return $approvals->where('approval_type', $type)->sortBy('approved_at')->first();
        };

        // khusus director bisa 2 orang tapi approval_type sama ("selected_director")
        $directors = $approvals
            ->where('approval_type', AgreementApproval::TYPE_SELECTED_DIRECTOR)
            ->sortBy('approved_at')
            ->values(); // index 0 dan 1

        $director1 = $directors->get(0);
        $director2 = $directors->get(1) ?: null; // bisa null kalau orangnya sama / cuma sekali approve

        // 3) isi field header
        $sheet->setCellValue('B2', $ao->nomor_dokumen ?? '-');
        $sheet->setCellValue('E6', $fmtDate($ao->tanggal ?? $ao->created_at));
        $sheet->setCellValue('E7', $ao->pic ?? '-');
        $sheet->setCellValue('E8', $ao->counter_party ?? '-');
        $sheet->setCellValue('E9', $ao->deskripsi ?? '-');

        // contoh jangka waktu: "15 April 2024 s.d 14 April 2026 (2 tahun)"
        $jangka = $ao->mulai && $ao->selesai
            ? sprintf('%s s.d %s (%s)',
                $fmtDate($ao->mulai),
                $fmtDate($ao->selesai),
                $ao->durasi_text ?? ''
            )
            : ($ao->jangka_waktu_text ?? '-');
        $sheet->setCellValue('E10', $jangka);

        $sheet->setCellValue('E11', $ao->resume_singkat ?? '-');

        // ketentuan komersial (pisahkan per baris)
        $sheet->setCellValue('E13', $ao->ketentuan_1 ?? 'Harga Sewa : -');
        $sheet->setCellValue('E14', $ao->ketentuan_2 ?? 'Harga Service Charge : -');

        $sheet->setCellValue('E16', $ao->mekanisme_pembayaran ?? '-');

        // 4) isi nama & tanggal per kolom tanda tangan
        // Ambil nama dari approval kalau ada, fallback dari relasi user/project owner
        $sheet->setCellValue('B25', $ao->dibuat_oleh_nama ?? ($get('head')?->approver_name ?? '-'));         // Site Dev
        $sheet->setCellValue('D25', $get(AgreementApproval::TYPE_HEAD_FINANCE)?->approver_name ?? '-');     // Finance Head
        $sheet->setCellValue('E25', $get(AgreementApproval::TYPE_HEAD_LEGAL)?->approver_name ?? '-');       // Legal Head

        $sheet->setCellValue('F25', $director1?->approver_name ?? '-');                                     // BOD 1
        $sheet->setCellValue('G25', $director2?->approver_name ?? ($director1?->approver_name ?? '-'));     // BOD 2 (bisa sama)

        // tanggal tanda tangan (approved_at) dan Tgl AO (tanggal AO masuk)
        $sheet->setCellValue('B26', $fmtDate($get('head')?->approved_at));                                   // Tgl Tdd Site Dev
        $sheet->setCellValue('B27', $fmtDate($ao->created_at));                                              // Tgl AO Site Dev

        $sheet->setCellValue('D26', $fmtDate($get(AgreementApproval::TYPE_HEAD_FINANCE)?->approved_at));
        $sheet->setCellValue('D27', $fmtDate($ao->created_at));

        $sheet->setCellValue('E26', $fmtDate($get(AgreementApproval::TYPE_HEAD_LEGAL)?->approved_at));
        $sheet->setCellValue('E27', $fmtDate($ao->created_at));

        $sheet->setCellValue('F26', $fmtDate($director1?->approved_at));
        $sheet->setCellValue('F27', $fmtDate($ao->created_at));

        $sheet->setCellValue('G26', $fmtDate($director2?->approved_at));
        $sheet->setCellValue('G27', $fmtDate($ao->created_at));

        // 5) cap APPROVE di tiap kotak yang sudah approved
        $stampPath = storage_path('app/templates/stamps/approved.png');
        $putStamp = function (string $cell, string $label) use ($sheet, $stampPath) {
            if (!is_file($stampPath)) return;
            $d = new Drawing();
            $d->setName("APPROVED {$label}");
            $d->setPath($stampPath);
            $d->setHeight(30);                  // sesuaikan ukuran cap
            $d->setCoordinates($cell);          // pojok kiri-atas kotak tanda tangan
            $d->setOffsetX(10);
            $d->setOffsetY(5);
            $d->setWorksheet($sheet);
        };

        if ($get('head')?->approved_at)                        $putStamp('B22', 'Site Dev');
        if ($get(AgreementApproval::TYPE_HEAD_FINANCE)?->approved_at) $putStamp('D22', 'Finance');
        if ($get(AgreementApproval::TYPE_HEAD_LEGAL)?->approved_at)   $putStamp('E22', 'Legal');
        if ($director1?->approved_at)                          $putStamp('F22', 'BOD 1');
        if ($director2?->approved_at || (!$director2 && $director1?->approved_at))
                                                               $putStamp('G22', 'BOD 2');

        // 6) wrap text area panjang (biar aman kalau template berubah)
        foreach (['B9','B10','B11','B13','B14','B16'] as $addr) {
            $sheet->getStyle($addr)->getAlignment()->setWrapText(true);
        }

        // 7) export PDF
        // pastikan mpdf sudah terinstall
        $pdfPath = storage_path('app/agreements/ao-' . $ao->id . '.pdf');

        // opsi page setup (tambahan, kalau perlu)
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);

        if (!is_dir(dirname($pdfPath))) {
            mkdir(dirname($pdfPath), 0775, true);
        }

        $writer = new PdfWriter($spreadsheet);
        $writer->save($pdfPath);

        return $pdfPath;
    }

    /**
     * Helper untuk action download (langsung response()->download).
     */
    public function download(AgreementOverview $ao)
    {
        $path = $this->generate($ao);
        return response()->download($path)->deleteFileAfterSend(true);
    }
}