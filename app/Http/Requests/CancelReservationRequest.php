<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reservation_id' => ['required','integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'reservation_id.required' => 'ไม่พบรหัสการจอง',
            'reservation_id.integer' => 'รหัสการจองไม่ถูกต้อง',
        ];
    }
}
