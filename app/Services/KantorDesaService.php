<?php
namespace App\Services;

use App\Models\KantorDesa;

class KantorDesaService
{
    public function get(): ?KantorDesa
    {
        return KantorDesa::aktif();
    }

    public function upsert(array $data): KantorDesa
    {
        $kantor = KantorDesa::aktif();

        if ($kantor) {
            $kantor->update($data);
        } else {
            $kantor = KantorDesa::create(array_merge($data, ['is_active' => true]));
        }

        return $kantor;
    }
}