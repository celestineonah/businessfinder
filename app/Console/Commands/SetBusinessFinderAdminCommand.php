<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetBusinessFinderAdminCommand extends Command
{
    protected $signature = 'businessfinder:set-admin {email : Existing BusinessFinder user email} {--revoke : Remove administrator access}';
    protected $description = 'Grant or revoke BusinessFinder administrator access for an existing user.';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = DB::table('users')->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null) {
            $this->error('No existing user was found with that email. Register/login first, then run this command again.');
            return self::FAILURE;
        }

        $isAdmin = ! (bool) $this->option('revoke');
        DB::table('users')->where('id', $user->id)->update([
            'is_admin' => $isAdmin,
            'updated_at' => now(),
        ]);

        $this->info(($isAdmin ? 'Administrator access granted to ' : 'Administrator access revoked from ') . $user->email);
        return self::SUCCESS;
    }
}
