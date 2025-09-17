<?php
// app/Filament/User/Resources/MyHistoryResource.php

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\MyHistoryResource\Pages;
use App\Models\DocumentRequest;
use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\FontWeight;

class MyHistoryResource extends Resource
{
    protected static ?string $model = DocumentRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Document History';
    protected static ?string $modelLabel = 'Document History';
    protected static ?string $pluralModelLabel = 'Document History';
    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('approvals', function ($query) {
                $query->where('approver_nik', auth()->user()->nik)
                    ->whereIn('status', [
                        \App\Models\DocumentApproval::STATUS_APPROVED,
                        \App\Models\DocumentApproval::STATUS_REJECTED,
                    ]);
            });
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Diunggah')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('nomor_dokumen')
                    ->label('Nomor Dokumen')
                    ->searchable()
                    ->placeholder('Not assigned'),
                Tables\Columns\TextColumn::make('title')
                    ->label('Nama Mitra')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('nama')
                    ->label('PIC')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('dept')
                    ->label('Departemen')
                    ->searchable(),
                Tables\Columns\TextColumn::make('doctype.document_name')
                    ->label('Jenis Perjanjian')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\BadgeColumn::make('decision')
                    ->label('Your Decision')
                    ->getStateUsing(function ($record) {
                        $userNik = auth()->user()->nik;

                        // Ambil approval yang spesifik untuk user login
                        $approval = $record->approvals->firstWhere('approver_nik', $userNik);

                        if (!$approval) {
                            return null; // belum ada keputusan
                        }

                        return $approval->status; // 'approved' / 'rejected'
                    })
                    ->colors([
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ])
                    ->formatStateUsing(fn($state) => match($state) {
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        default => 'Pending',
                    }),
                Tables\Columns\BadgeColumn::make('computed_status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending_supervisor',
                        'info'    => 'pending_gm',
                        'primary' => ['pending_legal', 'pending_legal_admin'],
                        'gray'    => 'submitted',

                        // AO stages
                        'purple'  => \App\Models\AgreementOverview::STATUS_PENDING_HEAD,
                        'success' => \App\Models\AgreementOverview::STATUS_APPROVED,
                        'danger'  => \App\Models\AgreementOverview::STATUS_REJECTED,
                    ])
                    ->formatStateUsing(function ($state, $record) {
                        // sekarang $state sudah computed_status (bisa AO / docreq)
                        return match ($state) {
                            \App\Models\AgreementOverview::STATUS_DRAFT             => 'AO Draft',
                            \App\Models\AgreementOverview::STATUS_PENDING_HEAD      => 'AO - Pending Head',
                            \App\Models\AgreementOverview::STATUS_PENDING_GM        => 'AO - Pending GM',
                            \App\Models\AgreementOverview::STATUS_PENDING_FINANCE   => 'AO - Pending Finance',
                            \App\Models\AgreementOverview::STATUS_PENDING_LEGAL     => 'AO - Pending Legal',
                            \App\Models\AgreementOverview::STATUS_PENDING_DIRECTOR1 => 'AO - Pending Director 1',
                            \App\Models\AgreementOverview::STATUS_PENDING_DIRECTOR2 => 'AO - Pending Director 2',
                            \App\Models\AgreementOverview::STATUS_APPROVED          => 'AO Approved',
                            \App\Models\AgreementOverview::STATUS_REJECTED          => 'AO Rejected',
                            \App\Models\AgreementOverview::STATUS_REDISCUSS         => 'AO Back to Discussion',

                            'pending_supervisor'   => 'Pending Supervisor',
                            'pending_gm'           => 'Pending GM',
                            'pending_legal_admin'  => 'Pending Admin Legal',
                            'pending_legal'        => 'Pending Legal',
                            'in_discussion'        => 'On Discussion Forum',
                            'agreement_creation'   => 'Ready for AO',
                            'completed'            => 'Agreement Successful',
                            'approved'             => 'Approved',
                            'rejected'             => 'Rejected',
                            default                => 'You haven\'t been involved yet',
                        };
                    }),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completed at')
                    ->sortable()
                    ->getStateUsing(function ($record) {
                        // Pakai completed_at kalau ada, kalau null pakai status fallback
                        return $record->completed_at ?? $record->status;
                    })
                    ->formatStateUsing(function ($state) {
                        if ($state instanceof \Carbon\Carbon) {
                            return $state->format('d M Y H:i');
                        }

                        return match ($state) {
                            'rejected' => 'Rejected',
                            default => 'Still on Progress',
                        };
                    }),
                /*
                Tables\Columns\IconColumn::make('has_agreement')
                    ->label('Agreement Created')
                    ->getStateUsing(fn($record) => $record->agreementOverview !== null)
                    ->boolean()
                    ->trueIcon('heroicon-o-document-duplicate')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('gray'),
                */
            ])
            ->filters([
                Filter::make('computed_status')
                    ->label('Status')
                    ->form([
                        Forms\Components\MultiSelect::make('statuses')
                            ->label('Select Status')
                            ->options([
                                'pending_supervisor'   => 'Pending Supervisor',
                                'pending_gm'           => 'Pending GM',
                                'pending_legal_admin'  => 'Pending Admin Legal',
                                'pending_legal'        => 'Pending Legal',
                                'in_discussion'        => 'On Discussion Forum',
                                'agreement_creation'   => 'Ready for AO',
                                'completed'            => 'Agreement Successful',
                                'approved'             => 'Approved',
                                'rejected'             => 'Rejected',

                                // AO stages
                                \App\Models\AgreementOverview::STATUS_DRAFT             => 'AO Draft',
                                \App\Models\AgreementOverview::STATUS_PENDING_HEAD      => 'AO - Pending Head',
                                \App\Models\AgreementOverview::STATUS_PENDING_GM        => 'AO - Pending GM',
                                \App\Models\AgreementOverview::STATUS_PENDING_FINANCE   => 'AO - Pending Finance',
                                \App\Models\AgreementOverview::STATUS_PENDING_LEGAL     => 'AO - Pending Legal',
                                \App\Models\AgreementOverview::STATUS_PENDING_DIRECTOR1 => 'AO - Pending Director 1',
                                \App\Models\AgreementOverview::STATUS_PENDING_DIRECTOR2 => 'AO - Pending Director 2',
                                \App\Models\AgreementOverview::STATUS_APPROVED          => 'AO Approved',
                                \App\Models\AgreementOverview::STATUS_REJECTED          => 'AO Rejected',
                                \App\Models\AgreementOverview::STATUS_REDISCUSS         => 'AO Back to Discussion',
                            ]),
                    ])
                    ->query(function ($query, array $data) {
                        if (empty($data['statuses'])) {
                            return;
                        }

                        $query->where(function ($subQuery) use ($data) {
                            $subQuery->whereHas('agreementOverview', function ($aoQuery) use ($data) {
                                $aoQuery->whereIn('status', $data['statuses']);
                            })->orWhere(function ($docReqQuery) use ($data) {
                                $docReqQuery->whereIn('status', $data['statuses']);
                            });
                        });
                    })
                    ->native(false),
                Filter::make('decision')
                    ->label('Your Decision')
                    ->form([
                        Forms\Components\Select::make('decisions')
                            ->label('Select Decision')
                            ->options([
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ]),
                    ])
                    ->query(function ($query, array $data) {
                        if (empty($data['decisions'])) {
                            return;
                        }

                        $userNik = auth()->user()->nik;

                        $query->whereHas('approvals', function ($approvalQuery) use ($data, $userNik) {
                            $approvalQuery->where('approver_nik', $userNik)
                                ->where('status', $data['decisions']);
                        });
                    })
                    ->native(false),
                SelectFilter::make('tipe_dokumen')
                    ->label('Jenis Perjanjian')
                    ->relationship('doctype', 'document_name')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                SelectFilter::make('dept')
                    ->label('Departemen')
                    ->options(function () {
                        return DocumentRequest::whereNotNull('dept')
                            ->distinct()
                            ->pluck('dept', 'dept')
                            ->filter()
                            ->toArray();
                    })
                    ->searchable()
                    ->multiple(),
                Filter::make('submitted_at')
                    ->form([
                        DatePicker::make('submitted_from')
                            ->label('Diunggah Sejak'),
                        DatePicker::make('submitted_until')
                            ->label('Diunggah Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['submitted_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('submitted_at', '>=', $date),
                            )
                            ->when(
                                $data['submitted_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('submitted_at', '<=', $date),
                            );
                    })
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('View'),
                Tables\Actions\Action::make('view_discussion')
                    ->label('View Discussion')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('primary')
                    ->visible(fn($record) => in_array($record->status, ['in_discussion', 'agreement_creation']))
                    ->url(fn ($record) => DiscussionResource::getUrl('view', ['record' => $record])),
                Tables\Actions\Action::make('download_documents')
                    ->label('Download LRF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn($record) => $record->status === 'completed')
                    ->action(function ($record) {
                        // Generate ZIP with all documents
                        return response()->download(storage_path('app/documents/' . $record->id . '_complete.zip'));
                    }),
                Tables\Actions\Action::make('view_agreement')
                    ->label('Download AO')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->visible(fn($record) => $record->agreementOverview !== null)
                    ->url(fn ($record) => \App\Filament\User\Resources\PendingAgreementOverviewResource::getUrl(
                        'view',
                        ['record' => $record->agreementOverview->getKey()]
                    )),
            ])
            ->actionsAlignment('start')
            ->bulkActions([])
            ->defaultSort('completed_at', 'desc')
            ->emptyStateHeading('No Document History')
            ->emptyStateDescription('Your approved and rejected documents will appear here.')
            ->emptyStateIcon('heroicon-o-clock');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Document Summary')
                    ->schema([
                        Infolists\Components\TextEntry::make('nomor_dokumen')
                            ->label('Nomor Dokumen')
                            ->weight(FontWeight::Bold)
                            ->copyable()
                            ->color('primary'),
                        Infolists\Components\TextEntry::make('title')
                            ->label('Nama Mitra')
                            ->weight(FontWeight::Medium), 
                        Infolists\Components\TextEntry::make('doctype.document_name')
                            ->label('Jenis Perjanjian')
                            ->badge()
                            ->color('info'),
                        Infolists\Components\TextEntry::make('computed_status')
                            ->badge()
                            ->label('Status')
                            ->colors([
                                'warning' => 'pending_supervisor',
                                'info'    => 'pending_gm',
                                'primary' => ['pending_legal', 'pending_legal_admin'],
                                'gray'    => 'submitted',

                                // AO stages
                                'purple'  => \App\Models\AgreementOverview::STATUS_PENDING_HEAD,
                                'success' => \App\Models\AgreementOverview::STATUS_APPROVED,
                                'danger'  => \App\Models\AgreementOverview::STATUS_REJECTED,
                            ])
                            ->formatStateUsing(function ($state, $record) {
                                // sekarang $state sudah computed_status (bisa AO / docreq)
                                return match ($state) {
                                    \App\Models\AgreementOverview::STATUS_DRAFT             => 'AO Draft',
                                    \App\Models\AgreementOverview::STATUS_PENDING_HEAD      => 'AO - Pending Head',
                                    \App\Models\AgreementOverview::STATUS_PENDING_GM        => 'AO - Pending GM',
                                    \App\Models\AgreementOverview::STATUS_PENDING_FINANCE   => 'AO - Pending Finance',
                                    \App\Models\AgreementOverview::STATUS_PENDING_LEGAL     => 'AO - Pending Legal',
                                    \App\Models\AgreementOverview::STATUS_PENDING_DIRECTOR1 => 'AO - Pending Director 1',
                                    \App\Models\AgreementOverview::STATUS_PENDING_DIRECTOR2 => 'AO - Pending Director 2',
                                    \App\Models\AgreementOverview::STATUS_APPROVED          => 'AO Approved',
                                    \App\Models\AgreementOverview::STATUS_REJECTED          => 'AO Rejected',
                                    \App\Models\AgreementOverview::STATUS_REDISCUSS         => 'AO Back to Discussion',

                                    'pending_supervisor'   => 'Pending Supervisor',
                                    'pending_gm'           => 'Pending GM',
                                    'pending_legal_admin'  => 'Pending Admin Legal',
                                    'pending_legal'        => 'Pending Legal',
                                    'in_discussion'        => 'On Discussion Forum',
                                    'agreement_creation'   => 'Ready for AO',
                                    'completed'            => 'Agreement Successful',
                                    'approved'             => 'Approved',
                                    'rejected'             => 'Rejected',
                                    default                => 'You haven\'t been involved yet',
                                };
                            }),
                        /*
                        Infolists\Components\TextEntry::make('priority')
                            ->badge(),
                        */
                    ])->columns(2),

                Infolists\Components\Section::make('Requester Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('nama')
                                    ->label('PIC')
                                    ->weight(FontWeight::Medium)
                                    ->color('primary'),
                                Infolists\Components\TextEntry::make('nik')
                                    ->label('NIK')
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('jabatan')
                                    ->label('Position'),
                                Infolists\Components\TextEntry::make('divisi')
                                    ->label('Division'),
                                Infolists\Components\TextEntry::make('dept')
                                    ->label('Department'),
                                Infolists\Components\TextEntry::make('direktorat')
                                    ->label('Directorate'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Informasi Dokumen')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('lama_perjanjian_surat')
                                    ->label('⏰ Jangka Waktu Perjanjian')
                                    ->placeholder('Not specified'),
                                Infolists\Components\TextEntry::make('doc_filter')
                                    ->label('📑 Document')
                                    ->formatStateUsing(fn($state) => match($state) {
                                        'review' => '🔍 Review',
                                        'create' => '✨ Create New',
                                        default => $state ?: 'Not specified'
                                    })
                                    ->badge(),
                            ]),
                        /*
                        Infolists\Components\TextEntry::make('description')
                            ->label('📝 Deskripsi Dokumen')
                            ->html()
                            ->columnSpanFull()
                            ->placeholder('Tidak ada deskripsi pada Document Request ini.'),
                        Infolists\Components\TextEntry::make('data')
                            ->label('Business Justification')
                            ->html()
                            ->columnSpanFull(),
                        */
                    ]),

                // HAK & KEWAJIBAN - SELALU TAMPIL
                Infolists\Components\Section::make('⚖️ Hak & Kewajiban')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('kewajiban_mitra')
                                    ->label('📝 Kewajiban Mitra')
                                    ->html()
                                    ->placeholder('Not specified'),
                                Infolists\Components\TextEntry::make('kewajiban_eci')
                                    ->label('📝 Kewajiban ECI')
                                    ->html()
                                    ->placeholder('Not specified'),
                                Infolists\Components\TextEntry::make('hak_mitra')
                                    ->label('✅ Hak Mitra')
                                    ->html()
                                    ->placeholder('Not specified'),
                                Infolists\Components\TextEntry::make('hak_eci')
                                    ->label('✅ Hak ECI')
                                    ->html()
                                    ->placeholder('Not specified'),
                            ]),
                    ])
                    ->collapsible(),,

                // CONTRACT TERMS - SELALU TAMPIL
                Infolists\Components\Section::make('📋 Regulasi Finansial')
                    ->schema([
                        Infolists\Components\TextEntry::make('syarat_ketentuan_pembayaran')
                            ->label('💰 Syarat & Ketentuan Pembayaran')
                            ->columnSpanFull()
                            ->html()
                            ->placeholder('Not specified'),
                        Infolists\Components\TextEntry::make('pajak')
                            ->label('📊 Ketentuan Pajak')
                            ->columnSpanFull()
                            ->html()
                            ->placeholder('Not specified'),
                    ])
                    ->collapsible(),,

                // ADDITIONAL TERMS - SELALU TAMPIL
                Infolists\Components\Section::make('📄 Ketentuan Tambahan')
                    ->schema([
                        Infolists\Components\TextEntry::make('ketentuan_lain')
                            ->label('📋 Ketentuan Lainnya')
                            ->columnSpanFull()
                            ->html()
                            ->formatStateUsing(fn($state) => $state ?: '<span class="text-gray-500">Tidak ada ketentuan tambahan.</span>')
                    ])
                    ->collapsible(),

                // ATTACHMENTS - SELALU TAMPIL tanpa visible condition
                Infolists\Components\Section::make('📎 Lampiran Dokumen')
                    ->schema([                               
                        Infolists\Components\TextEntry::make('dokumen_utama')
                            ->label('📄 Main Document')
                            ->formatStateUsing(function($state) {
                                if (!$state) return '❌ Not uploaded';
                                $filename = basename($state);
                                $extension = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                                return "📁 {$filename} ({$extension})";
                            })
                            ->url(fn ($record) => $record->dokumen_utama ? asset('storage/' . $record->dokumen_utama) : null)
                            ->openUrlInNewTab()
                            ->color(fn($state) => $state ? 'success' : 'danger')
                            ->tooltip(fn($state) => $state ? basename($state) : 'No file'),
                        Infolists\Components\Grid::make(2)
                            ->schema([                                
                                Infolists\Components\TextEntry::make('akta_pendirian')
                                    ->label('🏢 Akta Pendirian + SK')
                                    ->formatStateUsing(function($state) {
                                        if (!$state) return '➖ Not provided';
                                        $filename = basename($state);
                                        $extension = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                                        return "📁 {$filename} ({$extension})";
                                    })
                                    ->url(fn ($record) => $record->akta_pendirian ? asset('storage/' . $record->akta_pendirian) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'gray')
                                    ->limit(30) // batasi jadi 30 karakter, sisanya diganti ...
                                    ->tooltip(fn ($record) => $record->akta_pendirian), // full text muncul di hover

                                Infolists\Components\TextEntry::make('akta_perubahan')
                                    ->label('📋 Akta PT & SK Anggaran Dasar perubahan terakhir')
                                    ->formatStateUsing(function($state) {
                                        if (!$state) return '➖ Not provided';
                                        $filename = basename($state);
                                        $extension = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                                        return "📁 {$filename} ({$extension})";
                                    })
                                    ->url(fn ($record) => $record->akta_perubahan ? asset('storage/' . $record->akta_perubahan) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'gray')
                                    ->limit(30) // batasi jadi 30 karakter, sisanya diganti ...
                                    ->tooltip(fn ($record) => $record->akta_perubahan), // full text muncul di hover

                                Infolists\Components\TextEntry::make('npwp')
                                    ->label('📋 NPWP (Nomor Pokok Wajib Pajak)')
                                    ->formatStateUsing(function($state) {
                                        if (!$state) return '➖ Not provided';
                                        $filename = basename($state);
                                        $extension = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                                        return "📁 {$filename} ({$extension})";
                                    })
                                    ->url(fn ($record) => $record->npwp ? asset('storage/' . $record->npwp) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'gray')
                                    ->limit(30) // batasi jadi 30 karakter, sisanya diganti ...
                                    ->tooltip(fn ($record) => $record->npwp), // full text muncul di hover
                                
                                Infolists\Components\TextEntry::make('ktp_direktur')
                                    ->label('🆔 KTP kuasa Direksi (bila penandatangan bukan Direksi)')
                                    ->formatStateUsing(function($state) {
                                        if (!$state) return '➖ Not provided';
                                        $filename = basename($state);
                                        $extension = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                                        return "📁 {$filename} ({$extension})";
                                    })
                                    ->url(fn ($record) => $record->ktp_direktur ? asset('storage/' . $record->ktp_direktur) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'gray')
                                    ->limit(30) // batasi jadi 30 karakter, sisanya diganti ...
                                    ->tooltip(fn ($record) => $record->ktp_direktur), // full text muncul di hover

                                Infolists\Components\TextEntry::make('nib')
                                    ->label('🏪 NIB (Nomor Induk Berusaha)')
                                    ->formatStateUsing(function($state) {
                                        if (!$state) return '➖ Not provided';
                                        $filename = basename($state);
                                        $extension = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                                        return "📁 {$filename} ({$extension})";
                                    })
                                    ->url(fn ($record) => $record->nib ? asset('storage/' . $record->nib) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'gray')
                                    ->limit(30) // batasi jadi 30 karakter, sisanya diganti ...
                                    ->tooltip(fn ($record) => $record->nib), // full text muncul di hover
                                
                                Infolists\Components\TextEntry::make('surat_kuasa')
                                    ->label('✍️ Surat kuasa Direksi (bila penandatangan bukan Direksi)')
                                    ->formatStateUsing(function($state) {
                                        if (!$state) return '➖ Not provided';
                                        $filename = basename($state);
                                        $extension = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                                        return "📁 {$filename} ({$extension})";
                                    })
                                    ->url(fn ($record) => $record->surat_kuasa ? asset('storage/' . $record->surat_kuasa) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'gray')
                                    ->limit(30) // batasi jadi 30 karakter, sisanya diganti ...
                                    ->tooltip(fn ($record) => $record->surat_kuasa), // full text muncul di hover
                            ]),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('⏱️ Timeline & Progress')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Dibuat')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('submitted_at')
                            ->label('DIunggah')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('completed_at')
                            ->label('Selesai')
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) {
                                    return 'Dalam proses'; // placeholder manual
                                }

                                return \Carbon\Carbon::parse($state)->format('d M Y H:i');
                            }),
                        Infolists\Components\TextEntry::make('total_processing_time')
                            ->label('Jumlah Waktu Proses')
                            ->getStateUsing(function ($record) {
                                if ($record->submitted_at && $record->completed_at) {
                                    return $record->submitted_at->diffForHumans($record->completed_at, true);
                                }
                                return 'Dalam proses';
                            }),
                    ])->columns(4),

                Infolists\Components\Section::make('Approval History')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('approvals')
                            ->schema([
                                Infolists\Components\TextEntry::make('approver_name')
                                    ->label('Approver'),
                                Infolists\Components\TextEntry::make('approval_type')
                                    ->label('Role')
                                    ->getStateUsing(fn($record) => $record->getApprovalTypeLabel()),
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn($record) => $record->getStatusBadgeColor()),
                                Infolists\Components\TextEntry::make('approved_at')
                                    ->label('Date')
                                    ->dateTime()
                                    ->formatStateUsing(fn ($state) => $state ? $state->diffForHumans() : 'Pending'),,
                                Infolists\Components\TextEntry::make('comments')
                                    ->label('Comments')
                                    ->placeholder('No comments')
                                    ->formatStateUsing(fn ($state) => $state ?: 'No comments'),
                            ])
                            ->columns(5),
                    ]),

                // INI GANTI KE VIEW AO PDF
                Infolists\Components\Section::make('Related Agreement Overview')
                    ->schema([
                        /*
                        Infolists\Components\TextEntry::make('agreementOverview.nomor_dokumen')
                            ->label('AO Number')
                            ->placeholder('No agreement created'),
                        */
                        Infolists\Components\TextEntry::make('agreementOverview.status')
                            ->label('Agreement Status')
                            ->badge()
                            ->placeholder('N/A'),
                        Infolists\Components\TextEntry::make('agreementOverview.tanggal_ao')
                            ->label('Agreement Date')
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) {
                                    return 'No data'; // placeholder manual
                                }

                                return \Carbon\Carbon::parse($state)->format('d M Y H:i');
                            }),
                    ])->columns(2)
                    ->visible(fn($record) => $record->agreementOverview !== null),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyHistory::route('/'),
            'view' => Pages\ViewMyHistory::route('/{record}'),
        ];
    }
}