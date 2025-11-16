export default function () {
  return {
    customer_id: null,
    title: '',
    description: '',
    appointment_date: null,
    duration_minutes: 30,
    status: 'scheduled',
    type: 'consultation',
    patient_name: '',
    patient_phone: '',
    patient_email: '',
    chief_complaint: '',
    notes: '',
    preparation_instructions: '',
    send_reminder: true,
    reminder_hours_before: 24,
  }
}
