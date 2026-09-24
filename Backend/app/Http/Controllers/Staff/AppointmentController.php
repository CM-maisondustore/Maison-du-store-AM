<?php
namespace App\Http\Controllers\Staff;
use App\Models\{Appointment, User};
use App\Services\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\{Rule, ValidationException};
class AppointmentController
{
    public function index(Request $r)
    {
        $q = Appointment::with('agent')->orderByDesc('date')->orderBy('time');
        if (!$r->user()->isAdmin()) $q->where('agent_id', $r->user()->id);
        return view('staff.appointments', ['appointments' => $q->paginate(25)]);
    }
    public function show(Request $r, Appointment $appointment)
    {
        abort_unless($r->user()->isAdmin() || $appointment->agent_id === $r->user()->id, 403);
        return view('staff.appointment', ['appointment' => $appointment, 'agents' => User::where('role', 'agent')->where('active', true)->get()]);
    }
    public function update(Request $r, Appointment $appointment)
    {
        $data = $r->validate(['date' => 'required|date_format:Y-m-d', 'time' => ['required', Rule::in(config('appointments.slots'))], 'status' => 'required|in:pending,confirmed,completed,cancelled', 'agent_id' => ['nullable', Rule::exists('users', 'id')->where('active', true)->where('role', 'agent')], 'internal_notes' => 'nullable|string|max:10000']);
        try { $appointment->update($data); } catch (UniqueConstraintViolationException $e) { throw ValidationException::withMessages(['time' => 'Ce créneau est déjà réservé.']); }
        Audit::record('rendez_vous_modifie', $appointment);
        return back()->with('success', 'Rendez-vous enregistré. Une annulation libère le créneau.');
    }
}
