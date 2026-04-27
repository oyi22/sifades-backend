<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class User extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'nama_lengkap',
        'nik',
        'jenis_kelamin',
        'alamat',
        'tempat_lahir',
        'tanggal_lahir',
        'jabatan',
        'no_wa', 
        'foto_profile'
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date', 
        ];
    }

    protected $appends = ['foto_profile_url'];
 
    public function generateUsername(): string
    { 
        return strtolower(str_replace(' ', '', $this->nama_lengkap));
    }

    public function generatePassword(): string
    { 
        $kota    = strtolower(str_replace(' ', '', $this->tempat_lahir));
        $tanggal = Carbon::parse($this->tanggal_lahir)->format('dmY');
        return $kota . $tanggal;
    }
 
    public function akun()
    {
        return $this->hasOne(Akun::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function enrollmentAktif()
    {
        return $this->hasOne(Enrollment::class)->where('is_verified', true)->latest();
    }

    public function absensis()
    {
        return $this->hasMany(Absensi::class);
    }

    public function izins()
    {
        return $this->hasMany(Izin::class);
    }

    public function absensiHariIni()
    {
        return $this->hasOne(Absensi::class)->whereDate('tanggal', today());
    }

    public function izinHariIni()
    {
        return $this->hasOne(Izin::class)->whereDate('tanggal', today());
    }
 
    public function sudahEnrollment(): bool
    {
        return $this->enrollments()->where('is_verified', true)->exists();
    }

    public function gagalLiveness(): int
    {
        return $this->enrollmentAktif?->gagal_liveness ?? 0;
    }

    public function getFotoProfileUrlAttribute(): ?string{
        if(!$this->foto_profile) return null;
        return asset('storage/' . $this->foto_profile);
    }

}