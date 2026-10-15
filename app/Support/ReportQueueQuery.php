<?php

namespace App\Support;

use App\Enums\ReportStatus;
use Illuminate\Http\Request;

class ReportQueueQuery
{
    /**
     * @return array{status?: string, page?: int}
     */
    public static function from(Request $request): array
    {
        $query = [];

        $status = $request->query('status');

        if (is_string($status) && ReportStatus::tryFrom($status) !== null) {
            $query['status'] = $status;
        }

        $page = filter_var($request->query('page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($page !== false) {
            $query['page'] = $page;
        }

        return $query;
    }
}
