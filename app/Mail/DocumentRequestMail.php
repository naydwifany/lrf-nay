<?php

namespace App\Mail;

use App\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DocumentRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public DocumentRequest $documentRequest;
    public string $submitType;

    /**
     * Create a new message instance.
     */
    public function __construct(DocumentRequest $documentRequest, string $submitType = 'submit')
    {
        $this->documentRequest = $documentRequest;
        $this->submitType = $submitType;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Document Submitted')
                    ->markdown('emails.document-request-created')
                    ->with([
                        'documentRequest' => $this->documentRequest,
                        'submitType' => $this->submitType,
                    ]);
    }
}