<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
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
use Filament\Forms\Set;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'Transaksi Keuangan';
    protected static ?string $navigationLabel = 'Data Angsuran';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        $loggedInUser = auth()->user();
        return $form
            ->schema([
                Forms\Components\Select::make('loan_id')
                    ->label('Pinjaman Anggota')
                    ->relationship(
                        name: 'loan',
                        titleAttribute: 'id',
                        modifyQueryUsing: fn (Builder $query) => $query->whereIn('status', ['disetujui', 'berjalan']) // Hanya pinjaman yang aktif
                    )
                    ->getOptionLabelFromRecordUsing(function (Loan $record) {
                        $userName = $record->user ? $record->user->name : 'User Tidak Ditemukan';
                        return "ID: {$record->id} - Peminjam: {$userName} (Rp " . number_format($record->jumlah_pinjaman, 0, ',', '.') . ")";
                    })
                    ->searchable(
                        // Mencari berdasarkan ID pinjaman dan nama anggota (peminjam)
                        // Ini membutuhkan setup agar relasi user bisa di-search dari loan.
                        // Jika tidak kompleks, cukup search berdasarkan ID atau atribut lain di Loan.
                        // Untuk pencarian nama user, pastikan relasi user di model Loan ada dan bisa diakses.
                        // Untuk sementara, kita bisa search ID saja, atau jika relasi user di Loan sudah benar:
                         ['id', 'user.name'] // Ini mengasumsikan relasi user di model Loan bisa di-query seperti ini.
                                            // Jika tidak, sederhanakan ke ['id'] saja atau buat custom search logic.
                    )
                    ->preload()
                    ->live() // Agar user_id bisa di-update otomatis
                    ->required()
                    // Setelah loan_id dipilih, otomatis isi user_id (pemilik pinjaman)
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        if ($state) {
                            $loan = Loan::find($state);
                            if ($loan) {
                                $set('user_id', $loan->user_id);
                            }
                        } else {
                            $set('user_id', null);
                        }
                    }),
                // user_id adalah anggota pemilik pinjaman
                Forms\Components\Hidden::make('user_id')->dehydrated(),

                Forms\Components\TextInput::make('jumlah_pembayaran')
                    ->required()->numeric()->prefix('Rp')->minValue(1)
                    ->currencyMask(thousandSeparator: '.', decimalSeparator: ',', precision: 2),
                Forms\Components\DatePicker::make('tanggal_pembayaran')->required()->default(now()),
                Forms\Components\Select::make('metode_pembayaran')
                    ->options(['tunai' => 'Tunai', 'transfer_bank' => 'Transfer Bank', 'auto_debet' => 'Auto Debet Simpanan']),
                Forms\Components\Textarea::make('keterangan')->columnSpanFull(),
                Forms\Components\Hidden::make('processed_by')->default($loggedInUser->id),
                Forms\Components\Hidden::make('status_pembayaran')->default('dikonfirmasi'),
            ]);
    }

    public static function table(Table $table): Table { /* ... (Kode tabel dari respons sebelumnya sudah cukup baik) ... */
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('loan.id')->label('ID Pinj.')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('loan.user.name')->label('Nama Anggota')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('jumlah_pembayaran')->money('IDR', true)->sortable(),
                Tables\Columns\TextColumn::make('tanggal_pembayaran')->date()->sortable(),
                Tables\Columns\TextColumn::make('metode_pembayaran')->badge()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('processor.name')->label('Diproses Oleh')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('loan_id')->label('ID Pinjaman')->relationship('loan', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Loan $record) => "ID: {$record->id} - {$record->user?->name}") // Tambah ? untuk user
                    ->searchable()->preload()->visible(fn(): bool => auth()->user()->can('view_any_payments')),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }
    public static function getRelations(): array { return []; }
    public static function getPages(): array {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
    public static function getEloquentQuery(): Builder {
        $user = auth()->user();
        if ($user->hasRole('Anggota') && $user->can('view_own_payments')) {
            return parent::getEloquentQuery()->where('user_id', $user->id); // Langsung filter berdasarkan user_id di tabel payments
        }
         if (!$user->can('view_any_payments') && !$user->hasRole('Anggota')) {
            return parent::getEloquentQuery()->whereNull('id');
       }
        return parent::getEloquentQuery();
    }
}