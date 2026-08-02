<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['cloud_account_id' => ['required', 'uuid', Rule::exists('cloud_accounts', 'id')->where('user_id', $this->user()->id)], 'ssh_key_id' => ['nullable', 'uuid', Rule::exists('ssh_keys', 'id')->where('user_id', $this->user()->id)], 'name' => ['required', 'string', 'max:100'], 'hostname' => ['required', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/'], 'region' => ['required', 'string', 'max:50'], 'size' => ['required', 'string', 'max:50'], 'image' => ['required', 'string', 'max:100']];
    }
}
