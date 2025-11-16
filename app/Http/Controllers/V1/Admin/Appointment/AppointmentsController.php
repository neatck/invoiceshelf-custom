<?php

namespace App\Http\Controllers\V1\Admin\Appointment;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Customer;
use Illuminate\Http\Request;

class AppointmentsController extends Controller
{
    /**
     * Display a listing of appointments.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Appointment::class);

        $limit = $request->has('limit') ? $request->limit : 10;

        $appointments = Appointment::with(['customer', 'creator'])
            ->whereCompany()
            ->applyFilters($request->all())
            ->latest('appointment_date')
            ->paginateData($limit);

        return AppointmentResource::collection($appointments);
    }

    /**
     * Store a newly created appointment.
     */
    public function store(AppointmentRequest $request)
    {
        $this->authorize('create', Appointment::class);

        $appointment = Appointment::create($request->validated());

        return new AppointmentResource($appointment->load(['customer', 'creator']));
    }

    /**
     * Display the specified appointment.
     */
    public function show(Appointment $appointment)
    {
        $this->authorize('view', $appointment);

        return new AppointmentResource($appointment->load(['customer', 'creator']));
    }

    /**
     * Update the specified appointment.
     */
    public function update(AppointmentRequest $request, Appointment $appointment)
    {
        $this->authorize('update', $appointment);

        $appointment->update($request->validated());

        return new AppointmentResource($appointment->load(['customer', 'creator']));
    }

    /**
     * Remove the specified appointment.
     */
    public function destroy(Appointment $appointment)
    {
        $this->authorize('delete', $appointment);

        $appointment->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Get available time slots for a given date.
     */
    public function getAvailableSlots(Request $request)
    {
        $companyId = $request->company_id ?? $request->header('company');
        
        $request->merge(['company_id' => $companyId]);
        
        $request->validate([
            'date' => 'required|date',
            'company_id' => 'required|exists:companies,id',
            'exclude_appointment_id' => 'nullable|exists:appointments,id',
        ]);

        $slots = Appointment::getAvailableTimeSlots(
            $request->date,
            $companyId,
            $request->exclude_appointment_id
        );

        return response()->json([
            'slots' => $slots,
        ]);
    }

    /**
     * Update appointment status.
     */
    public function updateStatus(Request $request, Appointment $appointment)
    {
        $this->authorize('update', $appointment);

        $request->validate([
            'status' => 'required|in:scheduled,confirmed,completed,cancelled,no_show',
            'reason' => 'nullable|string',
        ]);

        switch ($request->status) {
            case 'completed':
                $appointment->markAsCompleted();
                break;
            case 'confirmed':
                $appointment->markAsConfirmed();
                break;
            case 'cancelled':
                $appointment->cancel($request->reason);
                break;
            default:
                $appointment->update(['status' => $request->status]);
        }

        return new AppointmentResource($appointment->fresh(['customer', 'creator']));
    }

    /**
     * Get dashboard appointment statistics.
     */
    public function getDashboardStats(Request $request)
    {
        $this->authorize('viewAny', Appointment::class);

        $companyId = $request->header('company');

        $stats = [
            'today' => Appointment::whereCompany($companyId)
                ->today()
                ->count(),
            'this_week' => Appointment::whereCompany($companyId)
                ->thisWeek()
                ->count(),
            'upcoming' => Appointment::whereCompany($companyId)
                ->upcoming()
                ->count(),
            'completed_this_month' => Appointment::whereCompany($companyId)
                ->where('status', 'completed')
                ->whereMonth('appointment_date', now()->month)
                ->whereYear('appointment_date', now()->year)
                ->count(),
        ];

        return response()->json($stats);
    }
}
