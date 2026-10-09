<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\Intl\Locales;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $currencyCanChange = ! $user->expenses()->exists()
            && ! $user->invoices()->exists()
            && ! $user->budgets()->exists();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'country_code' => ['sometimes', 'required', 'string', 'size:2', Rule::in(array_keys(Countries::getNames('en')))],
            'currency_code' => [
                'sometimes',
                'required',
                'string',
                'size:3',
                Rule::in($currencyCanChange ? Currencies::getCurrencyCodes() : [$user->currency_code]),
            ],
            'locale' => ['sometimes', 'required', 'string', 'max:24', Rule::in(array_keys(Locales::getNames('en')))],
            'timezone' => ['sometimes', 'required', 'string', 'timezone'],
        ];
    }
}
