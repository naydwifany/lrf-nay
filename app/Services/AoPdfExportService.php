<?php

namespace App\Services;

use Mpdf\Mpdf;
use App\Models\AgreementOverview;

class AoPdfExportService
{
    public function generate(AgreementOverview $ao): string
    {
        $pdfPath   = storage_path("app/agreements/ao_{$ao->id}.pdf");
        $stampPath = storage_path("app/templates/stamps/approved.png");
        $stampUrl  = 'file://' . $stampPath;

        $fmtDate = fn($date) => $date
            ? \Carbon\Carbon::parse($date)->isoFormat('D MMMM Y')
            : '-';

        // Helper untuk cell stamp
        $stampCell = fn($approvedAt) => $approvedAt
            ? '<img src="'.$stampUrl.'" width="80" height="80" />'
            : '<div style="height:80px"></div>';

        // Ambil data approval (contoh, sesuaikan sama model relasi kamu)
        $approvals = $ao->approvals->keyBy('role'); 
        // role bisa: head, finance, legal, director1, director2

        // Header info
        $html = <<<HTML
        <style>
            body { font-family: sans-serif; font-size: 11pt; }
            h2 { text-align: center; margin-bottom: 20px; }
            table { border-collapse: collapse; width: 100%; margin-top: 10px; }
            td, th { border: 1px solid #000; padding: 6px; font-size: 10pt; vertical-align: top; text-align: center; }
            .no-border td { border: none; }
            .label { width: 30%; text-align: left; }
            .value { width: 70%; text-align: left; }
        </style>

        <p><b>Nomor Dokumen:</b> {$ao->nomor_dokumen}</p>
        <h2>AGREEMENT OVERVIEW</h2>

        <table class='no-border'>
            <tr><td class='label'>Tanggal</td><td class='value'>: {$fmtDate($ao->tanggal_ao ?? $ao->created_at)}</td></tr>
            <tr><td class='label'>PIC</td><td class='value'>: {$ao->pic}</td></tr>
            <tr><td class='label'>Counter Party</td><td class='value'>: {$ao->counterparty}</td></tr>
            <tr><td class='label'>Deskripsi</td><td class='value'>: {$ao->deskripsi}</td></tr>
            <tr><td class='label'>Jangka Waktu Perjanjian</td><td class='value'>: {$fmtDate($ao->start_date_jk)} s.d. {$fmtDate($ao->end_date_jk)} ({$ao->duration})</td></tr>
            <tr><td class='label'>Resume singkat Perjanjian</td><td class='value'>: {$ao->resume}</td></tr>
            <tr><td class='label'>Ketentuan & Mekanisme</td><td class='value'>: {$ao->ketentuan_dan_mekanisme}</td></tr>
        </table>

        <br><br>

        <table border="1" cellspacing="0" cellpadding="5" width="100%" style="border-collapse:collapse; text-align:center; font-size:11pt;">
        <tr>
            <th colspan="1">Dibuat oleh,</th>
            <th colspan="1">Direview oleh,</th>
            <th colspan="1">Direview oleh,</th>
            <th colspan="2">Disetujui oleh,</th>
        </tr>
        <tr>
            <th>Site Dev</th>
            <th>Finance</th>
            <th>Legal</th>
            <th>BOD 1</th>
            <th>BOD 2</th>
        </tr>
        <tr>
            <td>{{approved_stamp_head}}</td>
            <td>{{approved_stamp_finance}}</td>
            <td>{{approved_stamp_legal}}</td>
            <td>{{approved_stamp_director1}}</td>
            <td>{{approved_stamp_director2}}</td>
        </tr>
        <tr>
            <td>{{approver_name_head}}</td>
            <td>{{approver_name_finance}}</td>
            <td>{{approver_name_legal}}</td>
            <td>{{approver_name_director1}}</td>
            <td>{{approver_name_director2}}</td>
        </tr>
        <tr>
            <td>Tgl Ttd: {{approved_at_head}}</td>
            <td>Tgl Ttd: {{approved_at_finance}}</td>
            <td>Tgl Ttd: {{approved_at_legal}}</td>
            <td>Tgl Ttd: {{approved_at_director1}}</td>
            <td>Tgl Ttd: {{approved_at_director2}}</td>
        </tr>
        <tr>
            <td>Tgl AO: {{submitted_at}}</td>
            <td>Tgl AO: {{submitted_at}}</td>
            <td>Tgl AO: {{submitted_at}}</td>
            <td>Tgl AO: {{submitted_at}}</td>
            <td>Tgl AO: {{submitted_at}}</td>
        </tr>
        </table>

        <br>
        <p style='font-size:9pt;'>
            *Tanggal AO adalah tanggal saat Agreement Overview diterima oleh Penandatangan.
        </p>
        HTML;

        // Replace placeholders
        $replacements = [
            '{{approved_stamp_head}}'      => $stampCell($approvals['head']->approved_at ?? null),
            '{{approved_stamp_finance}}'   => $stampCell($approvals['finance']->approved_at ?? null),
            '{{approved_stamp_legal}}'     => $stampCell($approvals['legal']->approved_at ?? null),
            '{{approved_stamp_director1}}' => $stampCell($approvals['director1']->approved_at ?? null),
            '{{approved_stamp_director2}}' => $stampCell($approvals['director2']->approved_at ?? null),

            '{{approver_name_head}}'       => $approvals['head']->approver_name ?? '',
            '{{approver_name_finance}}'    => $approvals['finance']->approver_name ?? '',
            '{{approver_name_legal}}'      => $approvals['legal']->approver_name ?? '',
            '{{approver_name_director1}}'  => $approvals['director1']->approver_name ?? '',
            '{{approver_name_director2}}'  => $approvals['director2']->approver_name ?? '',

            '{{approved_at_head}}'         => $fmtDate($approvals['head']->approved_at ?? null),
            '{{approved_at_finance}}'      => $fmtDate($approvals['finance']->approved_at ?? null),
            '{{approved_at_legal}}'        => $fmtDate($approvals['legal']->approved_at ?? null),
            '{{approved_at_director1}}'    => $fmtDate($approvals['director1']->approved_at ?? null),
            '{{approved_at_director2}}'    => $fmtDate($approvals['director2']->approved_at ?? null),
        ];

        $html = strtr($html, $replacements);

        // Render PDF
        $mpdf = new Mpdf([
            'format' => 'A4',
            'margin_left'   => 20,
            'margin_right'  => 20,
            'margin_top'    => 20,
            'margin_bottom' => 20,
        ]);

        $mpdf->WriteHTML($html);
        $mpdf->Output($pdfPath, \Mpdf\Output\Destination::FILE);

        return $pdfPath;
    }
}