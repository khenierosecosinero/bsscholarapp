@php
    $successMessage = session('success') ?: (request()->boolean('updated') ? 'Profile updated successfully.' : null);
    $errorMessage = session('error');
    $warningMessage = session('warning');
    $infoMessage = session('info') ?: session('status');
    $validationErrors = isset($errors) && $errors->any() ? $errors->all() : [];
@endphp

@if($successMessage || $errorMessage || $warningMessage || $infoMessage || $validationErrors !== [])
    <div class="bssa-flash-stack" aria-live="polite" aria-relevant="additions text">
        @if($successMessage)
            @include('partials.bssa-alert', [
                'type' => 'success',
                'title' => 'Success!',
                'message' => $successMessage,
                'dismissible' => true,
                'autoDismiss' => 5000,
            ])
        @endif

        @if($errorMessage)
            @include('partials.bssa-alert', [
                'type' => 'error',
                'title' => 'Error',
                'message' => $errorMessage,
                'dismissible' => true,
                'autoDismiss' => false,
            ])
        @endif

        @if($warningMessage)
            @include('partials.bssa-alert', [
                'type' => 'warning',
                'title' => 'Warning',
                'message' => $warningMessage,
                'dismissible' => true,
                'autoDismiss' => false,
            ])
        @endif

        @if($infoMessage)
            @include('partials.bssa-alert', [
                'type' => 'info',
                'title' => 'Information',
                'message' => $infoMessage,
                'dismissible' => true,
                'autoDismiss' => 5000,
            ])
        @endif

        @if($validationErrors !== [])
            @include('partials.bssa-alert', [
                'type' => 'error',
                'title' => count($validationErrors) > 1 ? 'Please fix these issues' : 'Please fix this issue',
                'message' => null,
                'messages' => $validationErrors,
                'dismissible' => true,
                'autoDismiss' => false,
            ])
        @endif
    </div>
@endif
