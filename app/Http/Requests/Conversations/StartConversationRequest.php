<?php

namespace App\Http\Requests\Conversations;

use App\Models\CarListing;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class StartConversationRequest extends MessageRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('message', $this->listing());
    }

    protected function getRedirectUrl(): string
    {
        return route('conversations.create', $this->listing());
    }

    private function listing(): CarListing
    {
        return $this->route('listing');
    }
}
