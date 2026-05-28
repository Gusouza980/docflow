<?php

namespace App\Http\Requests\Web;

use App\Http\Requests\Web\StoreCalendarEventRequest;

class UpdateCalendarEventRequest extends StoreCalendarEventRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('event')) ?? false;
    }
}
