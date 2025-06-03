<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransaksiSetoranResource\Pages;
// Tidak ada model utama untuk resource ini, atau Anda bisa buat model dummy jika perlu
// use App\Models\TransaksiSetoran; // Contoh jika Anda buat model dummy
use App\Models\User;
use App\Models\Saving;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification; // Untuk notifikasi Filament
// Import lain yang mungkin Anda butuhkan

class TransaksiSetoranResource extends Resource
{
    // Resource ini tidak memiliki model Eloquent utama untuk CRUD standar
    // Jika Anda membuat model dummy, Anda bisa set di sini. Jika tidak, bisa di-null atau dikomentari.
    // protected static ?string $model = TransaksiSetoran::class;
    protected static ?string $model = Saving::class; // Gunakan Saving sebagai placeholder, tapi kita tidak akan CRUD Saving dari sini
    protected static ?string $slug = 'transaksi-setoran-gabungan';


    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-on-square-stack'; // Ganti ikon jika perlu
    protected static ?string $navigationGroup = 'Transaksi Keuangan';
    protected static ?string $navigationLabel = 'Setoran Simpanan Gabungan';
    protected static ?int $navigationSort = 1; // Sesuaikan urutan agar di atas Data Simpanan biasa

    // Kontrol siapa yang bisa melihat menu ini
    public static function canViewAny(): bool
    {
        // Hanya Teller dan Admin yang bisa melakukan setoran gabungan
        // dan mereka harus punya izin dasar 'create_savings'
        return auth()->user()->hasAnyRole(['Admin', 'Teller']) && auth()->user()->can('create_savings');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Anggota Penerima Setoran')
                    ->options(User::whereHas('roles', fn (Builder $query) => $query->where('name', 'Anggota'))->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\Grid::make(1)->schema([ // Grid untuk estetika
                    Forms\Components\Placeholder::make('info_tanggal_transaksi')
                        ->label('Tanggal Transaksi Umum untuk Setoran Ini')
                        ->content('Jika tanggal transaksi untuk setiap jenis simpanan berbeda, Anda bisa mengosongkan field tanggal di bawah dan mengisinya per jenis. Jika sama, isi tanggal di salah satu jenis simpanan di bawah, yang lain akan mengikuti jika dikosongkan.')
                ])->columnSpanFull(),


                Forms\Components\Section::make('Simpanan Pokok')
                    ->description('Input jika ada setoran simpanan pokok.')
                    ->schema([
                        Forms\Components\TextInput::make('jumlah_pokok')
                            ->label('Jumlah Setoran Pokok')
                            ->numeric()->prefix('Rp')->minValue(0)
                            ->helperText('Kosongkan atau isi 0 jika tidak ada.'),
                        Forms\Components\DatePicker::make('tanggal_transaksi_pokok')
                            ->label('Tgl Transaksi Pokok')
                            ->default(now())->maxDate(now())
                            ->visible(fn (Forms\Get $get) => filled($get('jumlah_pokok')) && $get('jumlah_pokok') > 0),
                        Forms\Components\FileUpload::make('bukti_transfer_pokok')
                            ->label('Bukti Transfer Pokok (Opsional)')
                            ->disk('public')->directory('bukti-setoran-gabungan/pokok')
                            ->image()->columnSpanFull()
                            ->visible(fn (Forms\Get $get) => filled($get('jumlah_pokok')) && $get('jumlah_pokok') > 0),
                        Forms\Components\Textarea::make('keterangan_pokok')
                            ->label('Keterangan Pokok (Opsional)')
                            ->columnSpanFull()
                            ->visible(fn (Forms\Get $get) => filled($get('jumlah_pokok')) && $get('jumlah_pokok') > 0),
                    ])->collapsible()->collapsed(true)->columns(1), // Default tertutup

                Forms\Components\Section::make('Simpanan Wajib')
                    ->description('Input jika ada setoran simpanan wajib.')
                    ->schema([
                        Forms\Components\TextInput::make('jumlah_wajib')
                            ->label('Jumlah Setoran Wajib')
                            ->numeric()->prefix('Rp')->minValue(0)
                            ->helperText('Kosongkan atau isi 0 jika tidak ada.'),
                        Forms\Components\DatePicker::make('tanggal_transaksi_wajib')
                            ->label('Tgl Transaksi Wajib')
                            ->default(now())->maxDate(now())
                            ->visible(fn (Forms\Get $get) => filled($get('jumlah_wajib')) && $get('jumlah_wajib') > 0),
                        Forms\Components\FileUpload::make('bukti_transfer_wajib')
                            ->label('Bukti Transfer Wajib (Opsional)')
                            ->disk('public')->directory('bukti-setoran-gabungan/wajib')
                            ->image()->columnSpanFull()
                            ->visible(fn (Forms\Get $get) => filled($get('jumlah_wajib')) && $get('jumlah_wajib') > 0),
                        Forms\Components\Textarea::make('keterangan_wajib')
                            ->label('Keterangan Wajib (Opsional)')
                            ->columnSpanFull()
                            ->visible(fn (Forms\Get $get) => filled($get('jumlah_wajib')) && $get('jumlah_wajib') > 0),
                    ])->collapsible()->collapsed(true)->columns(1), // Default tertutup

                Forms\Components\Section::make('Simpanan Sukarela')
                    ->description('Input jika ada setoran simpanan sukarela.')
                    ->schema([
                        Forms\Components\TextInput::make('jumlah_sukarela')
                            ->label('Jumlah Setoran Sukarela')
                            ->numeric()->prefix('Rp')->minValue(0)
                            ->helperText('Kosongkan atau isi 0 jika tidak ada.'),
                        Forms\Components\DatePicker::make('tanggal_transaksi_sukarela')
                            ->label('Tgl Transaksi Sukarela')
                            ->default(now())->maxDate(now())
                            ->visible(fn (Forms\Get $get) => filled($get('jumlah_sukarela')) && $get('jumlah_sukarela') > 0),
                        Forms\Components\FileUpload::make('bukti_transfer_sukarela')
                            ->label('Bukti Transfer Sukarela (Opsional)')
                            ->disk('public')->directory('bukti-setoran-gabungan/sukarela')
                            ->image()->columnSpanFull()
                            ->visible(fn (Forms\Get $get) => filled($get('jumlah_sukarela')) && $get('jumlah_sukarela') > 0),
                        Forms\Components\Textarea::make('keterangan_sukarela')
                            ->label('Keterangan Sukarela (Opsional)')
                            ->columnSpanFull()
                            ->visible(fn (Forms\Get $get) => filled($get('jumlah_sukarela')) && $get('jumlah_sukarela') > 0),
                    ])->collapsible()->collapsed(false)->columns(1), // Default terbuka untuk sukarela
            ]);
    }

    // Kita tidak akan menampilkan tabel data "Transaksi Setoran Gabungan"
    // karena ini hanya form input. Jika Anda ingin log, Anda perlu model dan tabel sendiri.
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Tidak ada kolom, atau tampilkan log jika ada modelnya
            ])
            ->filters([
                //
            ])
            ->actions([
                // Tidak ada aksi edit/delete untuk form input ini
            ])
            ->bulkActions([
                // Tidak ada bulk action
            ]);
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
            // Kita hanya butuh halaman create untuk resource ini
            // 'index' => Pages\ListTransaksiSetorans::route('/'), // Hapus atau buat jika ingin ada list log
            'create' => Pages\CreateTransaksiSetoran::route('/create'),
            // 'edit' => Pages\EditTransaksiSetoran::route('/{record}/edit'), // Hapus
            // 'view' => Pages\ViewTransaksiSetoran::route('/{record}'), // Hapus
        ];
    }

    // Tidak ada query Eloquent utama karena ini bukan Resource CRUD standar
    // public static function getEloquentQuery(): Builder
    // {
    //     return parent::getEloquentQuery();
    // }
}