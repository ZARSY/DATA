<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoanResource\Pages;
use App\Filament\Resources\LoanResource\RelationManagers\PaymentsRelationManager;
use App\Models\Loan;
use App\Models\User; // Pastikan User di-import
use Filament\Forms;
use Filament\Forms\Form; // Pastikan Form di-import
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table; // Pastikan Table di-import
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Get; // Pastikan Get di-import
use Filament\Forms\Set; // Pastikan Set di-import
// Carbon tidak perlu di-import di sini jika hanya digunakan di Model

class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Transaksi Keuangan';
    protected static ?string $navigationLabel = 'Data Pinjaman';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        $loggedInUser = auth()->user();
        $isCreating = $form->getOperation() === 'create';
        $isEditing = $form->getOperation() === 'edit';
        /** @var ?Loan $record */
        $record = $form->getRecord();

        return $form
            ->schema([
                Forms\Components\Section::make('Pengajuan Pinjaman')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Anggota Peminjam')
                            ->options(User::whereHas('roles', fn (Builder $query) => $query->where('name', 'Anggota'))->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->default(function (string $operation) use ($loggedInUser): ?int {
                                if ($operation === 'create' && $loggedInUser && $loggedInUser->hasRole('Anggota')) {
                                    return $loggedInUser->id;
                                }
                                return null;
                            })
                            ->disabled(function (string $operation) use ($loggedInUser, $record, $isEditing): bool {
                                if ($operation === 'create' && $loggedInUser && $loggedInUser->hasRole('Anggota')) {
                                    return true;
                                }
                                if ($isEditing) {
                                    if ($loggedInUser && $loggedInUser->can('update_loans') && $record?->status === 'diajukan') {
                                        return false;
                                    }
                                    return true;
                                }
                                return false;
                            })
                            ->visible(function (string $operation) use ($loggedInUser): bool {
                                if ($operation === 'create') {
                                    return true;
                                }
                                return $operation === 'edit' && $loggedInUser && $loggedInUser->can('update_loans');
                            }),

                        Forms\Components\TextInput::make('jumlah_pinjaman')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(1)
                            ->disabled($isEditing && $record?->status !== 'diajukan' && !$loggedInUser->can('update_loans')),

                        Forms\Components\TextInput::make('jangka_waktu_bulan')
                            ->label('Jangka Waktu (Bulan)')
                            ->required()
                            ->numeric()
                            ->suffix('Bulan')
                            ->minValue(1)
                            ->disabled($isEditing && $record?->status !== 'diajukan' && !$loggedInUser->can('update_loans')),

                        Forms\Components\DatePicker::make('tanggal_pengajuan')
                            ->required()
                            ->default(now())
                            ->disabled($isEditing && $record?->status !== 'diajukan' && !$loggedInUser->can('update_loans')),

                        Forms\Components\Textarea::make('keterangan_pengajuan')
                            ->label('Keterangan/Tujuan Pinjaman')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Status & Persetujuan Pinjaman')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'diajukan' => 'Diajukan',
                                'disetujui' => 'Disetujui',
                                'ditolak' => 'Ditolak',
                                'berjalan' => 'Berjalan',
                                'lunas' => 'Lunas',
                            ])
                            ->required()
                            ->default('diajukan')
                            ->disabled(fn():bool => $isCreating || ($isEditing && !$loggedInUser->hasAnyRole(['Admin', 'Manajer Keuangan'])))
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state, ?Loan $loanRecord) use ($loggedInUser, $isCreating) {
                                if ($isCreating) return;

                                if ($state === 'disetujui' && ($loggedInUser->hasRole('Manajer Keuangan') || $loggedInUser->hasRole('Admin'))) {
                                    if (empty($loanRecord?->tanggal_persetujuan)) {
                                        $set('tanggal_persetujuan', now()->format('Y-m-d'));
                                    }
                                    if (empty($loanRecord?->approved_by)) {
                                        $set('approved_by', $loggedInUser->id);
                                    }
                                    if (is_null($loanRecord?->bunga_persen_per_bulan)) {
                                         $set('bunga_persen_per_bulan', $loanRecord?->bunga_persen_per_bulan ?? 0.00);
                                    }
                                } elseif ($state === 'ditolak' && ($loggedInUser->hasRole('Manajer Keuangan') || $loggedInUser->hasRole('Admin'))) {
                                    if (empty($loanRecord?->tanggal_persetujuan)) {
                                        $set('tanggal_persetujuan', now()->format('Y-m-d'));
                                    }
                                    if (empty($loanRecord?->approved_by)) {
                                        $set('approved_by', $loggedInUser->id);
                                    }
                                }
                            }),

                        Forms\Components\DatePicker::make('tanggal_persetujuan')
                            ->required(fn (Get $get): bool => $get('status') === 'disetujui')
                            ->disabled(fn (): bool => !$loggedInUser->hasAnyRole(['Admin', 'Manajer Keuangan']) || (!$loggedInUser->can('approve_loans') && !$loggedInUser->can('update_loans'))),

                        Forms\Components\TextInput::make('bunga_persen_per_bulan')
                            ->label('Bunga (% per Bulan)')
                            ->numeric()->suffix('%')->minValue(0)->maxValue(100)
                            ->required(fn (Get $get): bool => $get('status') === 'disetujui')
                            ->disabled(fn (): bool => !$loggedInUser->hasAnyRole(['Admin', 'Manajer Keuangan']) || (!$loggedInUser->can('approve_loans') && !$loggedInUser->can('update_loans'))),

                        Forms\Components\Hidden::make('approved_by'),

                        Forms\Components\Textarea::make('keterangan_approval')
                            ->label('Keterangan Persetujuan/Penolakan')
                            ->columnSpanFull()
                            ->disabled(fn (): bool => !$loggedInUser->hasAnyRole(['Admin', 'Manajer Keuangan']) || (!$loggedInUser->can('approve_loans') && !$loggedInUser->can('update_loans'))),
                    ])
                    ->visible($isEditing && $loggedInUser->hasAnyRole(['Admin', 'Manajer Keuangan']))
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_display_name')
                    ->label('Peminjam')
                    ->getStateUsing(fn (Loan $record): ?string => $record->user?->name ?? 'User Telah Dihapus')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('user', function (Builder $q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $userTableName = (new User())->getTable();
                        $loanTableName = (new Loan())->getTable();
                        return $query->orderBy(
                            User::select("{$userTableName}.name")
                                ->whereColumn("{$userTableName}.id", "{$loanTableName}.user_id"),
                            $direction
                        );
                    }),

                Tables\Columns\TextColumn::make('jumlah_pinjaman')
                    ->label('Jml. Pokok Pinjaman')
                    ->money('IDR', true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_estimasi_tagihan')
                    ->label('Total Est. Tagihan')
                    ->money('IDR', true)
                    ->getStateUsing(fn (Loan $record): float => $record->total_estimasi_tagihan)
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->sortable(false),

                Tables\Columns\TextColumn::make('sisa_estimasi_tagihan')
                    ->label('Sisa Tagihan (Est.)')
                    ->money('IDR', true)
                    ->getStateUsing(fn (Loan $record): float => $record->sisa_estimasi_tagihan),

                Tables\Columns\TextColumn::make('bunga_persen_per_bulan')
                    ->label('Bunga (%/bln)')
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tanggal_akhir_pinjaman_display')
                    ->label('Jatuh Tempo Akhir')
                    ->date()
                    ->getStateUsing(fn (Loan $record): ?string => $record->tanggal_akhir_pinjaman),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'diajukan' => 'warning',
                        'disetujui' => 'success',
                        'ditolak' => 'danger',
                        'berjalan' => 'info',
                        'lunas' => 'primary',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('id')->label('ID Pinj.')->sortable()->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('jangka_waktu_bulan')->label('Waktu (Bln)')->suffix(' bln')->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('tanggal_pengajuan')->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('approver_display_name')
                    ->label('Disetujui Oleh')
                    ->getStateUsing(fn (Loan $record): ?string => $record->approver?->name)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('approver', function (Builder $q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $userTableName = (new User())->getTable();
                        $loanTableName = (new Loan())->getTable();
                        return $query->orderBy(
                            User::select("{$userTableName}.name")
                                ->whereColumn("{$userTableName}.id", "{$loanTableName}.approved_by"),
                            $direction
                        );
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('tanggal_persetujuan')->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(['diajukan' => 'Diajukan', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak', 'berjalan' => 'Berjalan', 'lunas' => 'Lunas']),
                SelectFilter::make('user_id')->label('Anggota Peminjam')
                    ->relationship('user', 'name')
                    ->searchable()->preload()
                    ->visible(fn(): bool => auth()->user()->can('view_any_loans')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('approve_loan')
                        ->label('Setujui')
                        ->icon('heroicon-o-check-circle')->color('success')
                        ->requiresConfirmation()
                        // Menggunakan closure untuk form agar bisa akses $record
                        ->form(function (Loan $record) {
                            return [
                                Forms\Components\Placeholder::make('info_peminjam')
                                    ->label('Peminjam')
                                    ->content($record->user?->name ?? 'N/A'),
                                Forms\Components\Placeholder::make('info_jumlah_pinjaman')
                                    ->label('Jumlah Pokok Pinjaman')
                                    ->content('Rp ' . number_format($record->jumlah_pinjaman, 0, ',', '.')),
                                Forms\Components\Placeholder::make('info_jangka_waktu')
                                    ->label('Jangka Waktu')
                                    ->content($record->jangka_waktu_bulan . ' Bulan'),
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\DatePicker::make('tanggal_persetujuan_action')
                                            ->label('Tanggal Persetujuan')
                                            ->default(now())
                                            ->required(),
                                        Forms\Components\TextInput::make('bunga_persen_per_bulan_action')
                                            ->label('Bunga (% per Bulan)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->suffix('%')
                                            ->required()
                                            // Anda bisa set default bunga dari setting global di sini jika ada
                                            ->default($record->bunga_persen_per_bulan ?? 0.00),
                                    ]),
                                Forms\Components\Textarea::make('keterangan_approval_action')
                                    ->label('Keterangan Persetujuan (Opsional)')
                                    ->columnSpanFull(),
                            ];
                        })
                        ->action(function (Loan $record, array $data) {
                            $record->status = 'disetujui';
                            $record->tanggal_persetujuan = $data['tanggal_persetujuan_action'];
                            $record->bunga_persen_per_bulan = $data['bunga_persen_per_bulan_action'];
                            $record->keterangan_approval = $data['keterangan_approval_action'];
                            $record->approved_by = auth()->id();
                            $record->save();
                        })->visible(fn (Loan $record): bool => $record->status === 'diajukan' && auth()->user()->can('approve_loans')),

                    Tables\Actions\Action::make('reject_loan')
                        ->label('Tolak')->icon('heroicon-o-x-circle')->color('danger')
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('keterangan_approval_action')
                                ->label('Alasan Penolakan')
                                ->required(),
                        ])
                        ->action(function (Loan $record, array $data) {
                            $record->status = 'ditolak';
                            $record->keterangan_approval = $data['keterangan_approval_action'];
                            $record->approved_by = auth()->id();
                            $record->tanggal_persetujuan = now(); // Tanggal proses penolakan
                            $record->save();
                        })->visible(fn (Loan $record): bool => $record->status === 'diajukan' && auth()->user()->can('approve_loans')),
                ])->icon('heroicon-m-ellipsis-vertical')->visible(fn (Loan $record): bool => $record->status === 'diajukan' && auth()->user()->can('approve_loans')),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    { return [PaymentsRelationManager::class]; }

    public static function getPages(): array {
        return [
            'index' => Pages\ListLoans::route('/'),
            'create' => Pages\CreateLoan::route('/create'),
            'edit' => Pages\EditLoan::route('/{record}/edit'),
            'view' => Pages\ViewLoan::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder {
        $user = auth()->user();
        if ($user->hasRole('Anggota') && $user->can('view_own_loans')) {
            return parent::getEloquentQuery()->where('user_id', $user->id);
        }
        if (!$user->can('view_any_loans') && !$user->hasRole('Anggota')) {
            return parent::getEloquentQuery()->whereNull('id');
       }
        return parent::getEloquentQuery();
    }
}