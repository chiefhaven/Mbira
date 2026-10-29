@if (session('status'))
    <input type="hidden" id="status_span" data-status="{{ session('status.success') }}" data-msg="{{ session('status.msg') }}">
@endif

{{-- Form errors --}}
@if ($errors->any())
    @foreach ($errors->all() as $error)
        @component('accounting::components.alert', ['type' => 'danger'])
            {{ $error }}
        @endcomponent
    @endforeach
@endif

{{-- General error --}}
@if (session('error'))
    @component('accounting::components.alert', ['type' => 'danger'])
        {{ session('error') }}
    @endcomponent
@endif

{{-- Warning --}}
@if (session('warning'))
    @component('accounting::components.alert', ['type' => 'warning'])
        {{ session('warning') }}
    @endcomponent
@endif
