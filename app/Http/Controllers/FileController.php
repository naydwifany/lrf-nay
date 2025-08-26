<?php
// 1. FIX FILE DOWNLOAD - app/Http/Controllers/FileController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class FileController extends Controller
{
    public function download(Request $request, $filename)
    {
        try {
            // Clean the filename
            $cleanFilename = basename($filename);
            
            // Check if file exists in storage
            if (!Storage::disk('public')->exists($cleanFilename)) {
                abort(404, 'File not found');
            }
            
            $filePath = Storage::disk('public')->path($cleanFilename);
            $originalName = $this->getOriginalFileName($cleanFilename);
            
            // Get correct MIME type
            $mimeType = $this->getCorrectMimeType($filePath);
            
            return response()->download($filePath, $originalName, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'attachment; filename="' . $originalName . '"'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('File download error: ' . $e->getMessage());
            abort(404, 'File not found or corrupted');
        }
    }
    
    private function getCorrectMimeType($filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'txt' => 'text/plain',
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
    
    private function getOriginalFileName($filename): string
    {
        // Try to get original name from database or use the filename as is
        // You might want to store original filenames in database
        return $filename;
    }
}