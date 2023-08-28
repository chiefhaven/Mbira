@if(!empty($testimonials) && count($testimonials) > 0)
    <div class="block-20 space-between-blocks">
        <div class="container">
            <div class="col-lg-9 mx-auto text-center px-0 mb-5">
                <p class="block__paragraph--big">We have happy customers</p>
                <h1 class="block__title--big pb-5">What They Says About Us</h1>
            </div>
            <div class="row px-2 pt-4 mx-xxl-auto" id="block__container">
                @foreach($testimonials as $testimonial)
                    <div class="col-lg-4 mb-1">
                        <div class="testimonial-card-1">
                            <div class="block-20-person">
                                <div class="mb-2">
                                    <img class="block-20-person__avatar" src="{{$testimonial->feature_image_url ?? 'https://ui-avatars.com/api/?name='.$testimonial->title}}"
                                        loading="lazy">
                                </div>
                                <div class="flex-grow-1 d-flex flex-column">
                                    <span class="block-20-person__rating">
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                    </span>
                                    <span class="block-20-person__name my-1">
                                        {{$testimonial->title}}
                                    </span>
                                    <!-- <span class="block-20-person__info">
                                        Designation
                                    </span> -->
                                </div>
                            </div>
                            <div class="testimonial-card-1__paragraph mb-3">
                                {!!$testimonial->content!!}
                            </div>
                            <span class="testimonial-card-1__quote-symbol">
                                <i class="fas fa-quote-left"></i>
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif