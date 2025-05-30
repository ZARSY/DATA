<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role; // Import Role model dari Spatie
use Filament\Forms\Get; // Untuk conditional logic di form

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Pengguna & Akses';
    protected static ?string $navigationLabel = 'Data Pengguna';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        $loggedInUser = auth()->user();
        $isCreating = $form->getOperation() === 'create';
        $isEditing = $form->getOperation() === 'edit';
        $editingSelf = $isEditing && $form->getModelInstance()?->id === $loggedInUser->id;

        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Dasar')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->required($isCreating)
                            ->dehydrated(fn ($state) => filled($state)) // Hanya proses jika diisi (penting untuk edit)
                            ->revealable()
                            ->maxLength(255)
                            ->helperText($isEditing ? 'Kosongkan jika tidak ingin mengubah password.' : null)
                            // Hanya bisa diisi saat create, atau saat edit oleh admin, atau user edit profil sendiri
                            ->disabled($isEditing && !$loggedInUser->can('update_users', $form->getModelInstance()) && !$editingSelf),
                    ])->columns(2),

                Forms\Components\Section::make('Informasi Keanggotaan & Kontak')
                    ->schema([
                        Forms\Components\TextInput::make('nomor_anggota')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->required(function (Get $get) {
                                $roleIds = $get('roles');
                                if (empty($roleIds) || !is_array($roleIds)) return false;
                                foreach ($roleIds as $roleId) {
                                    $role = Role::findById((int)$roleId);
                                    if ($role && $role->name === 'Anggota') return true;
                                }
                                return false;
                            })
                            ->disabled($editingSelf && $loggedInUser->hasRole('Anggota')), // Anggota tidak bisa ubah no_anggota sendiri
                        Forms\Components\TextInput::make('telepon')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('alamat')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Hak Akses & Status')
                    ->schema([
                        Forms\Components\Select::make('roles')
                            ->multiple()
                            ->relationship(name: 'roles', titleAttribute: 'name')
                            ->preload()
                            ->searchable()
                            // Hanya user dengan permission 'assign_user_roles' yang bisa lihat/edit field ini
                            ->visible(fn(): bool => $loggedInUser->can('assign_user_roles', User::class))
                            // Jika user mengedit profilnya sendiri dan bukan Admin (atau tidak punya izin assign), jangan biarkan dia ubah rolenya
                            ->disabled($editingSelf && !$loggedInUser->can('assign_user_roles', $form->getModelInstance() ?? User::class))
                            ->label('Peran Pengguna'),
                        Forms\Components\Toggle::make('email_verified_at')
                            ->label('Email Terverifikasi')
                            ->onIcon('heroicon-s-check-badge')
                            ->offIcon('heroicon-s-x-circle')
                            ->formatStateUsing(fn ($state) => (bool)$state)
                            ->dehydrateStateUsing(fn ($state) => $state ? now() : null)
                            ->visible(fn(): bool => $loggedInUser->can('update_users', $form->getModelInstance() ?? User::class)), // Admin atau yang berhak bisa verifikasi
                    ])->columns(2)
                    ->visible($loggedInUser->can('assign_user_roles', User::class) || $loggedInUser->can('update_users', $form->getModelInstance() ?? User::class)), // Section ini hanya muncul jika user punya salah satu hak ini
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('nomor_anggota')->searchable()->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('roles.name')->badge()->label('Peran')->separator(','),
                Tables\Columns\IconColumn::make('email_verified_at')->boolean()->label('Verified')->trueIcon('heroicon-o-check-badge')->falseIcon('heroicon-o-x-circle'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->label('Filter Peran')
                    ->preload(),
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

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery();

        // Teller dan Manajer Keuangan hanya bisa melihat/mengelola user dengan role 'Anggota'
        // kecuali jika mereka punya permission 'view_any_users' (yang biasanya hanya dimiliki Admin)
        if (($user->hasRole('Teller') || $user->hasRole('Manajer Keuangan')) && !$user->can('view_any_users')) {
            return $query->whereHas('roles', function (Builder $roleQuery) {
                $roleQuery->where('name', 'Anggota');
            });
        }
        // Anggota tidak melihat daftar user lain sama sekali melalui resource ini
        if ($user->hasRole('Anggota') && !$user->can('view_any_users')) {
            return $query->where('id', $user->id); // Hanya dirinya sendiri jika terpaksa (atau query kosong)
        }
        return $query;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}