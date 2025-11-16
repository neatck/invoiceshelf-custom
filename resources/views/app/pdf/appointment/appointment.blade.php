<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Appointment</title>
  <style>
    body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 12px; color: #111827; }
    .header { display:flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
    .title { font-size: 20px; font-weight: 700; }
    .muted { color: #6b7280; }
    .section { margin-top: 16px; }
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 8px; border: 1px solid #e5e7eb; text-align: left; }
  </style>
</head>
<body>
  <div class="header">
    <div class="title">Appointment</div>
    <div class="muted">{{ company()->name ?? config('app.name') }}</div>
  </div>

  <div class="section">
    <table class="table">
      <tbody>
        <tr>
          <th>Title</th>
          <td>{{ $appointment->title }}</td>
        </tr>
        <tr>
          <th>Date & Time</th>
          <td>{{ optional($appointment->appointment_date)->format('M d, Y h:i A') }}</td>
        </tr>
        <tr>
          <th>Duration</th>
          <td>{{ $appointment->duration_minutes }} minutes</td>
        </tr>
        <tr>
          <th>Status</th>
          <td>{{ ucfirst($appointment->status) }}</td>
        </tr>
        <tr>
          <th>Customer</th>
          <td>{{ optional($appointment->customer)->name }}</td>
        </tr>
        <tr>
          <th>Patient</th>
          <td>{{ $appointment->patient_name ?: '-' }}</td>
        </tr>
        <tr>
          <th>Notes</th>
          <td>{{ $appointment->notes ?: '-' }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</body>
</html>
