@php
    //filter out chat
    $chats = (isset($__site_details['chat']) && !empty($__site_details['chat']['whatsapp'])) ? $__site_details['chat']['whatsapp'] : [];
    $chats_collection = collect($chats);
    $filtered_chats = $chats_collection->filter(function ($value, $key) {
        return !empty($value);
    });

    //filter out contact us to be included in chat widget
    $contact_us = (isset($__site_details['chat']) && !empty($__site_details['chat']['mobile'])) ? $__site_details['chat']['mobile'] : [];
    $contact_us_collection = collect($contact_us);
    $filtered_contact = $contact_us_collection->filter(function ($value, $key) {
        return !empty($value);
    });

    //filter out mail us to be included in chat widget
    $mail_us = (isset($__site_details['chat']) && !empty($__site_details['chat']['mail'])) ? $__site_details['chat']['mail'] : [];
    $mail_us_collection = collect($mail_us);
    $filtered_mail = $mail_us_collection->filter(function ($value, $key) {
        return !empty($value);
    });

    $filtered_socials = [];
    if(isset($__site_details['chat']) && !empty($__site_details['chat']['messanger_link'])) {
        $filtered_socials['messanger_link'] = $__site_details['chat']['messanger_link'];
    }

    if(isset($__site_details['chat']) && !empty($__site_details['chat']['telegram'])) {
        $filtered_socials['telegram'] = $__site_details['chat']['telegram'];
    }
@endphp
<!-- Start chatWidget Style Container ( Required )  -->
@if(count($filtered_chats) > 0 || count($filtered_contact) > 0 || count($filtered_mail) > 0 || count($filtered_socials) > 0)
    <div class="container">
         
        <!-- Click To chatWidget Container --> 
        <div class="cp-style1 cp-right-bottom">
            
            <!-- Start Floating Panel Container -->
            <div class="cp-panel"> 
                
                <!-- Chat Button For Whatsapp -->
                 @if(count($filtered_chats) > 0)
                    @foreach($filtered_chats as $chat)
                        @if(!empty($chat))
                            <a class="cp-list cp-whatsapp" data-number="{{$chat}}" data-message="" target="_blank">
                                <div class="cp-image">
                                    <i class="fab fa-whatsapp text-white fa-2x mt-2"></i>
                                </div>  
                                <div class="cp-content">
                                    <h2>Whatsapp</h2>
                                    <p>
                                        Chat on Whatsapp
                                    </p>
                                </div>
                            </a>
                        @endif
                    @endforeach
                @endif

                <!-- Chat Button For Facebook Messenger -->
                @if(!empty($filtered_socials['messanger_link'])) 
                    <a class="cp-list" href="{{$filtered_socials['messanger_link']}}" target="_blank">
                        <div class="cp-image">
                            <i class="fab fa-facebook-messenger text-white fa-2x mt-2"></i>
                        </div>  
                        <div class="cp-content">
                            <h2>FB Messenger</h2>
                            <p>Get connected with Facebook</p>
                        </div>
                    </a>
                @endif

                <!-- Chat Button For Telegram -->
                @if(!empty($filtered_socials['telegram']))
                    <a class="cp-list" href="{{$filtered_socials['telegram']}}" target="_blank">
                        <div class="cp-image">
                            <i class="fab fa-telegram-plane text-white fa-2x mt-2"></i>
                        </div>  
                        <div class="cp-content">
                            <h2>Telegram</h2>
                            <p>Chat on Telegram</p>
                        </div>
                    </a>
                @endif

                <!-- Mail Button For Email -->
                @if(count($filtered_mail) > 0)
                    @foreach($filtered_mail as $chat)
                        <a class="cp-list" href="mailto:{{$chat}}" target="_blank">
                            <div class="cp-image">
                                <i class="far fa-envelope text-white fa-2x mt-2"></i>
                            </div>  
                            <div class="cp-content">
                                <h2>Email</h2>
                                <p>Send Email</p>
                            </div>
                        </a>
                    @endforeach
                @endif

                <!-- Call Button For Phone Number -->
                @if(count($filtered_contact) > 0)
                    @foreach($filtered_contact as $chat)
                        @if(!empty($chat))
                            <a class="cp-list" href="tel:+{{$chat}}" target="_blank">
                                <div class="cp-image">
                                    <i class="fas fa-phone-volume text-white fa-2x mt-2"></i>
                                </div>  
                                <div class="cp-content">
                                    <h2>Call</h2>
                                    <p>Talk with us over telephone</p>
                                </div>
                            </a>
                        @endif
                    @endforeach
                @endif

            </div>
            <!--/ End Floating Panel Container -->

            <!-- Start Right Floating Button -->
            <div class="cp-button cp-right-bottom">
                <i id="cp-cmt-o" class="far fa-comments fa-lg"></i>
            </div>
            <!--/ End Right Floating Button -->

        </div>
    </div>
@endif
<!--/ End chatWidget Style Container -->