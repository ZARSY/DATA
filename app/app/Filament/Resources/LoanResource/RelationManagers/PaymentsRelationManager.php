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
use Filament\Tables\Filters\SelectFilter;
use App\Notifications\PaymentApprovalNeeded; // Tambahkan ini
use App\Helpers\NotificationRecipients;    // Tambahkan ini
use Illuminate\Support\Facades\Notification as NotificationFacade; // Tambahkan ini
use App\Models\Payment;                    // Tambahkan ini
use Filament\Forms\Get; // Untuk Get di form jika perlu

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
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array { // Untuk memastikan data sebelum create
                        $loggedInUser = auth()->user();
                        /** @var \App\Models\Loan $loanRecord */
                        $loanRecord = $this->getOwnerRecord();
                        $data['user_id'] = $loanRecord?->user_id; // Ambil user_id dari pinjaman induk
                        $data['processed_by'] = $loggedInUser->id;

                        // Logika status pembayaran
                        if (empty($data['status'])) {
                            if (isset($data['metode_pembayaran']) && $data['metode_pembayaran'] === 'transfer_bank') {
                                 $data['status'] = 'pending_approval';
                            } else {
                                 $data['status'] = ($loggedInUser && $loggedInUser->can('confirm_payments')) ? 'dikonfirmasi' : 'pending_approval';
                            }
                        } else {
                             if ($data['status'] === 'dikonfirmasi' && $loggedInUser && $loggedInUser->hasRole('Teller') && !$loggedInUser->can('confirm_payments')) {
                                $data['status'] = 'pending_approval';
                            }
                        }
                        return $data;
                    })
                    ->after(function (Payment $record) { // Hook setelah record dibuat
                        /** @var \App\Models\User $member */
                        $member = $record->member;
                        if ($record->status === 'pending_approval' && $member) {
                            $confirmers = NotificationRecipients::getPaymentConfirmers();
                            if ($confirmers->isNotEmpty()) {
                                NotificationFacade::send($confirmers, new PaymentApprovalNeeded($record, $member));
                            }
                        }
                    })
                    ->visible(fn (): bool => auth()->user()->can('create_payments')),
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