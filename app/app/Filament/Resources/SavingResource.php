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

class SavingResource extends Resource
{
    protected static ?string $model = Saving::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Transaksi Keuangan';
    protected static ?string $navigationLabel = 'Data Simpanan';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        $loggedInUser = auth()->user();
        $isCreating = $form->getOperation() === 'create';

        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Anggota')
                    ->options(
                        User::whereHas('roles', fn (Builder $query) => $query->where('name', 'Anggota'))->pluck('name', 'id')
                    )
                    ->searchable()
                    ->required()
                    // Hanya bisa dipilih saat create oleh Teller/Admin
                    ->disabled(!$isCreating && !$loggedInUser->can('update_savings')) // Admin bisa ganti saat edit
                    ->visible($isCreating || $loggedInUser->can('update_savings')), // Admin bisa lihat saat edit
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
                ->default(now()),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'dikonfirmasi' => 'Dikonfirmasi',
                    ])
                    ->default('pending')
                    ->required()
                    // Saat create, Teller set ke pending, Admin bisa langsung konfirmasi jika mau
                    // Saat edit, hanya yang punya izin 'confirm_savings' atau 'update_savings' (Admin) bisa ubah
                    ->disabled(fn(string $operation, Get $get) =>
                        ($operation === 'create' && !$loggedInUser->can('confirm_savings')) ||
                        ($operation === 'edit' && !$loggedInUser->can('confirm_savings') && !$loggedInUser->can('update_savings'))
                    )
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
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending' => 'Pending', 'dikonfirmasi' => 'Dikonfirmasi']),
                SelectFilter::make('user_id')->label('Anggota')
                    ->options(User::whereHas('roles', fn (Builder $query) => $query->where('name', 'Anggota'))->pluck('name', 'id'))
                    ->searchable()
                    ->visible(fn(): bool => auth()->user()->can('view_any_savings')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('confirm')
                    ->label('Konfirmasi')
                    ->icon('heroicon-o-check-circle')->color('success')
                    ->requiresConfirmation()
                    ->action(fn (Saving $record) => $record->update(['status' => 'dikonfirmasi']))
                    ->visible(fn (Saving $record): bool => $record->status === 'pending' && auth()->user()->can('confirm_savings')),
                Tables\Actions\DeleteAction::make(),
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
        if (!$user->can('view_any_savings') && !$user->hasRole('Anggota')) {
             return parent::getEloquentQuery()->whereNull('id');
        }
        return parent::getEloquentQuery();
    }
}