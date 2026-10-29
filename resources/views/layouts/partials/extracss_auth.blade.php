   <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <style>
        /* ================================
           BACKGROUND OPTIONS
           ================================ */
         /* Note: Only uncomment one of the options below. */
        /* OPTION 1: Flat background */
        html {
            height: 100%;
            background: #ffffff;
        }

        /* OPTION 2: Background Image */
        /* Uncomment below for image background */
        /* html {
            height: 100%;
            background-image: url('{{ asset('img/bg-image.jpg') }}');
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
        } */

        /* Common Body Styles (Always Keep This) */
        body {
            min-height: 100vh;
            background: #ffffff;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', sans-serif;
        }

        h1 {
            color: #111827;
        }

        .login-shell {
            min-height: calc(100vh - 110px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 0;
        }

        .login-grid {
            width: min(100%, 440px);
            display: block;
        }

        .login-side-panel,
        .login-side-placeholder {
            display: none;
        }

        .login-main-panel {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }

        .login-card {
            width: 100%;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            box-shadow: none;
            padding: 22px 20px 16px;
        }

        .login-header-block {
            margin-bottom: 18px;
            text-align: left;
        }

        .login-header-block h1 {
            margin: 0 0 6px;
            color: #111827;
            font-size: 1.7rem;
            font-weight: 700;
        }

        .login-header-block h2 {
            margin: 0;
            font-size: 0.9rem;
            color: #6b7280;
            font-weight: 500;
        }

        .login-support-text {
            display: block;
            margin-top: 14px;
            text-align: center;
            font-size: 11px;
            color: #6b7280;
        }

        .login-support-text a {
            color: #1f2937;
            font-weight: 600;
            text-decoration: none;
        }
    </style>

    <style type="text/css">
        /*
      * Pattern lock css
      * Pattern direction
      * http://ignitersworld.com/lab/patternLock.html
      */
        .patt-wrap {
            z-index: 10;
        }

        .patt-circ.hovered {
            background-color: #cde2f2;
            border: none;
        }

        .patt-circ.hovered .patt-dots {
            display: none;
        }

        .patt-circ.dir {
            background-image: url("http://pos.test/img/pattern-directionicon-arrow.png");
            background-position: center;
            background-repeat: no-repeat;
        }

        .patt-circ.e {
            -webkit-transform: rotate(0);
            transform: rotate(0);
        }

        .patt-circ.s-e {
            -webkit-transform: rotate(45deg);
            transform: rotate(45deg);
        }

        .patt-circ.s {
            -webkit-transform: rotate(90deg);
            transform: rotate(90deg);
        }

        .patt-circ.s-w {
            -webkit-transform: rotate(135deg);
            transform: rotate(135deg);
        }

        .patt-circ.w {
            -webkit-transform: rotate(180deg);
            transform: rotate(180deg);
        }

        .patt-circ.n-w {
            -webkit-transform: rotate(225deg);
            transform: rotate(225deg);
        }

        .patt-circ.n {
            -webkit-transform: rotate(270deg);
            transform: rotate(270deg);
        }

        .patt-circ.n-e {
            -webkit-transform: rotate(315deg);
            transform: rotate(315deg);
        }
    </style>
    <style>
        h1 {
            color: #111827;
        }
    </style>
    <style>
        .action-link[data-v-1552a5b6] {
            cursor: pointer;
        }
    </style>
    <style>
        .action-link[data-v-397d14ca] {
            cursor: pointer;
        }
    </style>
    <style>
        .action-link[data-v-49962cc0] {
            cursor: pointer;
        }
    </style>

<link href="{{ asset('css/tailwind/app.css') }}" rel="stylesheet">
