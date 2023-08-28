@if(!empty($faqs) && isset($faqs))
    <div class="block-39 space-between-blocks">
        <div class="container">
            <!-- HEADER -->
            <div class="col-lg-9 mx-auto text-center px-0 mb-5">
                <h1 class="block__title--big">Frequently Asked Questions</h1>
            </div>
            <div class="row px-2">
                @foreach($faqs as $faq)
                    <div class="col-lg-6">
                        <div class="content-block">
                            <h4 class="content-block__title">
                                {{$faq['question'] ?? ''}}
                            </h4>
                            <p class="content-block__paragraph">
                                {{$faq['answer'] ?? ''}}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div> 
@endif