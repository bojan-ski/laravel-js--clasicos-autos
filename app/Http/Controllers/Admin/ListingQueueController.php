<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListingQueueRequest;
use App\Services\ListingQueueService;
use App\Support\ListingQueueQuery;
use Illuminate\Contracts\View\View;

class ListingQueueController extends Controller
{
    public function index(ListingQueueRequest $request, ListingQueueService $queue): View
    {
        return view('pages.admin.listings.index', [
            'listings' => $queue->paginate($request->filter(), $request->search()),
            'filter' => $request->filter(),
            'search' => $request->search(),
            'returnQuery' => ListingQueueQuery::from($request),
        ]);
    }
}
