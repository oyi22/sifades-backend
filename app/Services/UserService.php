<?php
namespace App\Services;

use App\Models\Akun;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class UserService
{
    public function getAll(array $filters = [])
    {
        $query = User::with('akun');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('nama_lengkap', 'like', '%'.$filters['search'].'%')->orWhere('nik', 'like', '%'.$filters['search'].'%');
            });
        }

        if (!empty($filters['jabatan'])) {
            $query->where('jabatan', $filters['jabatan']);
        } 

        return $query->latest()->paginate(10);
    }

    public function getById(int $id): User
    {
        return User::with(['akun', 'enrollmentAktif'])->findOrFail($id);
    }

    public function create(array $data): User
    {
        $user = User::create([
            'nama_lengkap' => $data['nama_lengkap'],
            'nik' => $data['nik'],
            'jenis_kelamin' => $data['jenis_kelamin'],
            'alamat' => $data['alamat'],
            'tempat_lahir'  => $data['tempat_lahir'],
            'tanggal_lahir' => $data['tanggal_lahir'],
            'jabatan' => $data['jabatan'],
            'no_wa' => $data['no_wa'] ?? null, 
        ]);

        Akun::create([
            'user_id'   => $user->id,
            'username'  => $user->generateUsername(),
            'password'  => Hash::make($user->generatePassword()),
            'password_plain' => $user->generatePassword(),
            'is_active' => true,
        ]);

        return $user->load('akun');
    }

    public function update(int $id, array $data): User
    {
        $user = User::findOrFail($id);
        $user->update($data);
 
        if ($user->akun) {
            $user->akun->update([
                'username' => $user->generateUsername(),
                'password' => Hash::make($user->generatePassword()),
            ]);
        }

        return $user->load('akun');
    }

    public function delete(int $id): void
    {
        $user = User::findOrFail($id);
        $user->akun?->delete();
        $user->delete();
    }

    public function toggleStatus(int $id): User
    {
        $user = User::with('akun')->findOrFail($id);
        if ($user->akun){
            $user->akun->update([
                'is_active' => !$user->akun->is_active
            ]);
        }
        return $user->load('akun');
    }

    public function getAkunInfo(int $id): array
    {
        $user = User::findOrFail($id);
        return [
            'username' => $user->generateUsername(),
            'password_plain'  => $user->generatePassword(),
            'catatan' => 'Password = tempat lahir + tanggal lahir (lowercase, tanpa spasi)',
        ];
    }

    public function uploadFotoProfile(int $id, UploadedFile $file): User{
        $user = User::findOrFail($id);

        if($user->foto_profile){
            Storage::disk('public')->delete($user->foto_profile);
        }

        $path = $file->store("foto_profile", 'public');
        $user->update(['foto_profile' => $path]);

        return $user->load('akun');
    }

    public function deleteFotoProfile(int $id): User{
        $user = User::findOrFail($id);

        if($user->foto_profile){
            Storage::disk('public')->delete($user->foto_profile);
            $user->update(['foto_profile' => null]);
        }

        return $user->load('akun');
    }

    public function uploadFTSelf(int $userId, UploadedFile $file): User{
        return $this->uploadFotoProfile($userId, $file);
    }
}