<?php

namespace App\Http\Requests;

use App\Rules\Phone;

use App\Services\AppointmentSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AppointmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lastName' => ['required', 'string', 'min:2', 'max:60'],
            'firstName' => ['required', 'string', 'min:2', 'max:60'],
            'email' => ['required', 'email', 'max:254'],
            'phone' => ['required', 'string', 'max:30', new Phone],
            'appointmentType' => ['required', Rule::in(['showroom', 'domicile', 'telephone'])],
            'project' => ['nullable', 'string', 'max:200'],
            'message' => ['nullable', 'string', 'max:3000'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', Rule::in(config('appointments.slots'))],
            'submittedAt' => ['sometimes', 'date'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('date') || $validator->errors()->has('time')) return;
            if (!app(AppointmentSchedule::class)->isOpen($this->input('date'), $this->input('time'))) {
                $validator->errors()->add('time', 'Ce créneau est fermé, passé ou hors de la période de réservation.');
            }
        }];
    }
}
