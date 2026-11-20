<?php

declare(strict_types=1);

namespace App\Http\Requests\API\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreConversationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the recipient email before validation.
     */
    protected function prepareForValidation(): void
    {
        $email = $this->input('recipient_email');

        if (is_string($email)) {
            $this->merge(['recipient_email' => Str::lower(trim($email))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Exactly one of recipient_id or recipient_email is required.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'recipient_id' => [
                'required_without:recipient_email',
                'prohibits:recipient_email',
                'integer',
            ],
            'recipient_email' => [
                'required_without:recipient_id',
                'prohibits:recipient_id',
                'email',
            ],
        ];
    }
}
