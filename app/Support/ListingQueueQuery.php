<?php

namespace App\Support;

use App\Enums\ListingQueueFilter;
use Illuminate\Http\Request;

class ListingQueueQuery
{
    public const int MAX_SEARCH = 100;

    public static function returnsToQueue(Request $request): bool
    {
        return $request->query('return') === 'queue';
    }

    /**
     * @return array{status?: string, search?: string, page?: int}
     */
    public static function from(Request $request): array
    {
        $query = [];

        $status = $request->query('status');

        if (is_string($status) && ListingQueueFilter::tryFrom($status) !== null) {
            $query['status'] = $status;
        }

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
