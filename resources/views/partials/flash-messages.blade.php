@php
    $successMessage = session('success') ?: (request()->boolean('updated') ? 'Profile updated successfully.' : null);
@endphp
@if($successMessage)
    <div class="alert success flash-alert" role="status" aria-live="polite">{{ $successMessage }}</div>
@endif
@if(session('error'))
    <div class="alert error flash-alert" role="alert">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert error flash-alert" role="alert">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif
