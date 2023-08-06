<?php

namespace Dashed\DashedEcommerceCore\Requests\Frontend;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;
use Dashed\DashedCore\Models\Customsetting;

class StartTransactionRequest extends FormRequest
{
    public function rules()
    {
        return [
            'general_condition' => [
                'required',
            ],
            'first_name' => [
                'max:255',
            ],
            'last_name' => [
                'required',
                'max:255',
            ],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
            ],
            'password' => [
                Rule::requiredIf(Customsetting::get('checkout_account') == 'required' && ! Auth::check()),
                'nullable',
                'min:6',
                'max:255',
                'confirmed',
            ],
            'street' => [
                'required',
                'max:255',
            ],
            'house_nr' => [
                'required',
                'max:255',
            ],
            'zip_code' => [
                'required',
                'max:10',
            ],
            'city' => [
                'required',
                'max:255',
            ],
            'country' => [
                'required',
                'max:255',
            ],
            'phone_number' => [
                Rule::requiredIf(Customsetting::get('checkout_form_phone_number_delivery_address') == 'required'),
                'max:255',
            ],
            'company_name' => [
                Rule::requiredIf(Customsetting::get('checkout_form_company_name') == 'required'),
                'max:255',
            ],
            'btw_id' => [
                'max:255',
            ],
            'note' => [
                'max:1500',
            ],
            'invoice_street' => [
                'max:255',
            ],
            'invoice_house_nr' => [
                Rule::requiredIf(! empty(request()->get('invoice_street'))),
                'max:255',
            ],
            'invoice_zip_code' => [
                Rule::requiredIf(! empty(request()->get('invoice_street'))),
                'max:255',
            ],
            'invoice_city' => [
                Rule::requiredIf(! empty(request()->get('invoice_street'))),
                'max:255',
            ],
            'invoice_country' => [
                Rule::requiredIf(! empty(request()->get('invoice_street'))),
                'max:255',
            ],
//            'payment_method' => [
//                'required',
//            ],
            'shipping_method' => [
                'required',
                'max:255',
            ],
        ];
    }
}
