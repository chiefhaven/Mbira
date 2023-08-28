<div class="pos-tab-content">
    <div class="row">
        <div class="col-md-6">
           <div class="form-group">
                {!! Form::label('follow_us_fb', 'Facebook' . ':' )!!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fab fa-facebook"></i>
                    </span>
                    {!! Form::url('follow_us[facebook]', !empty($details['follow_us']['facebook']) ? $details['follow_us']['facebook'] : '', ['class' => 'form-control', 'id' => 'follow_us_fb', 'placeholder' => 'https://www.facebook.com/company-page']) !!}
                </div>
           </div>
        </div>
        <div class="col-md-6">
           <div class="form-group">
                {!! Form::label('follow_us_insta', 'Instagram' . ':' )!!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fab fa-instagram"></i>
                    </span>
                    {!! Form::url('follow_us[instagram]', !empty($details['follow_us']['instagram']) ? $details['follow_us']['instagram'] : '', ['class' => 'form-control', 'id' => 'follow_us_insta', 'placeholder' => 'https://www.instagram.com/company']) !!}
                </div>
           </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
           <div class="form-group">
                {!! Form::label('follow_us_twtr', 'Twitter' . ':' )!!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fab fa-twitter"></i>
                    </span>
                    {!! Form::url('follow_us[twitter]', !empty($details['follow_us']['twitter']) ? $details['follow_us']['twitter'] : '', ['class' => 'form-control', 'id' => 'follow_us_twtr', 'placeholder' => 'https://twitter.com/company-id']) !!}
                </div>
           </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('follow_us_linkdin', 'LinkedIn' . ':' )!!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fab fa-linkedin-in"></i>
                    </span>
                    {!! Form::url('follow_us[linkedin]', !empty($details['follow_us']['linkedin']) ? $details['follow_us']['linkedin'] : '', ['class' => 'form-control', 'id' => 'follow_us_linkdin','placeholder' => 'https://in.linkedin.com/company/username']) !!}
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
           <div class="form-group">
                {!! Form::label('follow_us_YT', 'YouTube' . ':' )!!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fab fa-youtube"></i>
                    </span>
                    {!! Form::url('follow_us[youtube]', !empty($details['follow_us']['youtube']) ? $details['follow_us']['youtube'] : '', ['class' => 'form-control', 'id' => 'follow_us_YT', 'placeholder' => 'https://www.youtube.com/c/channel-name']) !!}
                </div>
           </div>
        </div>
    </div>
</div>