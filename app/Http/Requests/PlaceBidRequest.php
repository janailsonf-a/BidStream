<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlaceBidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'O valor do lance é obrigatório.',
            'amount.numeric' => 'O valor do lance deve ser numérico.',
            'amount.min' => 'O valor do lance deve ser maior que zero.',
        ];
    }
}