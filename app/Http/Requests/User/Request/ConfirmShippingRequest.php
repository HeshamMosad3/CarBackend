<?php

namespace App\Http\Requests\User\Request;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class ConfirmShippingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    public function rules(): array
    {
        return [
            'requestId'       => ['required', 'integer'],
            'responseId'  => ['required', 'integer'],
            'vendorId'  => ['required', 'integer'],
            'idNumberUser'       => ['required', 'string', 'max:20'],
            'cityOriginDimensions'       => ['required', 'string', 'max:255'],
            'addressOriginDimensions'       => ['required', 'string', 'max:255'],
            'phoneOriginDimensions'       => ['required', 'string', 'max:20'],
            // Delivery point on the map. Optional so older app builds that don't send it keep working.
            'nameDestination'     => ['nullable', 'string', 'max:255'],
            'latDestination'      => ['nullable', 'numeric', 'between:-90,90'],
            'lngDestination'      => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'requestId.required'      => 'الطلب مطلوبة.',
            'requestId.integer'      => 'الطلب يجب ان يكون رقم صحيح.',
            'responseId.required'      => 'رد الشركة مطلوبة.',
            'responseId.integer'      => 'رد الشركة يجب ان يكون رقم صحيح.',
            'idNumberUser.required'      => 'رقم الهوية مطلوبة.',
            'idNumberUser.string'      => 'رقم الهوية يجب ان يكون نص.',
            'idNumberUser.max'      => 'رقم الهوية يجب ان يكون اقل من 20 حرف.',
            'cityOriginDimensions.required'      => 'المدينة مطلوبة.',
            'cityOriginDimensions.string'      => 'المدينة يجب ان يكون نص.',
            'cityOriginDimensions.max'      => 'المدينة يجب ان يكون اقل من 255 حرف.',
            'addressOriginDimensions.required'      => 'العنوان مطلوبة.',
            'addressOriginDimensions.string'      => 'العنوان يجب ان يكون نص.',
            'addressOriginDimensions.max'      => 'العنوان يجب ان يكون اقل من 255 حرف.',
            'phoneOriginDimensions.required'      => 'رقم الهاتف مطلوبة.',
            'phoneOriginDimensions.string'      => 'رقم الهاتف يجب ان يكون نص.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $response = response()->json($validator->errors(), 422);
        throw (new ValidationException($validator, $response))
            ->errorBag($this->errorBag)
            ->redirectTo($this->getRedirectUrl());
    }
}
