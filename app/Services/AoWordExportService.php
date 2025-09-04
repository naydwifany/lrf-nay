<?php

namespace App\Services;

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Writer\PDF\Mpdf as PdfWriter;
use Mpdf\Mpdf;
use App\Models\AgreementOverview;

class AoWordExportService
{
    public function generate(AgreementOverview $ao): string
    {
        $templatePath = storage_path('app/templates/ao_template.docx');
        $tempDocxPath = storage_path("app/agreements/ao_{$ao->id}.docx");
        $pdfPath = storage_path("app/agreements/ao_{$ao->id}.pdf");

        $fmtDate = fn($date) => $date ? \Carbon\Carbon::parse($date)->isoFormat('D MMMM Y') : '-';

        // 1. Isi template DOCX
        $template = new TemplateProcessor($templatePath);
        $template->setValue('nomor_dokumen', $ao->nomor_dokumen ?? '-');
        $template->setValue('tanggal_ao', $fmtDate($ao->tanggal_ao ?? $ao->created_at));
        $template->setValue('creator', $ao->pic ?? '-');
        $template->setValue('counterparty', $ao->counterparty ?? '-');
        $template->setValue('deskripsi', $ao->deskripsi ?? '-');

        // Agreement duration
        $template->setValue('start_date_jk', $fmtDate($ao->start_date_jk));
        $template->setValue('end_date_jk', $fmtDate($ao->end_date_jk));

        $template->setValue('resume', $ao->resume ?? '-');
        $template->setValue('ketentuan_dan_mekanisme', $ao->ketentuan_dan_mekanisme ?? '-');

        $template->saveAs($tempDocxPath);

        // load back docx
        $phpWord = IOFactory::load($tempDocxPath);

        // simpan ke HTML dulu
        $tempHtmlPath = storage_path("app/agreements/tmp_{$ao->id}.html");
        $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
        $htmlWriter->save($tempHtmlPath);

        // ambil isi HTML
        $html = file_get_contents($tempHtmlPath);

        // render ke PDF manual pakai mPDF
        $mpdf = new Mpdf([
            'margin_left'   => 20,
            'margin_right'  => 20,
            'margin_top'    => 20,
            'margin_bottom' => 20,
        ]);
        $mpdf->WriteHTML($html);
        $mpdf->Output($pdfPath, \Mpdf\Output\Destination::FILE);

        // convert to PDF
        $writer = new PdfWriter($phpWord);
        $writer->save($pdfPath);

        return $pdfPath;
    }
}