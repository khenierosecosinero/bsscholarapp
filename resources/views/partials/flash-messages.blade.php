@if(session('success'))
    <div class="alert success flash-alert">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert error flash-alert">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert error flash-alert">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif
