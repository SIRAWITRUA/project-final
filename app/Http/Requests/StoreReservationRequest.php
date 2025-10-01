<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trip_id' => ['required','integer'],
            'origin_place_id' => ['required','integer','different:destination_place_id'],
            'destination_place_id' => ['required','integer','different:origin_place_id'],
            'seats' => ['nullable','integer','min:1','max:4'],
        ];
    }

    public function messages(): array
    {
        return [
            'trip_id.required' => 'กรุณาเลือกรอบรถ',
            'trip_id.integer' => 'รูปแบบรอบรถไม่ถูกต้อง',
            'origin_place_id.required' => 'กรุณาเลือกจุดขึ้น',
            'destination_place_id.required' => 'กรุณาเลือกจุดลง',
            'origin_place_id.different' => 'จุดขึ้นและจุดลงต้องไม่เป็นจุดเดียวกัน',
            'destination_place_id.different' => 'จุดขึ้นและจุดลงต้องไม่เป็นจุดเดียวกัน',
            'seats.integer' => 'จำนวนที่นั่งไม่ถูกต้อง',
            'seats.min' => 'จำนวนที่นั่งอย่างน้อย 1',
            'seats.max' => 'จำนวนที่นั่งสูงสุด 4 ต่อรอบ',
        ];
    }
}
