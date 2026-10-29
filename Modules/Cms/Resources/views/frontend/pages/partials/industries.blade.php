@php
    $industry = [];
    if (
        isset($page_meta['industry']) &&
        isset($page_meta['industry']['meta_value']) &&
        !empty($page_meta['industry']['meta_value'])
    ) {
        $industry = json_decode($page_meta['industry']['meta_value'], true);
    }
@endphp
@if(!empty($industry))
    <section class="block-2 space-between-blocks" aria-labelledby="industries-title">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-5">
                    <p class="cms-eyebrow">Made for the way you work</p>
                    <h2 class="block__title--big mb-4" id="industries-title">
                        {{$industry['title'] ?? ''}}
                    </h2>
                    <div class="block__paragraph mb-4">
                        {!!$industry['description'] ?? ''!!}
                    </div>
                    <a href="{{ $industry_btn['link'] }}" class="btn btn-primary">
                        {{$industry_btn['text']}}
                        <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i>
                    </a>
                </div>
                @if(!empty($industry['content']))
                    <div class="col-lg-7">
                        <div class="cms-industries-grid">
                            @foreach($industry['content'] as $content)
                                @if(!empty($content['icon']) && !empty($content['title']) && !empty($content['description']))
                                    <article class="cms-industry-card">
                                        <span class="fr-icon">
                                            <i class="{{ $content['icon'] }}" aria-hidden="true"></i>
                                        </span>
                                        <h3 class="mb-2">{{ $content['title'] }}</h3>
                                        <p>{{ $content['description'] }}</p>
                                    </article>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endif
