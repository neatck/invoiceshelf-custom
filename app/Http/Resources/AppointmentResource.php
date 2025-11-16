<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'company_id' => $this->company_id,
            'creator_id' => $this->creator_id,
            'title' => $this->title,
            'description' => $this->description,
            'appointment_date' => $this->appointment_date,
            'formatted_appointment_date' => $this->formatted_appointment_date,
            'formatted_appointment_time' => $this->formatted_appointment_time,
            'duration_minutes' => $this->duration_minutes,
            'status' => $this->status,
            'status_badge_color' => $this->status_badge_color,
            'type' => $this->type,
            'patient_name' => $this->patient_name,
            'patient_phone' => $this->patient_phone,
            'patient_email' => $this->patient_email,
            'chief_complaint' => $this->chief_complaint,
            'notes' => $this->notes,
            'preparation_instructions' => $this->preparation_instructions,
            'send_reminder' => $this->send_reminder,
            'reminder_hours_before' => $this->reminder_hours_before,
            'reminder_sent_at' => $this->reminder_sent_at,
            'is_past_due' => $this->is_past_due,
            'can_be_modified' => $this->canBeModified(),
            'end_time' => $this->getEndTime(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'customer' => $this->when($this->relationLoaded('customer'), function () {
                return new CustomerResource($this->customer);
            }),
            'creator' => $this->when($this->relationLoaded('creator'), function () {
                return new UserResource($this->creator);
            }),
        ];
    }
}
