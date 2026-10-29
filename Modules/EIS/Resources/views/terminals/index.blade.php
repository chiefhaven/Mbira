@extends('layouts.app')
@section('title', 'MRA EIS terminals')
@section('content')
@include('eis::shared.navigation')
<section class="content-header eis-terminals-page-header">
    <div class="eis-terminals-eyebrow"><span></span> MRA EIS <b>/</b> TERMINAL ADMINISTRATION</div>
    <h1>Terminal management</h1>
    <p>Activate terminals and manage configuration, tokens, and location defaults.</p>
</section>
<section class="content eis-terminals-page">
    @include('business.partials.settings_mra_eis', ['standalone' => true])
</section>
@endsection
@section('css')
    @include('eis::shared.styles')
    <style>
        .eis-terminals-page-header {
            padding-top: 20px;
            padding-bottom: 12px;
        }
        .eis-terminals-page-header h1 {
            margin: 8px 0 6px;
            color: #172b4d;
            font-size: 24px;
        }
        .eis-terminals-page-header > p {
            margin: 0;
            color: #66758a;
            font-size: 14px;
        }
        .eis-terminals-eyebrow {
            color: #526782;
            font-size: 12px;
            font-weight: 700;
        }
        .eis-terminals-eyebrow span {
            display: inline-block;
            width: 7px;
            height: 7px;
            margin-right: 5px;
            border-radius: 50%;
            background: #3276b1;
            vertical-align: 1px;
        }
        .eis-terminals-eyebrow b {
            padding: 0 4px;
            color: #9aa8b8;
            font-weight: 400;
        }
        .eis-terminals-page .eis-terminal-management {
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-top: 3px solid #3276b1;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 5px 18px rgba(28, 48, 74, .06);
        }
        .eis-terminals-page .eis-terminal-management > .box-header {
            padding: 20px 24px 16px;
            border-bottom: 1px solid #edf1f5;
        }
        .eis-terminals-section-title {
            margin: 0;
            color: #24364b;
            font-size: 18px;
        }
        .eis-terminals-section-description {
            margin: 6px 0 0;
            color: #718096;
            font-size: 14px;
        }
        .eis-terminals-page .eis-terminal-management > .box-body {
            padding: 22px 24px 24px;
        }
        .eis-terminals-page .eis-terminal-activation {
            margin-bottom: 22px;
            padding: 18px 20px 8px;
            border: 1px solid #e3ebf5;
            border-radius: 8px;
            background: #f7faff;
        }
        .eis-terminals-activation-heading {
            margin: 0 0 5px;
            color: #24364b;
            font-size: 18px;
        }
        .eis-terminals-activation-help {
            margin: 0 0 15px;
            color: #718096;
            font-size: 12px;
        }
        .eis-terminals-page .eis-terminal-activation .form-group {
            margin-bottom: 12px;
        }
        .eis-terminals-page .eis-terminal-activation label {
            color: #42536a;
            font-size: 12px;
            font-weight: 600;
        }
        .eis-terminals-page .eis-terminal-activation .form-control {
            height: 40px;
            border-color: #d7e0eb;
            border-radius: 5px;
            box-shadow: none;
        }
        .eis-terminals-page .eis-terminal-activation .form-control:focus {
            border-color: #6a9bc9;
            box-shadow: 0 0 0 3px rgba(50, 118, 177, .12);
        }
        .eis-terminals-page .eis-terminal-activation-actions {
            padding-top: 25px;
        }
        .eis-terminals-page .eis-terminal-activation-actions .btn {
            min-height: 38px;
            margin: 0 6px 8px 0;
            padding: 8px 14px;
            border-radius: 5px;
            font-weight: 600;
        }
        .eis-terminals-page .eis-terminal-activation-actions .btn-success {
            border-color: #26734d;
            background: #26734d;
        }
        .eis-terminals-page .eis-terminal-table-wrap {
            border: 1px solid #e7ecf2;
            border-radius: 7px;
        }
        .eis-terminals-page .eis-terminal-table {
            margin-bottom: 0;
        }
        .eis-terminals-page .eis-terminal-table > thead > tr > th {
            padding: 13px 14px;
            border-bottom: 1px solid #e1e7ef;
            background: #f6f8fb;
            color: #586a81;
            font-size: 14px;
            white-space: nowrap;
        }
        .eis-terminals-page .eis-terminal-table > tbody > tr > td {
            padding: 13px 14px;
            border-color: #edf1f5;
            color: #34465c;
            vertical-align: middle;
        }
        .eis-terminals-page .eis-terminal-table > tbody > tr:hover > td {
            background: #f9fbfe;
        }
        .eis-terminals-page .eis-terminal-table .eis-terminal-action {
            margin: 2px 3px 2px 0;
            padding: 5px 9px;
            border-color: #d9e2ec;
            border-radius: 4px;
            color: #40546c;
        }
        .eis-terminals-page .eis-terminal-table .eis-terminal-action:hover {
            border-color: #9ab7d4;
            background: #f2f7fc;
            color: #235d91;
        }
        .eis-terminals-page .eis-management-note {
            margin: 12px 0 0;
            color: #748399;
            font-size: 12px;
            line-height: 1.6;
        }
        .eis-terminals-page #eis-management-message:not(:empty) {
            margin-bottom: 16px;
            border-radius: 5px;
        }
        .eis-terminals-page #eis-configuration-panel {
            margin-top: 24px;
            border: 1px solid #e3e9f0;
            border-radius: 7px;
            box-shadow: none;
        }
        .eis-terminals-page #eis-configuration-panel > .box-header {
            padding: 14px 18px;
            border-bottom: 1px solid #edf1f5;
        }
        .eis-terminals-page #eis-configuration-panel .box-body {
            padding: 16px 18px;
        }
        .eis-terminals-page #eis-configuration-panel .box-body > p {
            color: #6c7b8e;
            font-size: 12px;
            line-height: 1.6;
        }
        @media (max-width: 767px) {
            .eis-terminals-page .eis-terminal-management > .box-header,
            .eis-terminals-page .eis-terminal-management > .box-body {
                padding-right: 16px;
                padding-left: 16px;
            }
            .eis-terminals-page .eis-terminal-activation {
                padding: 15px 14px 5px;
            }
            .eis-terminals-page .eis-terminal-activation-actions {
                padding-top: 0;
            }
        }
    </style>
@endsection
@section('javascript')
    @include('eis::terminals.script')
@endsection
