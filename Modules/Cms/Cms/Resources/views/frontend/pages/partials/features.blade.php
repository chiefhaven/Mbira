@php
    $feature = [];
    if(
        isset($page_meta['feature']) && 
        isset($page_meta['feature']['meta_value']) &&
        !empty($page_meta['feature']['meta_value'])
    ) {
        $feature = json_decode($page_meta['feature']['meta_value'], true);
    }
    @endphp
@if(!empty($feature))
    <div class="block-1 space-between-blocks" data-sticky-container="" id="features">
        <div class="container">
            <div class="row justify-content-center flex-column-reverse flex-lg-row px-2 mx-auto" id="block__container">
                <div class="col-lg-4 col-xl-6 mb-5 pe-lg-5">
                    <div sticky="" data-margin-top="30px">
                        <h1 class="block__title--big mb-3">
                            {{$feature['title'] ?? ''}}
                        </h1>
                        <p class="block__paragraph--big mb-5">
                            {!!$feature['description'] ?? ''!!}
                        </p>
                    </div>
                </div>
                @if(!empty($feature['content']))
                    <div class="col-lg-8 col-xl-6 gx-xxl-5">
                        <div class="row">
                            @foreach($feature['content'] as $content)
                                @if(!empty($content['icon']) && !empty($content['title']) && !empty($content['description']))
                                    <div class="col-md-6 mb-2-1rem">
                                        <div class="card-1">
                                            <span class="fr-icon">
                                                <i class="{{$content['icon']}} fa-lg"></i>
                                            </span>
                                            <h3 class="card-1__title mb-2">
                                                {{$content['title'] ?? ''}}
                                            </h3>
                                            <p class="card-1__paragraph">
                                                {{$content['description'] ?? ''}}
                                            </p>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif