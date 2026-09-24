<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppointmentRequest;
use App\Models\Appointment;
use App\Services\AppointmentSchedule;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AppointmentController
{
    public function store(AppointmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        try {
            $appointment = \Illuminate\Support\Facades\DB::transaction(fn () => Appointment::create([
                'contact_id' => app(\App\Services\ContactArchive::class)->capture($data)->id,
                'reference' => (string) Str::uuid(),
                'date' => $data['date'], 'time' => $data['time'],
                'details' => collect($data)->except(['date', 'time', 'submittedAt'])->all(),
            ]));
        } catch (UniqueConstraintViolationException $exception) {
            // La contrainte SQL protège aussi deux requêtes simultanées.
            throw ValidationException::withMessages(['time' => 'Ce créneau vient d’être réservé. Choisissez une autre heure.']);
        }
        return response()->json(['data' => ['reference' => $appointment->reference, 'status' => 'pending']], 201);
    }

    public function availability(Request $request, AppointmentSchedule $schedule): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        return response()->json(['data' => [
            'date' => $data['date'], 'timezone' => config('app.timezone'),
            'unavailable' => $schedule->unavailable($data['date']),
        ]]);
    }
}
