@extends('spreadsheet::layouts.app')
@section('title', __('spreadsheet::lang.view_spreadsheet'))
@section('content')
<!-- Content Header (Page header) -->
<section class="content-header no-print">
    <h1>
    	@lang('spreadsheet::lang.view_spreadsheet')
    </h1>
</section>
<!-- Main content -->
<section class="content no-print">
    <div class="container-fluid">
    	<div class="row">
            <div class="col-xs-4 col-sm-4 col-md-4 col-lg-4">
                @php
                    $redirect = action([\Modules\Spreadsheet\Http\Controllers\SpreadsheetController::class, 'index']);
                    if ($spreadsheet->created_by != auth()->user()->id) {
                        $redirect = action([\Modules\Essentials\Http\Controllers\ToDoController::class, 'index']);
                    }
                @endphp

                <a href="{{$redirect}}"
                    class="btn btn-warning btn-lg">
                    <i class="fas fa-chevron-left"></i>
                    @lang('messages.go_back')
                </a>
            </div>
            @if($spreadsheet->created_by == auth()->user()->id)
                <div class="col-xs-4 col-sm-4 col-md-4 col-lg-4">
                    <p>
                        <input class="font-17" type="file" id="import-excel-file" name="file_name" change="spreadsheetHandler"/> 
                    </p>
                </div>
            @endif
            @if(auth()->user()->can('superadmin') || auth()->user()->can('create.spreadsheet'))
            <div class="col-xs-4 col-sm-4 col-md-4 col-lg-4">
                {!! Form::open(['url' => action([\Modules\Spreadsheet\Http\Controllers\SpreadsheetController::class, 'update'], $spreadsheet->id), 'id' => 'spreadsheet', 'method' => 'put']) !!}
                        <button type="submit" class="btn btn-primary btn-lg pull-right">
                            <i class="fas fa-save"></i>
                            @lang('messages.update')
                        </button>
                    <input type="hidden" name="name" id="name" value="">
                    <input type="hidden" name="sheet_data" id="spread_sheet_data" value="">
                  {!! Form::close() !!}
            </div>
            @endif
    		<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
    			<div id="my_spreadsheet" class="spreadsheet"></div>
    		</div>
    	</div>
    </div>
</section>
@stop
@section('javascript')
<script type="text/javascript">
	$(function () {
		luckysheet.create({
            container: 'my_spreadsheet',
            showinfobar: true,
            title: "{{$spreadsheet->name ?? ''}}",
            lang: 'en',
            allowEdit: true,
            forceCalculation: false,
            data: {!!$spreadsheet->sheet_data!!}
        });

        function spreadsheetHandler () {
        	let upload = document.getElementById("import-excel-file");
        	if(upload){
                
                window.onload = () => {
                    
                    upload.addEventListener("change", function(evt){
                        var files = evt.target.files;
                        if(files==null || files.length==0){
                            alert("No files wait for import");
                            return;
                        }

                        let name = files[0].name;
                        let suffixArr = name.split("."), suffix = suffixArr[suffixArr.length-1];
                        if(suffix!="xlsx"){
                            alert("Currently only supports the import of xlsx files");
                            return;
                        }
                        LuckyExcel.transformExcelToLucky(files[0], function(exportJson, luckysheetfile){
                            if(exportJson.sheets==null || exportJson.sheets.length==0){
                                alert("Failed to read the content of the excel file, currently does not support xls files!");
                                return;
                            }
                            // console.log(exportJson, luckysheetfile);
                            window.luckysheet.destroy();
                            window.luckysheet.create({
                                container: 'my_spreadsheet', //my_spreadsheet is the container id
                                data:exportJson.sheets,
                                title:exportJson.info.name,
                                userInfo:exportJson.info.name.creator
                            });
                        });
                    });
                }
            }
        }

        spreadsheetHandler();

        $("form#spreadsheet").on('submit', function () {
        	$("input#name").val(luckysheet.toJson().title);
            $("input#spread_sheet_data").val(JSON.stringify(luckysheet.getAllSheets()));
        });
    });
</script>
@endsection