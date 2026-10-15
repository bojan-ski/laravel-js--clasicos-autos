<?php

namespace App\Support;

use Illuminate\Http\Request;

class UserTableQuery
{
    public const int MAX_SEARCH = 100;

    /**
     * @return array{search?: string, page?: int}
     */
    public static function from(Request $request): array
    {
        $query = [];

        $search = $request->query('search');

        if (is_string($search) && trim($search) !== '' && mb_strlen(trim($search)) <= self::MAX_SEARCH) {
            $query['search'] = trim($search);
        }

        $page = filter_var($request->query('page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($page !== false) {
            $query['page'] = $page;
        }

        return $query;
    }
}
