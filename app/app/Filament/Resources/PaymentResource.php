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
use Filament\Forms\Components\FileUpload;

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
        $isCreating = $form->getOperation() === 'create';

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
                        return "ID: {$record->id} - Peminjam: {$userName} (Rp " . number_format($record->jumlah_pembayaran, 0, ',', '.') . ")";
                    })
                    ->searchable(['id', 'user.name'])
                    ->preload()
                    ->live()
                    ->required()
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        if ($state) {
                            $loan = Loan::find($state);
                            if ($loan) {
                                $set('user_id', $loan->user_id);
                            }
                        } else {
                            $set('user_id', null);
                        }
                    })
                    ->columnSpanFull(),

                Forms\Components\Hidden::make('user_id')->dehydrated(),

                Forms\Components\TextInput::make('jumlah_pembayaran')->label('Jumlah Pembayaran')->required()->numeric()->prefix('Rp')->minValue(1),
                Forms\Components\DatePicker::make('tanggal_pembayaran')->label('Tanggal Pembayaran')->required()->default(now())->maxDate(now()),
                Forms\Components\Select::make('metode_pembayaran')->label('Metode Pembayaran')
                    ->options(['tunai' => 'Tunai', 'transfer_bank' => 'Transfer Bank', 'auto_debet' => 'Auto Debet Simpanan'])->required(),
                FileUpload::make('bukti_transfer')
                    ->label('Bukti Transfer Pembayaran (jika transfer)')
                    ->disk('public')->directory('bukti-angsuran')->image()->imageEditor()->visibility('public')
                    ->columnSpanFull()
                    ->requiredIf('metode_pembayaran', 'transfer_bank'), // Wajib jika metode transfer

                Forms\Components\Select::make('status') // <-- TAMBAHKAN FIELD STATUS INI
                    ->label('Status Pembayaran')
                    ->options([
                        'pending' => 'Pending',
                        'dikonfirmasi' => 'Dikonfirmasi',
                        'gagal' => 'Gagal',
                    ])
                    ->default('dikonfirmasi') // Default saat input
                    ->required()
                    // Atur visibilitas atau disable berdasarkan peran jika perlu
                    // Misalnya, hanya Admin/Teller yang bisa set status selain 'pending' saat create
                    ->visible(fn (): bool => auth()->user()->hasAnyRole(['Admin', 'Teller'])) // Contoh: hanya Admin/Teller bisa set status
                    ->disabled($isCreating && !auth()->user()->hasAnyRole(['Admin', 'Teller'])), // Jika Anggota input (jika ada skenario itu), status tidak bisa diubah

                Forms\Components\Textarea::make('keterangan')->label('Keterangan (Opsional)')->columnSpanFull(),
                Forms\Components\Hidden::make('processed_by')->default($loggedInUser->id),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('loan.id')->label('ID Pinj.')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('loan_user_name_accessor')
                    ->label('Nama Anggota')
                    ->getStateUsing(fn (Payment $record): ?string => $record->loan?->user?->name)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('loan.user', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"));
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $userTableName = (new User())->getTable();
                        $loanTableName = (new Loan())->getTable();
                        $paymentTableName = (new Payment())->getTable();
                        return $query->orderBy(
                            Loan::select("{$userTableName}.name")
                                ->join($userTableName, "{$userTableName}.id", '=', "{$loanTableName}.user_id")
                                ->whereColumn("{$loanTableName}.id", "{$paymentTableName}.loan_id"),
                            $direction
                        );
                    }),
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
                Tables\Columns\TextColumn::make('metode_pembayaran')->badge()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('processor.name')->label('Diproses Oleh')->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->label('Tgl Input')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),

                // ...
                Tables\Columns\ImageColumn::make('bukti_transfer')->label('Bukti')->disk('public')->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending_approval' => 'warning',
                        'dikonfirmasi' => 'success',
                        'gagal' => 'danger',
                        default => 'gray',
                    })->sortable(),
                // ...
            ])
            ->filters([
                SelectFilter::make('status') // <-- TAMBAHKAN FILTER STATUS INI
                    ->options([
                        'pending' => 'Pending',
                        'dikonfirmasi' => 'Dikonfirmasi',
                        'gagal' => 'Gagal',
                    ]),
                SelectFilter::make('loan_id')->label('ID Pinjaman')->relationship('loan', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Loan $record) => "ID: {$record->id} - {$record->user?->name}")
                    ->searchable()->preload()->visible(fn(): bool => auth()->user()->can('view_any_payments')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                // Anda bisa menambahkan Aksi untuk konfirmasi pembayaran di sini jika perlu
                Tables\Actions\ActionGroup::make([
                Tables\Actions\Action::make('approve_payment')
                    ->label('Setujui')->icon('heroicon-o-check-circle')->color('success')->requiresConfirmation()
                    ->action(fn (Payment $record) => $record->update(['status' => 'dikonfirmasi']))
                    ->visible(fn (Payment $record): bool => $record->status === 'pending_approval' && auth()->user()->can('confirm_payments')), // Ganti dengan permission yang sesuai
                Tables\Actions\Action::make('reject_payment')
                    ->label('Tolak')->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()
                    ->form([Forms\Components\Textarea::make('rejection_reason')->label('Alasan Penolakan')->required()])
                    ->action(function (Payment $record, array $data) {
                        $record->keterangan = ($record->keterangan ? $record->keterangan . "\n" : "") . "Ditolak: " . $data['rejection_reason'];
                        $record->status = 'gagal'; // Atau 'ditolak'
                        $record->save();
                    })
                    ->visible(fn (Payment $record): bool => $record->status === 'pending_approval' && auth()->user()->can('confirm_payments')), // Ganti dengan permission yang sesuai
            ])->label('Aksi Cepat')->icon('heroicon-m-ellipsis-vertical')->visible(fn (Payment $record): bool => $record->status === 'pending_approval' && auth()->user()->can('confirm_payments')), // Ganti dengan permission yang sesuai
            // ...
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    // ... (getRelations, getPages, getEloquentQuery tetap sama)
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
            return parent::getEloquentQuery()->where('user_id', $user->id);
        }
         if (!$user->can('view_any_payments') && !$user->hasRole('Anggota')) {
            return parent::getEloquentQuery()->whereNull('id');
       }
        return parent::getEloquentQuery();
    }
}