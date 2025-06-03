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
                // ... (konfigurasi user_id tetap sama seperti sebelumnya)
                // ...
                ->label('Anggota')
                ->options(
                    User::whereHas('roles', fn (Builder $query) => $query->where('name', 'Anggota'))->pluck('name', 'id')
                )
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
                        if ($loggedInUser && $loggedInUser->can('update_savings') && $record?->status === 'dikonfirmasi' && $loggedInUser->hasRole('Admin')) { // Hanya Admin bisa ubah user_id jika sudah dikonfirmasi
                            return false;
                        }
                        return true; // Selain Admin, atau jika status bukan dikonfirmasi, user_id disable saat edit
                    }
                    return false;
                })
                ->visible(true), // Selalu visible saat create, Admin bisa lihat/ubah saat edit


            Forms\Components\Select::make('jenis_simpanan')
                ->label('Jenis Simpanan')
                ->options(function () use ($loggedInUser, $isCreating) {
                    $allOptions = [
                        'pokok' => 'Simpanan Pokok',
                        'wajib' => 'Simpanan Wajib',
                        'sukarela' => 'Simpanan Sukarela',
                        // Tambahkan jenis simpanan lain jika ada
                    ];

                    // Jika Anggota yang membuat, hanya izinkan jenis tertentu (misalnya hanya sukarela)
                    // ATAU biarkan mereka memilih dari semua jenis jika itu aturannya.
                    // Untuk contoh ini, kita izinkan Anggota memilih dari semua jenis,
                    // tapi Anda bisa filter di sini jika perlu.
                    if ($isCreating && $loggedInUser && $loggedInUser->hasRole('Anggota')) {
                        // Jika Anggota hanya boleh sukarela:
                        // return ['sukarela' => 'Simpanan Sukarela'];
                        // Jika Anggota boleh pilih semua (atau subset tertentu):
                        return $allOptions; // Atau filter $allOptions di sini
                    }
                    // Admin dan Teller bisa memilih dari semua opsi
                    return $allOptions;
                })
                ->required()
                // Field ini TIDAK LAGI di-disable secara otomatis untuk Anggota
                // ->disabled(function () use ($loggedInUser, $isCreating) {
                //     return $isCreating && $loggedInUser && $loggedInUser->hasRole('Anggota');
                // })
                // Default bisa dikosongkan agar Anggota harus memilih
                ->default(null),

            Forms\Components\TextInput::make('jumlah')
                ->required()->numeric()->prefix('Rp')->minValue(0),
            // ... (sisa field seperti tanggal_transaksi, bukti_transfer, status, keterangan)
            // Pastikan field status diatur dengan benar
            Forms\Components\DatePicker::make('tanggal_transaksi')->required()->default(now())->maxDate(now()),

            FileUpload::make('bukti_transfer')
                ->label('Bukti Transfer (Jika Ada)')
                ->disk('public')->directory('bukti-simpanan')->image()->imageEditor()->visibility('public')
                ->columnSpanFull(),

            Forms\Components\Select::make('status')
                ->label('Status Simpanan')
                ->options(['pending_approval' => 'Pending Approval', 'dikonfirmasi' => 'Dikonfirmasi', 'ditolak' => 'Ditolak'])
                ->default(function(string $operation) use ($loggedInUser) {
                    // Jika Anggota yang membuat, status selalu 'pending_approval'
                    if ($operation === 'create' && $loggedInUser && $loggedInUser->hasRole('Anggota')) {
                        return 'pending_approval';
                    }
                    // Jika Admin/Teller yang bisa konfirmasi, mereka bisa memilih saat create atau default ke dikonfirmasi
                    if ($operation === 'create' && $loggedInUser && $loggedInUser->can('confirm_savings')) {
                        // Biarkan mereka memilih, atau default ke dikonfirmasi jika itu alurnya
                        return 'pending_approval'; // Atau 'dikonfirmasi' jika Teller/Admin bisa langsung konfirm
                    }
                    return 'pending_approval'; // Default umum saat create jika tidak ada kondisi di atas
                })
                ->required()
                ->disabled(function(string $operation) use ($loggedInUser, $record, $isEditing) {
                    // Anggota tidak bisa set status saat create, akan dihandle mutateFormDataBeforeCreate
                    if ($operation === 'create' && $loggedInUser && $loggedInUser->hasRole('Anggota')) {
                        return true;
                    }
                    // Jika Teller tanpa izin konfirmasi, statusnya default pending dan disabled
                    if ($operation === 'create' && !$loggedInUser->can('confirm_savings')) {
                        return true;
                    }
                    // Saat edit, hanya yang bisa konfirmasi yang bisa ubah status
                    if ($isEditing && !$loggedInUser->can('confirm_savings')) {
                        return true;
                    }
                    return false;
                })
                ->visible(true), // Selalu visible, tapi status disabled diatur di atas

            Forms\Components\Textarea::make('keterangan')->columnSpanFull(),
        ]);
}

    // ... (method table(), getRelations(), getPages(), getEloquentQuery() tetap sama)
    // Kode method table() dari respons sebelumnya sudah baik
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Nama Anggota')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('jenis_simpanan')->badge(),
                Tables\Columns\TextColumn::make('jumlah')->money('IDR', true)->sortable(),
                Tables\Columns\ImageColumn::make('bukti_transfer')->label('Bukti')->disk('public')->toggleable(),
                Tables\Columns\TextColumn::make('tanggal_transaksi')->date()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending_approval' => 'warning',
                        'pending' => 'warning',
                        'dikonfirmasi' => 'success',
                        'ditolak' => 'danger',
                        default => 'gray',
                    })->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Tgl Input')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending_approval' => 'Pending Approval', 'dikonfirmasi' => 'Dikonfirmasi', 'ditolak' => 'Ditolak', 'pending' => 'Pending (Lama)']),
                SelectFilter::make('jenis_simpanan')
                    ->options(['pokok' => 'Simpanan Pokok', 'wajib' => 'Simpanan Wajib', 'sukarela' => 'Simpanan Sukarela'])
                    ->label('Jenis Simpanan'),
                SelectFilter::make('user_id')->label('Anggota')
                    ->options(User::whereHas('roles', fn (Builder $query) => $query->where('name', 'Anggota'))->pluck('name', 'id'))
                    ->searchable()->visible(fn(): bool => auth()->user()->can('view_any_savings')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (Saving $record): bool => !in_array($record->status, ['dikonfirmasi', 'ditolak']) || auth()->user()->hasRole('Admin')),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('approve_saving')
                        ->label('Setujui')->icon('heroicon-o-check-circle')->color('success')->requiresConfirmation()
                        ->action(fn (Saving $record) => $record->update(['status' => 'dikonfirmasi']))
                        ->visible(fn (Saving $record): bool => auth()->user()->can('confirmOrReject', $record)),
                    Tables\Actions\Action::make('reject_saving')
                        ->label('Tolak')->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()
                        ->form([Forms\Components\Textarea::make('rejection_reason')->label('Alasan Penolakan')->required()])
                        ->action(function (Saving $record, array $data) {
                            $record->keterangan = ($record->keterangan ? $record->keterangan . "\n" : "") . "Ditolak: " . $data['rejection_reason'];
                            $record->status = 'ditolak';
                            $record->save();
                        })
                        ->visible(fn (Saving $record): bool => auth()->user()->can('confirmOrReject', $record)),
                ])->label('Aksi Cepat')->icon('heroicon-m-ellipsis-vertical')->color('gray')
                  ->visible(fn (Saving $record): bool => auth()->user()->can('confirmOrReject', $record)),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
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
        if (!$user->can('view_any_savings') && !$user->hasRole('Anggota')) {
             return parent::getEloquentQuery()->whereNull('id');
        }
        return parent::getEloquentQuery();
    }
}