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
                        modifyQueryUsing: fn (Builder $query) => $query->whereIn('status', ['disetujui', 'berjalan'])
                    )
                    ->getOptionLabelFromRecordUsing(function (Loan $record) {
                        $userName = $record->user?->name ?? 'User Tidak Ditemukan';
                        return "ID: {$record->id} - Peminjam: {$userName} (Rp " . number_format($record->jumlah_pinjaman, 0, ',', '.') . ")";
                    })
                    ->searchable(['id', 'user.name'])
                    ->preload()
                    ->live()
                    ->required()
                    ->afterStateUpdated(function (Set $set, ?string $state) { // $state adalah loan_id
                        if ($state) {
                            $loan = Loan::find($state);
                            if ($loan) {
                                $set('user_id', $loan->user_id); // Set 'user_id' di form
                            }
                        } else {
                            $set('user_id', null);
                        }
                    })
                    ->columnSpanFull(),

                // user_id adalah anggota pemilik pinjaman, diisi otomatis
                Forms\Components\Hidden::make('user_id')
                    ->dehydrated(), // PENTING: Pastikan nilai dari field hidden ini disimpan ke database

                Forms\Components\TextInput::make('jumlah_pembayaran')
                    ->label('Jumlah Pembayaran')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->minValue(1),

                Forms\Components\DatePicker::make('tanggal_pembayaran')
                    ->label('Tanggal Pembayaran')
                    ->required()
                    ->default(now())
                    ->maxDate(now()),

                Forms\Components\Select::make('metode_pembayaran')
                    ->label('Metode Pembayaran')
                    ->options([
                        'tunai' => 'Tunai',
                        'transfer_bank' => 'Transfer Bank',
                        'auto_debet' => 'Auto Debet Simpanan',
                    ])
                    ->required(),

                Forms\Components\Textarea::make('keterangan')
                    ->label('Keterangan (Opsional)')
                    ->columnSpanFull(),

                Forms\Components\Hidden::make('processed_by')
                    ->default($loggedInUser->id),

                Forms\Components\Hidden::make('status_pembayaran')
                    ->default('dikonfirmasi'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('loan.id')
                    ->label('ID Pinj.')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('loan_user_name_accessor') // Menggunakan accessor jika relasi kompleks
                    ->label('Nama Anggota')
                    ->getStateUsing(function (Payment $record): ?string { // Cara lebih aman
                        return $record->loan?->user?->name;
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder { // Custom search
                        return $query->whereHas('loan.user', function (Builder $q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder { // Custom sort
                        return $query->orderBy(
                            // Ini adalah contoh subquery untuk sorting, pastikan nama tabel dan kolom sesuai
                            Loan::select('users.name')
                                ->join('users', 'users.id', '=', 'loans.user_id') // Asumsi tabel users dan loans
                                ->whereColumn('loans.id', 'payments.loan_id'), // Sesuaikan nama tabel jika berbeda
                            $direction
                        );
                    }),
                Tables\Columns\TextColumn::make('jumlah_pembayaran')
                    ->money('IDR', true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('tanggal_pembayaran')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('metode_pembayaran')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('processor.name') // Pastikan relasi 'processor' ada di model Payment
                    ->label('Diproses Oleh')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tgl Input')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('loan_id')
                    ->label('ID Pinjaman')
                    ->relationship('loan', 'id') // Menampilkan ID pinjaman di filter
                    ->getOptionLabelFromRecordUsing(function (Loan $record) { // Kustomisasi label di filter
                        $userName = $record->user?->name ?? 'User Tidak Ditemukan';
                        return "ID: {$record->id} - {$userName}";
                    })
                    ->searchable()
                    ->preload()
                    // Filter ini hanya visible jika user punya izin lihat semua pembayaran
                    ->visible(fn(): bool => auth()->user()->can('view_any_payments')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Biasanya tidak ada relation manager di bawah Payment
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        if ($user->hasRole('Anggota') && $user->can('view_own_payments')) {
            // Filter pembayaran berdasarkan user_id di tabel payments
            // (user_id di tabel payments adalah ID anggota pemilik pinjaman)
            return parent::getEloquentQuery()->where('user_id', $user->id);
        }

        if (!$user->can('view_any_payments') && !$user->hasRole('Anggota')) {
           return parent::getEloquentQuery()->whereNull('id'); // Query kosong jika tidak ada izin
        }

        return parent::getEloquentQuery();
    }
}