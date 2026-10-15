<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait InsertsSessionRows
{
    private function insertSessionRow(?User $user): string
    {
        $id = Str::random(40);

        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user?->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('a:0:{}'),
            'last_activity' => now()->getTimestamp(),
        ]);

        return $id;
    }

    /**
     * @return list<string>
     */
    private function sessionRowIds(): array
    {
        $ids = DB::table('sessions')->pluck('id')->all();
        sort($ids);

        return $ids;
    }
}
