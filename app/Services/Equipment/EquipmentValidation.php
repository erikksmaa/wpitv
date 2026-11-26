<?php

namespace App\Services\Equipment;

use Illuminate\Support\Facades\Validator;

final class EquipmentValidation
{
    public static function validate(array $input, array $rules, array $labels = []): array
    {
        return Validator::make($input, $rules, [
            'required' => ':attribute harus diisi.',
            'string' => ':attribute harus berupa teks.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'boolean' => 'Pilih :attribute yang valid.',
            'in' => 'Pilih :attribute yang tersedia.',
            'exists' => ':attribute tidak ditemukan atau tidak sesuai dengan pilihan lain.',
            'date_format' => ':attribute tidak valid.',
            'after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.',
            'max.string' => ':attribute maksimal :max karakter.',
            'max.array' => ':attribute maksimal :max pilihan.',
            'max.file' => 'Ukuran foto maksimal 5 MB.',
            'min.numeric' => ':attribute minimal :min.',
            'min.array' => 'Pilih minimal :min pada :attribute.',
            'between.numeric' => ':attribute harus antara :min dan :max.',
            'array' => 'Pilihan :attribute tidak valid.',
            'distinct' => 'Unit yang sama hanya boleh dipilih sekali.',
            'regex' => 'Gunakan huruf kapital dan angka, dengan tanda hubung sebagai pemisah.',
            'file' => 'Foto gagal diunggah.',
            'uploaded' => 'Foto gagal diunggah. Periksa ukuran dan coba lagi.',
            'accepted' => 'Centang konfirmasi sebelum membatalkan peminjaman.',
            'units.required' => 'Pilih minimal satu unit.',
            'selected.required' => 'Pilih minimal satu unit.',
            'category_id.required' => 'Pilih kategori terlebih dahulu.',
            'photos.*.required' => 'Unggah minimal satu foto untuk unit ini.',
        ], $labels + [
            'reason'=>'Alasan', 'name'=>'Nama', 'code'=>'Kode', 'category_id'=>'Kategori',
            'description'=>'Deskripsi', 'is_active'=>'Status', 'quantity'=>'Jumlah unit',
            'condition'=>'Kondisi', 'serial_number'=>'Nomor serial',
            'borrower_type'=>'Jenis peminjam', 'borrower_name'=>'Nama peminjam',
            'identity_number'=>'Identitas peminjam', 'borrower_contact'=>'Kontak',
            'borrower_affiliation'=>'Program studi atau instansi', 'purpose'=>'Keperluan',
            'due_date'=>'Tanggal jatuh tempo', 'units'=>'Unit', 'units.*'=>'Unit',
            'selected'=>'Unit', 'selected.*'=>'Unit', 'items'=>'Unit', 'items.*'=>'Unit',
            'items.*.loan_unit_id'=>'Unit', 'items.*.notes'=>'Catatan unit', 'items.*.condition'=>'Kondisi akhir',
            'notes'=>'Catatan pengembalian', 'not_handed_over'=>'Konfirmasi serah terima',
            'photos'=>'Foto', 'photos.*'=>'Foto unit', 'photos.*.*'=>'Foto',
            'category'=>'Kategori', 'model'=>'Peralatan', 'status'=>'Status', 'search'=>'Pencarian',
            'period'=>'Dasar tanggal', 'from'=>'Tanggal mulai', 'until'=>'Tanggal akhir',
            'borrower'=>'Jenis peminjam', 'progress'=>'Status peminjaman', 'late'=>'Keterlambatan',
        ])->validate();
    }
}
