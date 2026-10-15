<?php

namespace App\Http\Controllers\Conversations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversations\StartConversationRequest;
use App\Models\CarListing;
use App\Models\Conversation;
use App\Models\User;
use App\Services\MessagingService;
use App\Support\BackUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    public function index(Request $request, MessagingService $messaging): View
    {
        $viewer = $request->user();
        $audience = $viewer->isAdmin() ? 'admin' : 'member';
        $conversations = $messaging->conversationsFor($viewer);
        $unreadCount = $messaging->unreadCountFor($viewer);

        return view('pages.conversations.index', [
            'heading' => __("app.conversations.index.title.{$audience}"),
            'conversations' => $conversations,
            'liveList' => $viewer->isActiveAppUser(),
            'showsUnreadLine' => ! $viewer->isAdmin(),
            'firstPage' => $conversations->onFirstPage(),
            'unreadCount' => $unreadCount,
            'unreadLine' => $unreadCount > 0 ? __('app.conversations.index.unread', ['count' => $unreadCount]) : '',
            'emptyMessage' => __("app.conversations.index.empty.{$audience}"),
            'browseUrl' => $viewer->isAdmin() ? null : route('listings.index'),
        ]);
    }

    public function create(Request $request, CarListing $listing, MessagingService $messaging, BackUrl $backUrl): View|RedirectResponse
    {
        Gate::authorize('message', $listing);

        $existing = $messaging->existingFor($listing, $request->user());

        if ($existing !== null) {
            return redirect()->route('conversations.show', $existing);
        }

        $listing = $messaging->forNewConversation($listing);

        return $this->page($request->user(), $listing, null, route('conversations.store', $listing), $backUrl->resolve(route('listings.show', $listing)));
    }

    public function store(StartConversationRequest $request, CarListing $listing, MessagingService $messaging): RedirectResponse
    {
        $message = $messaging->start($listing, $request->user(), $request->text());

        return redirect()->route('conversations.show', $message->conversation_id)->withFragment('message-'.$message->id);
    }

    public function show(Request $request, Conversation $conversation, MessagingService $messaging): View
    {
        Gate::authorize('view', $conversation);

        $messaging->markRead($conversation, $request->user());
        $conversation = $messaging->thread($conversation, $request->user());
        $composerAction = Gate::allows('reply', $conversation) ? route('conversations.messages.store', $conversation) : null;

        return $this->page($request->user(), $conversation->listing, $conversation, $composerAction, route('conversations.index'));
    }

    public function read(Request $request, Conversation $conversation, MessagingService $messaging): Response
    {
        Gate::authorize('view', $conversation);

        $messaging->markRead($conversation, $request->user());

        return response()->noContent();
    }

    private function page(User $viewer, CarListing $listing, ?Conversation $conversation, ?string $composerAction, string $backUrl): View
    {
        $sender = $conversation?->sender->username;
        $receiver = $conversation?->receiver->username;
        $counterpart = match (true) {
            $conversation === null => $listing->owner->username,
            $conversation->hasParticipant($viewer) => $conversation->otherParticipant($viewer)->username,
            default => null,
        };

        return view('pages.conversations.show', [
            'heading' => $counterpart !== null
                ? __('app.conversations.heading.with', ['username' => $counterpart])
                : __('app.conversations.heading.between', ['buyer' => $sender, 'seller' => $receiver]),
            'counterpart' => $counterpart ?? __('app.conversations.participants', ['buyer' => $sender, 'seller' => $receiver]),
            'listing' => $listing,
            'conversation' => $conversation,
            'messages' => $conversation?->messages ?? collect(),
            'composerAction' => $composerAction,
            'markReadUrl' => $conversation !== null && $composerAction !== null ? route('conversations.read', $conversation) : null,
            'emptyMessage' => __($conversation === null ? 'app.conversations.empty.new' : 'app.conversations.empty.thread'),
            'backUrl' => $backUrl,
        ]);
    }
}
