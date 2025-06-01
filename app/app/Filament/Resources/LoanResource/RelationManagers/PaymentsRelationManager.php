<?php

namespace App\Filament\Resources\LoanResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Loan;
use Filament\Forms\Components\FileUpload;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';
    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        $loggedInUser = auth()->user();
        /** @var Loan $loanRecord */
        $loanRecord = $this->getOwnerRecord();
        $loanOwnerUserId = $loanRecord ? $loanRecord->user_id : null;
        $isCreating = $form->getOperation() === 'create';


        return $form
            ->schema([
                Forms\Components\Hidden::make('user_id')->default($loanOwnerUserId)->dehydrated(),
                Forms\Components\TextInput::make('jumlah_pembayaran')->label('Jumlah Pembayaran')->required()->numeric()->prefix('Rp')->minValue(1),
                Forms\Components\DatePicker::make('tanggal_pembayaran')->label('Tanggal Pembayaran')->required()->default(now())->maxDate(now()),
                Forms\Components\Select::make('metode_pembayaran')->label('Metode Pembayaran')
                    ->options(['tunai' => 'Tunai', 'transfer_bank' => 'Transfer Bank', 'auto_debet' => 'Auto Debet Simpanan'])->required(),

                Forms\Components\Select::make('status') // <-- TAMBAHKAN FIELD STATUS INI
                    ->label('Status Pembayaran')
                    ->options([
                        'pending' => 'Pending',
                        'dikonfirmasi' => 'Dikonfirmasi',
                        'gagal' => 'Gagal',
                    ])
                    ->default('dikonfirmasi')
                    ->required()
                    ->visible(fn (): bool => auth()->user()->hasAnyRole(['Admin', 'Teller'])) // Contoh
                    ->disabled($isCreating && !auth()->user()->hasAnyRole(['Admin', 'Teller'])),


                Forms\Components\Textarea::make('keterangan')->label('Keterangan (Opsional)')->columnSpanFull(),
                Forms\Components\Hidden::make('processed_by')->default($loggedInUser->id),
    
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('jumlah_pembayaran')->money('IDR', true)->sortable(),
                Tables\Columns\TextColumn::make('tanggal_pembayaran')->date()->sortable(),
                Tables\Columns\TextColumn::make('status') // <-- TAMBAHKAN KOLOM STATUS INI
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'dikonfirmasi' => 'success',
                        'gagal' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('metode_pembayaran')->badge(),
                Tables\Columns\TextColumn::make('processor.name')->label('Diproses Oleh')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Tgl Input')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status') // <-- TAMBAHKAN FILTER STATUS INI
                    ->options([
                        'pending' => 'Pending',
                        'dikonfirmasi' => 'Dikonfirmasi',
                        'gagal' => 'Gagal',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->visible(fn (): bool => auth()->user()->can('create_payments')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn ($record): bool => auth()->user()->can('update', $record)),
                Tables\Actions\DeleteAction::make()->visible(fn ($record): bool => auth()->user()->can('delete', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->visible(fn (): bool => auth()->user()->can('delete_payments')),
                ]),
            ]);
    }
}