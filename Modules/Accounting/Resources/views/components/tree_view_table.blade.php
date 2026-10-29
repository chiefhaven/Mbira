{{-- Requires box-shadow.css --}}
<div class="@if (!empty('table_responsive')) table-responsive @endif">
    <table class="table table-bordered-dark tree box box-shadow-effect">
        {{ $slot }}
    </table>
</div>
