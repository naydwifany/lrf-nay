@component('mail::message')
# LRF - Document Request Notification

Hello **{{ $documentRequest->nama }}**,

Your document request has been **{{ $submitType === 'submit' ? 'submitted' : 'saved as draft' }}**.

**Details:**
- Document Number: {{ $documentRequest->nomor_dokumen }}
- Title: {{ $documentRequest->title ?? '-' }}
- Status: {{ ucfirst($documentRequest->status) }}
- Division: {{ $documentRequest->divisi ?? '-' }}
- Department: {{ $documentRequest->dept ?? '-' }}

@if($submitType === 'submit')
@component('mail::panel')
Your document has entered the approval workflow.
@endcomponent
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent