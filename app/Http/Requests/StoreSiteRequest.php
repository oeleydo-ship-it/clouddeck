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

    /**
     * Platform arrived after the API did. Defaulting it keeps every existing caller working
     * and, because required_if reads the resolved value, still demands a repository for the
     * Laravel sites those callers are creating.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['platform' => $this->input('platform', 'laravel')]);
    }

    public function rules(): array
    {
        return [
            'server_id' => ['required', 'uuid', Rule::exists('servers', 'id')->where('user_id', $this->user()->id)->where('status', ServerStatus::Ready->value)],
            // Guarded here as well as by the index, so a domain already on the chosen
            // server comes back as a message on the field instead of a 500 from the
            // database. Trashed rows are ignored: deleting a site frees its domain.
            'domain' => [
                'required', 'lowercase', 'max:253',
                'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/',
                Rule::unique('sites', 'domain')->where(fn ($query) => $query->where('server_id', $this->input('server_id'))->whereNull('deleted_at')),
            ],
            'php_version' => ['required', Rule::in(['8.2', '8.3', '8.4'])],
            'platform' => ['required', Rule::in(['laravel', 'wordpress'])],
            // WordPress is downloaded from wordpress.org rather than cloned, so a repository
            // is required for Laravel and meaningless for WordPress.
            'repository_url' => ['required_if:platform,laravel', 'nullable', 'string', 'max:2048', 'regex:/^(https:\/\/[^\s]+|git@[^\s:]+:[^\s]+)$/'],
            'branch' => ['required_if:platform,laravel', 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            'auto_deploy' => ['sometimes', 'boolean'],
            'zero_downtime' => ['sometimes', 'boolean'],
        ];
    }
}
