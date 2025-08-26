namespace App\Services;

use App\Models\AgreementOverview;
use App\Models\DocumentRequest;
use App\Models\DocumentComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AgreementOverviewService
{
    protected $approvalService;

    public function __construct()
    {
        $this->approvalService = app(AgreementApprovalService::class);
    }

    /**
     * Create new Agreement Overview with attachment copy (filesystem only)
     */
    public function create(User $user, array $data): AgreementOverview
    {
        try {
            DB::beginTransaction();

            // Auto-fill director from supervisor line
            $directorData = $this->getDirectorFromSupervisorLine($user);
            
            if (empty($data['nomor_dokumen'])) {
                $data['nomor_dokumen'] = $this->generateAONumber();
            }

            // Store director info in existing fields
            $data['nama_direksi_default'] = $directorData['name'];
            $data['nik_direksi'] = $data['nik_direksi'] ?? $directorData['nik']; // Use selected or default

            $agreementOverview = AgreementOverview::create([
                'nik' => $user->nik,
                'nama' => $user->name,
                'jabatan' => $user->jabatan,
                'divisi' => $user->divisi,
                'direktorat' => $user->direktorat,
                'level' => $user->level ?? '',
                'is_draft' => true,
                'status' => AgreementOverview::STATUS_DRAFT,
                ...$data
            ]);

            // Copy attachments to filesystem and store info as JSON
            $sourceDocId = $data['lrf_doc_id'] ?? null;
            if ($sourceDocId) {
                $this->copyDiscussionAttachmentsToFilesystem($agreementOverview, $sourceDocId);
            }

            DB::commit();
            
            Log::info('Agreement Overview created successfully', [
                'ao_id' => $agreementOverview->id,
                'user_nik' => $user->nik,
                'source_doc' => $sourceDocId
            ]);

            return $agreementOverview;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create Agreement Overview: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Submit Agreement Overview for approval
     */
    public function submit(AgreementOverview $agreementOverview): bool
    {
        try {
            DB::beginTransaction();

            // Validate before submission
            $errors = $agreementOverview->validateForSubmission();
            if (!empty($errors)) {
                throw new \Exception('Validation failed: ' . implode(', ', $errors));
            }

            $agreementOverview->update([
                'is_draft' => false,
                'status' => AgreementOverview::STATUS_PENDING_HEAD,
                'submitted_at' => now()
            ]);

            // Create approval workflow
            $this->approvalService->createApprovalWorkflow($agreementOverview);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to submit Agreement Overview: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Copy attachments to filesystem (no database table)
     */
    protected function copyDiscussionAttachmentsToFilesystem(AgreementOverview $agreementOverview, int $sourceDocId): void
    {
        try {
            $documentRequest = DocumentRequest::find($sourceDocId);
            if (!$documentRequest) {
                return;
            }

            $lastCommentWithAttachments = DocumentComment::where('document_request_id', $documentRequest->id)
                ->whereHas('attachmentFiles')
                ->where('is_forum_closed', false)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$lastCommentWithAttachments) {
                return;
            }

            $attachments = $lastCommentWithAttachments->attachmentFiles;
            $copiedAttachments = [];

            foreach ($attachments as $attachment) {
                try {
                    $copiedInfo = $this->copyAttachmentFile($attachment, $agreementOverview);
                    if ($copiedInfo) {
                        $copiedAttachments[] = $copiedInfo;
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to copy attachment: ' . $e->getMessage());
                }
            }

            // Store attachment info as JSON in existing field or metadata
            if (!empty($copiedAttachments)) {
                $agreementOverview->update([
                    'attachment_info' => json_encode($copiedAttachments)
                ]);
            }

            Log::info('Copied discussion attachments', [
                'ao_id' => $agreementOverview->id,
                'attachments_copied' => count($copiedAttachments)
            ]);

        } catch (\Exception $e) {
            Log::error('Error copying discussion attachments: ' . $e->getMessage());
        }
    }

    /**
     * Copy single attachment file
     */
    protected function copyAttachmentFile($attachment, AgreementOverview $agreementOverview): ?array
    {
        if (!Storage::disk('public')->exists($attachment->file_path)) {
            return null;
        }

        $extension = pathinfo($attachment->original_filename, PATHINFO_EXTENSION);
        $newFilename = Str::uuid() . '.' . $extension;
        $newPath = "agreement-attachments/{$agreementOverview->id}/{$newFilename}";

        Storage::disk('public')->copy($attachment->file_path, $newPath);

        return [
            'id' => Str::uuid(),
            'filename' => $newFilename,
            'original_filename' => $attachment->original_filename,
            'file_path' => $newPath,
            'mime_type' => $attachment->mime_type,
            'file_size' => $attachment->file_size,
            'source' => 'discussion',
            'source_comment_id' => $attachment->document_comment_id,
            'uploaded_by_nik' => $agreementOverview->nik,
            'uploaded_by_name' => $agreementOverview->nama,
            'copied_at' => now()->toDateTimeString()
        ];
    }

    /**
     * Get director from supervisor line
     */
    protected function getDirectorFromSupervisorLine(User $user): array
    {
        try {
            $currentUser = $user;
            $maxLevels = 5;
            $level = 0;

            while ($level < $maxLevels) {
                if (!$currentUser->supervisor_nik) {
                    break;
                }

                $supervisor = User::where('nik', $currentUser->supervisor_nik)->first();
                if (!$supervisor) {
                    break;
                }

                if ($this->isDirector($supervisor)) {
                    return [
                        'nik' => $supervisor->nik,
                        'name' => $supervisor->name,
                        'direktorat' => $supervisor->direktorat ?? $user->direktorat
                    ];
                }

                $currentUser = $supervisor;
                $level++;
            }

            // Fallback
            $defaultDirector = User::where('direktorat', $user->direktorat)
                ->where(function($query) {
                    $query->where('role', 'like', '%director%')
                          ->orWhere('jabatan', 'like', '%direktur%');
                })
                ->first();

            if ($defaultDirector) {
                return [
                    'nik' => $defaultDirector->nik,
                    'name' => $defaultDirector->name,
                    'direktorat' => $defaultDirector->direktorat
                ];
            }

            return [
                'nik' => 'DIR_DEFAULT',
                'name' => 'Default Director',
                'direktorat' => $user->direktorat ?? 'Unknown'
            ];

        } catch (\Exception $e) {
            Log::error('Error getting director: ' . $e->getMessage());
            return [
                'nik' => 'DIR_ERROR',
                'name' => 'Director (Error)',
                'direktorat' => $user->direktorat ?? 'Unknown'
            ];
        }
    }

    protected function isDirector(User $user): bool
    {
        $role = strtolower($user->role ?? '');
        $jabatan = strtolower($user->jabatan ?? '');

        return str_contains($role, 'director') || 
               str_contains($jabatan, 'direktur') || 
               str_contains($jabatan, 'director');
    }

    protected function generateAONumber(): string
    {
        $lastAO = AgreementOverview::latest('id')->first();
        $nextId = $lastAO ? ($lastAO->id + 1) : 1;
        $seqNumber = str_pad($nextId, 4, '0', STR_PAD_LEFT);
        $month = date('m');
        $year = date('Y');
        
        return "AO/{$seqNumber}/{$month}/{$year}";
    }

    /**
     * Get available directors
     */
    public static function getAvailableDirectors(): array
    {
        try {
            return User::where(function($query) {
                    $query->where('role', 'like', '%director%')
                          ->orWhere('jabatan', 'like', '%direktur%');
                })
                ->where('is_active', true)
                ->pluck('name', 'nik')
                ->toArray();
        } catch (\Exception $e) {
            return [
                'DIR001' => 'Director 1',
                'DIR002' => 'Director 2'
            ];
        }
    }

    /**
     * Get attachment stats from JSON
     */
    public function getAttachmentStats(AgreementOverview $agreementOverview): array
    {
        $attachmentInfo = $agreementOverview->attachment_info ?? [];
        $discussionAttachments = collect($attachmentInfo)->where('source', 'discussion');
        
        return [
            'total_attachments' => count($attachmentInfo),
            'discussion_attachments' => $discussionAttachments->count(),
            'direct_attachments' => count($attachmentInfo) - $discussionAttachments->count(),
            'total_size' => $this->formatFileSize($discussionAttachments->sum('file_size'))
        ];
    }

    protected function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
