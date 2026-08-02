<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Support\AccessControl;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    private function assertRoleAccess(string $action = 'view'): void
    {
        $user = auth()->user();
        if (!$user || !AccessControl::has($user, 'hak-akses', $action)) {
            abort(403, 'Forbidden');
        }
    }

    public function index(Request $request)
    {
        $this->assertRoleAccess();

        $page      = (int) $request->input('page', 1);
        $limit     = (int) $request->input('limit', 10);
        $search    = $request->input('search');
        $sortBy    = in_array($request->input('sort_by'), ['nama', 'created_at']) ? $request->input('sort_by') : 'created_at';
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = Role::query();

        if ($search) {
            $query->where('nama', 'like', '%' . $search . '%');
        }

        $total      = $query->count();
        $roles      = $query->orderBy($sortBy, $sortOrder)
                            ->offset(($page - 1) * $limit)
                            ->limit($limit)
                            ->get();

        return response()->json([
            'success'    => true,
            'data'       => $roles,
            'pagination' => [
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    public function show($id)
    {
        $this->assertRoleAccess();

        $role = Role::find($id);
        if (!$role) {
            return response()->json(['success' => false, 'message' => 'Role tidak ditemukan'], 404);
        }

        return response()->json(['success' => true, 'data' => $role]);
    }

    public function store(Request $request)
    {
        $this->assertRoleAccess('create');

        $request->validate([
            'nama'           => 'required|string',
            'is_super_admin' => 'required|boolean',
            'akses'          => 'required|array',
        ]);

        $role = Role::create([
            'id'             => Str::uuid(),
            'nama'           => $request->nama,
            'is_super_admin' => $request->is_super_admin,
            'akses'          => $request->akses,
        ]);

        AuditLogger::record(
            auth()->user(),
            'role_created',
            'role',
            $role->id,
            $role->nama,
            'Hak akses dibuat.',
            ['is_super_admin' => $role->is_super_admin]
        );

        return response()->json([
            'success' => true,
            'message' => 'Hak akses berhasil dibuat',
            'data'    => $role,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $this->assertRoleAccess('update');

        $role = Role::find($id);
        if (!$role) {
            return response()->json(['success' => false, 'message' => 'Role tidak ditemukan'], 404);
        }

        $request->validate([
            'nama'           => 'required|string',
            'is_super_admin' => 'required|boolean',
            'akses'          => 'required|array',
        ]);

        $role->update([
            'nama'           => $request->nama,
            'is_super_admin' => $request->is_super_admin,
            'akses'          => $request->akses,
        ]);

        AuditLogger::record(
            auth()->user(),
            'role_updated',
            'role',
            $role->id,
            $role->nama,
            'Hak akses diperbarui.',
            ['is_super_admin' => $role->is_super_admin]
        );

        return response()->json([
            'success' => true,
            'message' => 'Hak akses berhasil diperbarui',
            'data'    => $role,
        ]);
    }

    public function destroy($id)
    {
        $this->assertRoleAccess('delete');

        $role = Role::find($id);
        if (!$role) {
            return response()->json(['success' => false, 'message' => 'Role tidak ditemukan'], 404);
        }

        $roleName = $role->nama;
        $roleId = $role->id;
        $role->delete();

        AuditLogger::record(
            auth()->user(),
            'role_deleted',
            'role',
            $roleId,
            $roleName,
            'Hak akses dihapus.'
        );

        return response()->json(['success' => true, 'message' => 'Hak akses berhasil dihapus']);
    }
}
