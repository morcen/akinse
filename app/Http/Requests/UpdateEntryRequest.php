<?php

namespace App\Http\Requests;

use App\Models\Enums\EntryTypesEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required_without:category_name', 'nullable', 'exists:categories,id'],
            'category_name' => ['required_without:category_id', 'nullable', 'string', 'max:255', 'min:1'],
            'type' => ['sometimes', 'required', Rule::enum(EntryTypesEnum::class)],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999.99'],
            'date' => ['sometimes', 'required', 'date'],
            'description' => ['nullable', 'string', 'max:65535'],
        ];
    }
}

