<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessMember;

abstract class Controller
{
    /** Keanggotaan user login pada usaha ini (null jika bukan anggota). */
    protected function membership(Business $business): ?BusinessMember
    {
        return $business->memberFor(auth()->id());
    }

    /** Wajib anggota (owner atau staff) untuk akses. */
    protected function authorizeMember(Business $business): BusinessMember
    {
        $m = $this->membership($business);
        abort_if(!$m, 403, 'Akses ditolak.');
        return $m;
    }

    /** Wajib pemilik (owner) untuk aksi sensitif (hapus, kelola staff, setting). */
    protected function authorizeOwner(Business $business): void
    {
        $m = $this->membership($business);
        abort_if(!$m || !$m->isOwner(), 403, 'Hanya pemilik usaha yang bisa melakukan ini.');
    }

    /**
     * Izinkan owner ATAU staff yang punya izin tertentu (mis. can_edit_transactions).
     * Owner selalu lolos; staff hanya lolos kalau kolom izinnya bernilai true.
     */
    protected function authorizeCan(Business $business, string $permission, string $message = 'Kamu tidak punya izin untuk aksi ini.'): void
    {
        $m = $this->membership($business);
        abort_if(!$m, 403, 'Akses ditolak.');
        abort_if(!$m->isOwner() && !$m->{$permission}, 403, $message);
    }
}
