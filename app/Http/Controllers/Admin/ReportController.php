<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReportOutcome;
use App\Exceptions\InvalidReportTransitionException;
use App\Exceptions\InvalidUserStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportIndexRequest;
use App\Http\Requests\Admin\ResolveReportRequest;
use App\Models\MessageReport;
use App\Services\MessageReportService;
use App\Support\ReportQueueQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ReportController extends Controller
{
    public function index(ReportIndexRequest $request, MessageReportService $reports): View
    {
        return view('pages.admin.reports.index', [
            'reports' => $reports->paginate($request->status()),
            'status' => $request->status(),
            'returnQuery' => ReportQueueQuery::from($request),
        ]);
    }

    public function resolve(ResolveReportRequest $request, MessageReport $report, MessageReportService $reports): RedirectResponse
    {
        $redirect = redirect()->route('admin.reports', ReportQueueQuery::from($request));
        $outcome = $request->outcome();

        try {
            match ($outcome) {
                ReportOutcome::Dismissed => $reports->dismiss($report, $request->user()),
                ReportOutcome::MessageDeleted => $reports->deleteMessage($report, $request->user()),
                ReportOutcome::UserSuspended => $reports->suspendAuthor($report, $request->user(), $request->reason(), $request->days()),
            };
        } catch (InvalidReportTransitionException|InvalidUserStatusTransitionException) {
            return $redirect->with('error', __('app.admin.reports.flash.error'));
        }

        return $redirect->with('success', __("app.admin.reports.flash.{$outcome->value}"));
    }
}
