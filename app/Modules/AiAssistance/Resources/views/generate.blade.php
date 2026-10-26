<div class="col-md-8">
    <div class="box box-success box-solid">
        <div class="box-body">
        
        @php
            $class = rand();
        @endphp

        <span id="{{$class}}">{!! nl2br($text) !!}</span>

        <br/>

        <i class="fas fa-copy pull-right cursor-pointer" onclick="copyToClipboard({{$class}})"></i>
        </div>

    </div>

</div>