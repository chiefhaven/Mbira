<div class="alert alert-{{ $type }} alert-dismissable" role="alert">
    {{ $slot }}
    <button class="btn btn-xs btn-{{ $type }}" type="button" data-dismiss="alert" aria-label="Close">
        <i class="fa fa-times"></i>
    </button>
</div>
