<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SavingResource\Pages;
use App\Models\Saving;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Get;
use Filament\Forms\Components\FileUpload;

class SavingResource extends Resource
{
    protected static ?string $model = Saving::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Transaksi Keuangan';
    protected static ?string $navigationLabel = 'Data Simpanan';
    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user->can('view_any_savings') || $user->can('view_own_savings');
    }

    public static function form(Form $form): Form
    {
        $loggedInUser = auth()->user();
        $isCreating = $form->getOperation() === 'create';
        $isEditing = $form->getOperation() === 'edit';
        /** @var ?Saving $record */
        $record = $form->getRecord();

        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Anggota')
                    ->options(
                        User::whereHas('roles', fn (Builder $query) => $query->where('name', 'Anggota'))->pluck('name', 'id')
                    )
                    ->searchable()
                    ->required()
                    // Saat create, Teller/Admin bisa pilih. Anggota tidak membuat simpanan dari resource ini.
                    // Saat edit, hanya Admin (dengan izin update_savings) yang bisa ganti pemilik simpanan (jarang terjadi).
                    ->disabled(
                        $isEditing &&
                        (!$loggedInUser->can('update_savings') || ($record && $record->status === 'dikonfirmasi' && !$loggedInUser->hasRole('Admin')))
                    )
                    ->visible($isCreating || $loggedInUser->can('update_savings')),

                Forms\Components\Select::make('jenis_simpanan')
                    ->options([
                        'pokok' => 'Simpanan Pokok',
                        'wajib' => 'Simpanan Wajib',
                        'sukarela' => 'Simpanan Sukarela',
                    ])
                    ->required(),

                Forms\Components\TextInput::make('jumlah')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->minValue(0),

                Forms\Components\DatePicker::make('tanggal_transaksi')
                    ->required()
                    ->default(now())
                    ->maxDate(now()),

                // Field untuk upload bukti transfer
                FileUpload::make('bukti_transfer')
                    ->label('Bukti Transfer (Jika Ada)')
                    ->disk('public') // Simpan ke disk 'public' (storage/app/public)
                    ->directory('bukti-simpanan') // Buat folder 'bukti-simpanan' di dalam storage/app/public
                    ->image() // Hanya izinkan file gambar (opsional, bisa juga jenis file lain)
                    ->imageEditor() // Aktifkan editor gambar sederhana (opsional)
                    ->visibility('public') // Akses file
                    ->columnSpanFull()
                    // Mungkin hanya required jika metode pembayaran adalah transfer
                    // ->requiredIf('metode_pembayaran_field', 'transfer') // Perlu field metode pembayaran dulu
                    ,

                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'dikonfirmasi' => 'Dikonfirmasi',
                        'ditolak' => 'Ditolak',
                    ])
                    ->default('pending')
                    ->required()
                    // Teller set ke pending saat create. Admin bisa set langsung ke dikonfirmasi.
                    // Saat edit, hanya yang punya izin 'confirm_savings' atau 'update_savings' (Admin) bisa ubah.
                    ->disabled(function (string $operation) use ($loggedInUser, $record) {
                        if ($operation === 'create' && !$loggedInUser->can('confirm_savings')) {
                            return true; // Teller input sebagai pending
                        }
                        if ($operation === 'edit' && !$loggedInUser->can('confirm_savings') && !$loggedInUser->can('update_savings')) {
                            return true;
                        }
                        // Jika sudah dikonfirmasi dan bukan Admin, tidak bisa diubah lagi statusnya
                        if ($operation === 'edit' && $record?->status === 'dikonfirmasi' && !$loggedInUser->hasRole('Admin')) {
                            return true;
                        }
                        return false;
                    })
                    ->visible(fn(): bool => $loggedInUser->can('confirm_savings') || $loggedInUser->can('update_savings') || $isCreating),

                Forms\Components\Textarea::make('keterangan')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Nama Anggota')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('jenis_simpanan')->badge(),
                Tables\Columns\TextColumn::make('jumlah')->money('IDR', true)->sortable(),
                Tables\Columns\TextColumn::make('tanggal_transaksi')->date()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'dikonfirmasi' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('Tgl Input')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending' => 'Pending', 'dikonfirmasi' => 'Dikonfirmasi']),
                SelectFilter::make('user_id')->label('Anggota')
                    ->options(User::whereHas('roles', fn (Builder $query) => $query->where('name', 'Anggota'))->pluck('name', 'id'))
                    ->searchable()
                    ->visible(fn(): bool => auth()->user()->can('view_any_savings')),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending_approval' => 'Pending Approval', 'dikonfirmasi' => 'Dikonfirmasi', 'ditolak' => 'Ditolak', 'pending' => 'Pending (Lama)']),
                SelectFilter::make('jenis_simpanan') // <-- TAMBAHKAN ATAU PASTIKAN INI ADA
                ->options([
                    'pokok' => 'Simpanan Pokok',
                    'wajib' => 'Simpanan Wajib',
                    'sukarela' => 'Simpanan Sukarela',
                ])
                ->label('Jenis Simpanan'),
                SelectFilter::make('user_id')->label('Anggota')
                // ... (definisi filter user_id Anda)
             ])
            ->actions([
                Tables\Actions\EditAction::make()
                    // EditAction akan otomatis menggunakan SavingPolicy@update
                    // Anda bisa menambahkan kondisi visible tambahan jika perlu:
                    // ->visible(fn (Saving $record): bool => auth()->user()->can('update', $record)),
                    ,
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('approve_saving')
                        ->label('Setujui')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Saving $record) {
                            $record->status = 'dikonfirmasi';
                            // Jika Anda memiliki kolom untuk mencatat siapa yang menyetujui dan kapan:
                            // $record->approved_by = auth()->id();
                            // $record->approved_at = now();
                            $record->save();
                        })
                        // Tombol ini hanya muncul jika statusnya pending dan user punya izin 'confirmOrReject' (dari policy)
                        ->visible(fn (Saving $record): bool =>
                            in_array($record->status, ['pending_approval', 'pending']) &&
                            auth()->user()->can('confirmOrReject', $record)
                        ),

                    Tables\Actions\Action::make('reject_saving')
                        ->label('Tolak')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->form([ // Tambahkan form untuk alasan penolakan
                            Forms\Components\Textarea::make('rejection_reason')
                                ->label('Alasan Penolakan')
                                ->required(),
                        ])
                        ->action(function (Saving $record, array $data) {
                            $record->status = 'ditolak';
                            // Anda bisa menyimpan $data['rejection_reason'] ke kolom keterangan atau kolom baru jika ada
                            $record->keterangan = ($record->keterangan ? $record->keterangan . "\n" : "") . "Ditolak: " . $data['rejection_reason'];
                            // Jika Anda memiliki kolom untuk mencatat siapa yang menolak dan kapan:
                            $record->rejected_by = auth()->id();
                            $record->rejected_at = now();
                            $record->save();
                        })
                        // Tombol ini hanya muncul jika statusnya pending dan user punya izin 'confirmOrReject' (dari policy)
                        ->visible(fn (Saving $record): bool => auth()->user()->can('confirmOrReject', $record)),
                ])
                ->label('Aksi Cepat') // Label untuk ActionGroup
                ->icon('heroicon-m-ellipsis-vertical') // Ikon untuk ActionGroup
                ->color('gray') // Warna ikon ActionGroup
                // ActionGroup ini hanya muncul jika statusnya pending dan user punya izin 'confirmOrReject' (dari policy)
                ->visible(fn (Saving $record): bool => auth()->user()->can('confirmOrReject', $record)),
                

                Tables\Actions\DeleteAction::make(),
                // DeleteAction akan otomatis menggunakan SavingPolicy@delete
                // ->visible(fn (Saving $record): bool => auth()->user()->can('delete', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array {
        return [
            'index' => Pages\ListSavings::route('/'),
            'create' => Pages\CreateSaving::route('/create'),
            'edit' => Pages\EditSaving::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder {
        $user = auth()->user();
        if ($user->hasRole('Anggota') && $user->can('view_own_savings')) {
            return parent::getEloquentQuery()->where('user_id', $user->id);
        }
        // Jika bukan Admin/Teller/MK dan bukan Anggota (atau tidak punya izin lihat semua), jangan tampilkan apa-apa
        if (!$user->can('view_any_savings') && !$user->hasRole('Anggota')) {
             return parent::getEloquentQuery()->whereNull('id'); // Query yang tidak menghasilkan apa-apa
        }
        // Admin, Teller, Keuangan bisa lihat semua (dibatasi oleh Policy `viewAny`)
        return parent::getEloquentQuery();
    }
}