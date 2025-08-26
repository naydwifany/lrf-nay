<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterDocument;

class MasterDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [
            ['document_name' => 'Kontrak Kerja', 'document_code' => 'KK', 'is_active' => true],
            ['document_name' => 'Perjanjian Kerjasama', 'document_code' => 'PKS', 'is_active' => true],
            ['document_name' => 'MOU', 'document_code' => 'MOU', 'is_active' => true],
            ['document_name' => 'Agreement', 'document_code' => 'AGR', 'is_active' => true],
            ['document_name' => 'Surat Perjanjian', 'document_code' => 'SP', 'is_active' => true],
        ];

        foreach ($documents as $doc) {
            MasterDocument::create($doc);
        }
    }
}