<?php

namespace App\Http\Requests\Admin;

class ApproveListingRequest extends ModerateListingRequest
{
    protected function ability(): string
    {
        return 'approve';
    }
}
