<div class="container-fluid" style="overflow-y: hidden; overflow-x: auto; width: 100%">
    <div class="box box-primary">

        @unless(empty($title) && empty($header))
            <div class="box-header">
                @unless(empty($title))
                    <h3 class="box-title">{{ $title }}</h3>
                @endunless

                {{-- To add a button on the right add box-tools class to the div in your header --}}
                @unless(empty($header))
                    {{ $header }}
                @endunless
            </div>
        @endunless

        <div class="box-body">
            {{ $body }}
        </div>
        <!-- /.box-body -->

        @unless(empty($footer))
            <div class="box-footer">
                {{ $footer }}
            </div>
        @endunless
    </div>

</div>
