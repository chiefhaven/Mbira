<!------------------------------>
<!--Footer---------------->
<!------------------------------>
<div class="block-44">
    <hr class="block-44__divider">
    <div class="container">
        <div class="row flex-column flex-md-row px-2 justify-content-center">
            @if(isset($__site_details['follow_us']) && !empty($__site_details['follow_us']))
                <div class="flex-grow-1 col">
                    <ul class="block-44__extra-links d-flex list-unstyled p-0">
                        @foreach($__site_details['follow_us'] as $key => $follow_us)
                            @if($key == 'facebook' && !empty($follow_us))
                                <li class="mx-2">
                                    <a href="{{$follow_us??'#'}}" target="_blank" title="Facebook" class="block-44__link m-0">
                                        <i class="fab fa-facebook-f"></i>
                                    </a>
                                </li>
                            @endif
                            @if($key == 'instagram' && !empty($follow_us))
                                <li class="mx-2">
                                    <a href="{{$follow_us??'#'}}" target="_blank" title="Instagram" class="block-44__link m-0">
                                        <i class="fab fa-instagram"></i>
                                    </a>
                                </li>
                            @endif
                            @if($key == 'twitter' && !empty($follow_us))
                                <li class="mx-2">
                                    <a href="{{$follow_us??'#'}}" target="_blank" title="Twitter" class="block-44__link m-0">
                                        <i class="fab fa-twitter"></i>
                                    </a>
                                </li>
                            @endif
                            @if($key == 'linkedin' && !empty($follow_us))
                                <li class="mx-2">
                                    <a href="{{$follow_us??'#'}}" target="_blank" title="Linkedin" class="block-44__link m-0">
                                        <i class="fab fa-linkedin-in"></i>
                                    </a>
                                </li>
                            @endif
                            @if($key == 'youtube' && !empty($follow_us))
                                <li class="mx-2">
                                    <a href="{{$follow_us??'#'}}" target="_blank" title="YouTube" class="block-44__link m-0">
                                        <i class="fab fa-youtube"></i>
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            @endif
            <p class="block-41__copyrights col col-md-8 text-xxl-end text-xl-end text-lg-end text-md-end text-sm-center">
                &copy; &nbsp;{{ date('Y')}} &nbsp;{{config('app.name', 'ultimatePOS')}}. &nbsp;All Rights Reserved.
            </p>
        </div>
    </div>
  </div>
