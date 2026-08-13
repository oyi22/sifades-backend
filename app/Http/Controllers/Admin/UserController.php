<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use App\Services\RiwayatService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(protected UserService $service) {}

    public function index(Request $request)
    {
        $data = $this->service->getAll($request->only(['jabatan', 'search']));
        return response()->json([
            'success' => true, 
            'data' => $data
        ]);
    }

    public function show(int $id)
    {
        $data = $this->service->getById($id);
        return response()->json([
            'success' => true, 
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nik' => 'required|string|size:16|unique:users,nik',
            'jenis_kelamin' => 'required|in:L,P',
            'alamat' => 'required|string',
            'tempat_lahir' => 'required|string',
            'tanggal_lahir' => 'required|date',
            'jabatan' => 'required|in:sekdes,kaur,pelayanan,karyawan,kamituwo,kades,pj',
            'no_wa' => 'nullable|string|max:15',
        ]);

        $user = $this->service->create($data);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil ditambahkan.',
            'data' => $user,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
        'nama_lengkap' => 'required|string|max:255',
        'nik' =>  [
            'required', 'string', 'size:16', Rule::unique('users', 'nik')->ignore($id)
        ],
        'jenis_kelamin' => 'required|in:L,P',
        'alamat' => 'required|string',
        'tempat_lahir' => 'required|string',
        'tanggal_lahir' => 'required|date',
        'jabatan' => 'required|in:sekdes,kaur,pelayanan,karyawan,kamituwo,kades,pj',
        'no_wa' => 'nullable|string|max:15',
        ]);

        $user = $this->service->update($id, $data);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil diupdate.',
            'data' => $user,
        ]);
    }

    public function destroy(int $id)
    {
        $this->service->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dihapus.',
        ]);
    }

    public function toggleStatus(int $id)
    {
        $user = $this->service->toggleStatus($id);

        return response()->json([
            'success' => true,
            'message' => 'Status keanggotaan diperbarui.',
            'data' => $user,
        ]);
    }

    public function uploadFoto(Request $request, int $id){
        $request->validate([
            'foto' => 'required|image|mimes:jpg,jpeg,png|max:10240',
        ]);

        $user = $this->service->uploadFotoProfile($id, $request->file('foto'));

        return response()->json([
            'success' => true,
            'message' => 'foto profile berhasil diubah',
            'data' => [
                'foto_profile_url' => $user->foto_profile_url,
            ],
        ]);
    }

    public function deleteFoto(int $id){
        $user = $this->service->deleteFotoProfile($id);

        return response()->json([
            'success' => true,
            'message' => 'foto profile berhasil dihapus',
            'data' => [
                'foto_profile_url' => null,
            ],
        ]);
    }

    public function riwayat(Request $request, int $id)
    {
        $query = \App\Models\RiwayatAktivitas::where('user_id', $id)
            ->where('dihapus', false)
            ->orderByDesc('terjadi_pada');

        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }

        $data = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}