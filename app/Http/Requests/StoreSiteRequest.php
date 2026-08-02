<?php

namespace App\Http\Requests;

use App\Enums\ServerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'server_id' => ['required', 'uuid', Rule::exists('servers', 'id')->where('user_id', $this->user()->id)->where('status', ServerStatus::Ready->value)],
            'domain' => ['required', 'lowercase', 'max:253', 'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/'],
            'php_version' => ['required', Rule::in(['8.2', '8.3', '8.4'])],
            'repository_url' => ['required', 'string', 'max:2048', 'regex:/^(https:\/\/[^\s]+|git@[^\s:]+:[^\s]+)$/'],
            'branch' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            'auto_deploy' => ['sometimes', 'boolean'],
            'zero_downtime' => ['sometimes', 'boolean'],
        ];
    }
}
