<?php

namespace App\Filament\Resources\LoanResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Loan; // Untuk mengambil user_id dari owner loan

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';
    protected static ?string $recordTitleAttribute = 'id'; // Atau atribut lain yang relevan dari Payment

    public function form(Form $form): Form
    {
        $loggedInUser = auth()->user();
        /** @var Loan $loanRecord */ // Type hint untuk $this->getOwnerRecord()
        $loanRecord = $this->getOwnerRecord();
        $loanOwnerUserId = $loanRecord ? $loanRecord->user_id : null; // Dapatkan user_id dari pinjaman induk

        return $form
            ->schema([
                // user_id (anggota pemilik pinjaman) diisi otomatis dari pinjaman induk
                Forms\Components\Hidden::make('user_id')
                    ->default($loanOwnerUserId) // Ambil dari owner record (Loan)
                    ->dehydrated(), // PENTING

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

    public function table(Table $table): Table
    {
        return $table
            // Kita tidak perlu menampilkan nama anggota atau ID Pinjaman di sini karena sudah dalam konteks satu pinjaman
            ->columns([
                Tables\Columns\TextColumn::make('jumlah_pembayaran')->money('IDR', true)->sortable(),
                Tables\Columns\TextColumn::make('tanggal_pembayaran')->date()->sortable(),
                Tables\Columns\TextColumn::make('metode_pembayaran')->badge(),
                Tables\Columns\TextColumn::make('processor.name')->label('Diproses Oleh')->sortable(), // Asumsi ada relasi 'processor' di model Payment
                Tables\Columns\TextColumn::make('created_at')->label('Tgl Input')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filter biasanya tidak terlalu dibutuhkan di relation manager
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn (): bool => auth()->user()->can('create_payments')), // Hanya yang punya izin bisa buat angsuran
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                     ->visible(fn ($record): bool => auth()->user()->can('update', $record)), // Menggunakan PaymentPolicy
                Tables\Actions\DeleteAction::make()
                     ->visible(fn ($record): bool => auth()->user()->can('delete', $record)), // Menggunakan PaymentPolicy
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->can('delete_payments')), // Sesuaikan permission untuk bulk delete
                ]),
            ]);
    }
}