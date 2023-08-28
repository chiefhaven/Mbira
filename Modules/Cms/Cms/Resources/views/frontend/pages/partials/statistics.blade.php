@if(!empty($statistics))
    <div class="block-38 space-between-blocks">
        <div class="container">
            <!-- HEADER -->
            <div class="col-lg-9 mx-auto text-center px-0 mb-5">
                <h1 class="block__title--big mb-3">
                    {{$statistics['tagline'] ?? ''}}
                </h1>
                <p class="block__paragraph--big mb-0 mx-3">
                    {{$statistics['description'] ?? ''}}
                </p>
            </div>
            <ul class="stats row list-unstyled text-center mx-auto p-4 p-lg-5">
                @if(isset($statistics['content']) && !empty($statistics['content']))
                    @foreach($statistics['content'] as $stats)
                        <li class="stats__li col-6 col-lg-3 p-0">
                            <span class="stats__number">
                                {{$stats['stats'] ?? ''}}
                            </span>
                            <p class="stats__text">
                                {{$stats['title'] ?? ''}}
                            </p>
                        </li>
                    @endforeach
                @endif
            </ul>
        </div>
    </div>
@endif