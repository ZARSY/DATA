<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoanResource\Pages;
use App\Filament\Resources\LoanResource\RelationManagers\PaymentsRelationManager;
use App\Models\Loan;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Get;
use Filament\Forms\Set; // Untuk set value field lain
use Illuminate\Support\Carbon;

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
        $record = $form->getRecord(); // Loan record saat edit

        return $form
            ->schema([
                Forms\Components\Section::make('Pengajuan Pinjaman')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Anggota Peminjam')
                            ->options(User::whereHas('roles', fn (Builder $query) => $query->where('name', 'Anggota'))->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->default(fn(): ?int => ($isCreating && $loggedInUser->hasRole('Anggota')) ? $loggedInUser->id : null)
                            ->disabled(fn(): bool =>
                                ($isCreating && $loggedInUser->hasRole('Anggota')) ||
                                ($isEditing && ($record?->status !== 'diajukan' || $loggedInUser->hasRole('Anggota')) && !$loggedInUser->can('update_loans'))
                            )
                            // Hanya bisa dipilih saat create oleh Admin/Teller, atau saat Anggota create
                            ->visible(fn(): bool => $isCreating || ($isEditing && $loggedInUser->can('update_loans'))),
                        Forms\Components\TextInput::make('jumlah_pinjaman')
                            ->required()->numeric()->prefix('Rp')->minValue(1)
                            ->currencyMask(thousandSeparator: '.', decimalSeparator: ',', precision: 2)
                            ->disabled($isEditing && $record?->status !== 'diajukan' && !$loggedInUser->can('update_loans')),
                        Forms\Components\TextInput::make('jangka_waktu_bulan')
                            ->label('Jangka Waktu (Bulan)')->required()->numeric()->suffix('Bulan')->minValue(1)
                            ->disabled($isEditing && $record?->status !== 'diajukan' && !$loggedInUser->can('update_loans')),
                        Forms\Components\DatePicker::make('tanggal_pengajuan')
                            ->required()->default(now())
                            ->disabled($isEditing && $record?->status !== 'diajukan' && !$loggedInUser->can('update_loans')),
                        Forms\Components\Textarea::make('keterangan_pengajuan')
                            ->label('Keterangan/Tujuan Pinjaman')->columnSpanFull()
                            ->visible($isCreating || ($isEditing && $record?->status === 'diajukan')),
                    ])->columns(2),

                Forms\Components\Section::make('Status & Persetujuan Pinjaman')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'diajukan' => 'Diajukan',
                                'disetujui' => 'Disetujui',
                                'ditolak' => 'Ditolak',
                                'berjalan' => 'Berjalan', // Setelah dana cair
                                'lunas' => 'Lunas',
                            ])
                            ->required()
                            ->default($isCreating ? 'diajukan' : null)
                            ->disabled(fn():bool =>
                                $isCreating || // Status selalu 'diajukan' saat create
                                ($isEditing && !$loggedInUser->can('approve_loans') && !$loggedInUser->can('update_loans')) || // Hanya yang berhak bisa ubah status
                                ($isEditing && in_array($record?->status, ['lunas', 'ditolak']) && !$loggedInUser->can('update_loans')) // Jika sudah lunas/ditolak, hanya admin bisa ubah
                            )
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state, ?Loan $loanRecord) use ($loggedInUser) {
                                // Jika status diubah menjadi 'disetujui' dan tanggal persetujuan belum ada
                                if ($state === 'disetujui' && empty($loanRecord?->tanggal_persetujuan)) {
                                    $set('tanggal_persetujuan', now()->format('Y-m-d'));
                                    if (empty($loanRecord?->approved_by)) {
                                        $set('approved_by', $loggedInUser->id);
                                    }
                                } elseif ($state === 'ditolak' && empty($loanRecord?->tanggal_persetujuan)) {
                                    $set('tanggal_persetujuan', now()->format('Y-m-d')); // Tanggal proses penolakan
                                     if (empty($loanRecord?->approved_by)) {
                                        $set('approved_by', $loggedInUser->id);
                                    }
                                }
                            }),
                        Forms\Components\DatePicker::make('tanggal_persetujuan')
                            ->visible(fn (Get $get): bool => in_array($get('status'), ['disetujui', 'ditolak', 'berjalan', 'lunas']))
                            ->requiredWith('status,disetujui')
                            ->disabled($isEditing && !$loggedInUser->can('approve_loans') && !$loggedInUser->can('update_loans')),
                        Forms\Components\TextInput::make('bunga_persen_per_bulan')
                            ->label('Bunga (% per Bulan)')->numeric()->suffix('%')->minValue(0)->maxValue(100)
                            ->visible(fn (Get $get): bool => in_array($get('status'), ['disetujui', 'berjalan', 'lunas']))
                            ->requiredWith('status,disetujui')
                            ->disabled($isEditing && !$loggedInUser->can('approve_loans') && !$loggedInUser->can('update_loans')),
                        Forms\Components\Hidden::make('approved_by'), // Diisi otomatis atau oleh aksi
                        Forms\Components\Textarea::make('keterangan_approval')
                            ->label('Keterangan Persetujuan/Penolakan')->columnSpanFull()
                            ->visible(fn (Get $get): bool => in_array($get('status'), ['disetujui', 'ditolak', 'berjalan', 'lunas'])),
                    ])
                    ->visible($isEditing) // Section persetujuan hanya muncul saat edit
                    ->columns(2)->collapsible(),
            ]);
    }

    public static function table(Table $table): Table { /* ... (Kode tabel dari respons sebelumnya sudah cukup baik, pastikan visible action disesuaikan) ... */
        // PASTIKAN ACTION GROUP UNTUK APPROVE/REJECT LOAN MEMILIKI LOGIKA VISIBILITAS YANG BENAR
        // SEPERTI CONTOH DI RESPONS SEBELUMNYA (BERDASARKAN STATUS 'diajukan' DAN PERMISSION 'approve_loans')
        return $table // Salin dari respons sebelumnya dan sesuaikan jika perlu
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID Pinj.')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('Peminjam')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('jumlah_pinjaman')->money('IDR', true)->sortable(),
                Tables\Columns\TextColumn::make('jangka_waktu_bulan')->label('Waktu (Bln)')->suffix(' bln')->sortable(),
                Tables\Columns\TextColumn::make('tanggal_pengajuan')->date()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'diajukan' => 'warning',
                        'disetujui' => 'success',
                        'ditolak' => 'danger',
                        'berjalan' => 'info',
                        'lunas' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('approver.name')->label('Disetujui Oleh')->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('tanggal_persetujuan')->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(['diajukan' => 'Diajukan', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak', 'berjalan' => 'Berjalan', 'lunas' => 'Lunas']),
                 SelectFilter::make('user_id')->label('Anggota Peminjam')
                    ->options(User::whereHas('roles', fn (Builder $query) => $query->where('name', 'Anggota'))->pluck('name', 'id'))
                    ->searchable()->visible(fn(): bool => auth()->user()->can('view_any_loans')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('approve_loan')
                        ->label('Setujui')->icon('heroicon-o-check-circle')->color('success')
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\DatePicker::make('tanggal_persetujuan_action')->label('Tanggal Persetujuan')->default(now())->required(),
                            Forms\Components\TextInput::make('bunga_persen_per_bulan_action')->label('Bunga (% per Bulan)')->numeric()->minValue(0)->maxValue(100)->suffix('%')->required(),
                            Forms\Components\Textarea::make('keterangan_approval_action')->label('Keterangan Persetujuan (Opsional)'),
                        ])
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
                        ->form([Forms\Components\Textarea::make('keterangan_approval_action')->label('Alasan Penolakan')->required()])
                        ->action(function (Loan $record, array $data) {
                            $record->status = 'ditolak';
                            $record->keterangan_approval = $data['keterangan_approval_action'];
                            $record->approved_by = auth()->id();
                            $record->tanggal_persetujuan = now();
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