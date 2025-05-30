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
                            // Jika Anggota yang membuat, otomatis isi dengan ID-nya dan disable fieldnya.
                            // Jika Admin/Teller yang membuat, field ini bisa dipilih.
                            ->default(function (string $operation) use ($loggedInUser): ?int {
                                if ($operation === 'create' && $loggedInUser && $loggedInUser->hasRole('Anggota')) {
                                    return $loggedInUser->id;
                                }
                                return null;
                            })
                            ->disabled(function (string $operation) use ($loggedInUser, $record, $isEditing): bool {
                                if ($operation === 'create' && $loggedInUser && $loggedInUser->hasRole('Anggota')) {
                                    return true; // Anggota tidak bisa ganti dirinya saat create
                                }
                                // Logika disable saat edit
                                if ($isEditing) {
                                    // Admin bisa edit user_id jika status masih diajukan (misal salah input)
                                    if ($loggedInUser && $loggedInUser->can('update_loans') && $record?->status === 'diajukan') {
                                        return false;
                                    }
                                    return true; // Selain itu, user_id di-disable saat edit
                                }
                                return false; // Tidak di-disable untuk Admin/Teller saat create
                            })
                            ->visible(function (string $operation) use ($loggedInUser): bool {
                                // Selalu visible saat create agar Anggota melihat namanya (meski disabled)
                                // atau jika Admin/Teller yang buat dan perlu memilih
                                if ($operation === 'create') {
                                    return true;
                                }
                                // Saat edit, hanya Admin yang bisa lihat/ubah user_id (jika diperlukan dan diizinkan)
                                return $operation === 'edit' && $loggedInUser && $loggedInUser->can('update_loans');
                            }),

                        Forms\Components\TextInput::make('jumlah_pinjaman')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(1)
                            // Saat edit, hanya bisa diubah jika status 'diajukan' ATAU oleh user dengan izin 'update_loans' (Admin)
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
                            // Tidak perlu visible/disabled khusus, akan selalu ada di section ini
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
                            ->default('diajukan') // Selalu 'diajukan' saat create
                            ->disabled(true) // Anggota tidak bisa mengubah status saat mengajukan. Admin/MK baru bisa saat edit.
                            ->visible($isEditing && $loggedInUser->hasAnyRole(['Admin', 'Manajer Keuangan'])), // Hanya visible saat edit oleh role tertentu

                        Forms\Components\DatePicker::make('tanggal_persetujuan')
                            ->required(fn (Get $get): bool => $get('status') === 'disetujui')
                            ->disabled(fn (): bool => !$loggedInUser->hasAnyRole(['Admin', 'Manajer Keuangan']) || (!$loggedInUser->can('approve_loans') && !$loggedInUser->can('update_loans')))
                            ->visible(fn (Get $get): bool => $isEditing && in_array($get('status'), ['disetujui', 'ditolak', 'berjalan', 'lunas'])),

                        Forms\Components\TextInput::make('bunga_persen_per_bulan')
                            ->label('Bunga (% per Bulan)')
                            ->numeric()->suffix('%')->minValue(0)->maxValue(100)
                            ->required(fn (Get $get): bool => $get('status') === 'disetujui')
                            ->disabled(fn (): bool => !$loggedInUser->hasAnyRole(['Admin', 'Manajer Keuangan']) || (!$loggedInUser->can('approve_loans') && !$loggedInUser->can('update_loans')))
                            ->visible(fn (Get $get): bool => $isEditing && in_array($get('status'), ['disetujui', 'berjalan', 'lunas'])),

                        Forms\Components\Hidden::make('approved_by'), // Akan diisi oleh Aksi Setujui/Tolak di tabel atau afterStateUpdated

                        Forms\Components\Textarea::make('keterangan_approval')
                            ->label('Keterangan Persetujuan/Penolakan')
                            ->columnSpanFull()
                            ->disabled(fn (): bool => !$loggedInUser->hasAnyRole(['Admin', 'Manajer Keuangan']) || (!$loggedInUser->can('approve_loans') && !$loggedInUser->can('update_loans')))
                            ->visible(fn (Get $get): bool => $isEditing && in_array($get('status'), ['disetujui', 'ditolak', 'berjalan', 'lunas'])),
                    ])
                    // Section ini hanya muncul saat EDIT dan oleh Admin atau Manajer Keuangan
                    ->visible($isEditing && $loggedInUser->hasAnyRole(['Admin', 'Manajer Keuangan']))
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table {
        // Salin kode tabel dari respons sebelumnya, sepertinya sudah cukup baik.
        // Fokus utama adalah memastikan form tidak error.
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID Pinj.')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('Peminjam')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('jumlah_pinjaman')->money('IDR', true)->sortable(),
                Tables\Columns\TextColumn::make('jangka_waktu_bulan')->label('Waktu (Bln)')->suffix(' bln')->sortable(),
                Tables\Columns\TextColumn::make('bunga_persen_per_bulan')->label('Bunga (%)')->suffix('%')->sortable()->toggleable(isToggledHiddenByDefault: true), // Ditambahkan dari respons sebelumnya
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