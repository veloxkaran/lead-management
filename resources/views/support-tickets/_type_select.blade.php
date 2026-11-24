{{-- Ticket type picker. No default: whoever raises a ticket chooses what kind it is. --}}
<select name="type" class="form-select {{ $size ?? '' }}" required>
    <option value="" disabled @selected(blank($selected ?? null))>Choose a type…</option>
    @foreach (App\Enums\SupportTicketType::cases() as $type)
        <option value="{{ $type->value }}" @selected(($selected ?? null) === $type->value)>{{ $type->label() }}</option>
    @endforeach
</select>
