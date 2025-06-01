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

    // Method untuk mengontrol siapa yang bisa melihat menu ini di sidebar
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user->can('view_any_users') || $user->can('view_members');
    }


    public static function form(Form $form): Form
    {
        $loggedInUser = auth()->user();
        $isCreating = $form->getOperation() === 'create';
        $isEditing = $form->getOperation() === 'edit';
        /** @var ?User $editingRecord */
        $editingRecord = $form->getModelInstance();
        $editingSelf = $isEditing && $editingRecord && $editingRecord->id === $loggedInUser->id;

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
                            ->dehydrated(fn ($state) => filled($state))
                            ->revealable()
                            ->maxLength(255)
                            ->helperText($isEditing ? 'Kosongkan jika tidak ingin mengubah password.' : null)
                            // Bisa diisi saat create. Saat edit, hanya jika punya izin update pada record, atau edit profil sendiri.
                            ->disabled($isEditing && !$loggedInUser->can('update', $editingRecord) && !$editingSelf),
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
                                    // Pastikan $roleId adalah integer sebelum digunakan di findById
                                    $role = Role::findById((int)$roleId);
                                    if ($role && $role->name === 'Anggota') return true;
                                }
                                return false;
                            })
                            ->disabled($editingSelf && $loggedInUser->hasRole('Anggota')),
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
                            // Gunakan pengecekan permission Spatie secara langsung
                            ->visible(fn(): bool => $loggedInUser->can('assign_user_roles')) // <-- PERUBAHAN KUNCI
                            ->disabled(fn(): bool =>
                                !$loggedInUser->can('assign_user_roles') || // Disable jika tidak punya izin umum
                                ($editingSelf && !$loggedInUser->hasRole('Super Admin')) // Opsional: Super Admin bisa edit role sendiri
                            )
                            ->label('Peran Pengguna'),
                        Forms\Components\Toggle::make('email_verified_at')
                            ->label('Email Terverifikasi')
                            ->onIcon('heroicon-s-check-badge')
                            ->offIcon('heroicon-s-x-circle')
                            ->formatStateUsing(fn ($state) => (bool)$state)
                            ->dehydrateStateUsing(fn ($state) => $state ? now() : null)
                            // Hanya Admin dengan izin 'update_users' yang bisa verifikasi email dari form ini
                            ->visible(fn(): bool => $loggedInUser->hasRole('Admin') && $loggedInUser->can('update_users')),
                    ])->columns(2)
                    // Section ini hanya muncul jika user punya izin assign_user_roles ATAU Admin yang bisa update_users
                    ->visible($loggedInUser->can('assign_user_roles') || ($loggedInUser->hasRole('Admin') && $loggedInUser->can('update_users'))),
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

        if (($user->hasRole('Teller') || $user->hasRole('Manajer Keuangan')) && $user->can('view_members') && !$user->can('view_any_users')) {
            return $query->whereHas('roles', function (Builder $roleQuery) {
                $roleQuery->where('name', 'Anggota');
            });
        }
        if ($user->hasRole('Anggota') && !$user->can('view_any_users') && !$user->can('view_members')) {
            return $query->where('id', -1); // Anggota tidak melihat daftar user lain
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