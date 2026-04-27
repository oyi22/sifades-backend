<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KantorDesa extends Model
{
    protected $table = 'kantor_desas';

    protected $fillable = [
        'nama_kantor',
        'latitude',
        'longitude',
        'radius_meter',
        'alamat',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meter' => 'integer',
            'is_active' => 'boolean',
        ];
    }
 
    public static function aktif(): ?self
    {
        return static::where('is_active', true)->latest()->first();
    }
 
    public function hitungJarak(float $lat, float $lng): int
    {
        $earthRadius = 6371000; // meter

        $dLat = deg2rad($lat - $this->latitude);
        $dLng = deg2rad($lng - $this->longitude);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($this->latitude))
           * cos(deg2rad($lat))
           * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round($earthRadius * $c);
    }
 
    public function dalamRadius(float $lat, float $lng): bool
    {
        return $this->hitungJarak($lat, $lng) <= $this->radius_meter;
    }
}