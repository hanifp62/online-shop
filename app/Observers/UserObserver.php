<?php

namespace App\Observers;

use App\Models\User;
use App\Models\UserInfoBackup;

class UserObserver
{
    /**
     * Pengganti trigger dump `after_user_info_insert`.
     * Setiap User baru diduplikat ke user_info_backups untuk audit.
     */
    public function created(User $user): void
    {
        UserInfoBackup::create([
            'user_id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'address1' => $user->address1,
            'address2' => $user->address2,
        ]);
    }

    /**
     * Setiap kali data profil diperbarui via /akun, arsipkan snapshot ke audit.
     */
    public function updated(User $user): void
    {
        if ($user->wasChanged(['first_name', 'last_name', 'email', 'mobile', 'address1', 'address2', 'name'])) {
            UserInfoBackup::create([
                'user_id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'address1' => $user->address1,
                'address2' => $user->address2,
            ]);
        }
    }
}
