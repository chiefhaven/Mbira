@extends('spreadsheet::layouts.app')
@section('title', __('spreadsheet::lang.create_spreadsheet'))
@section('content')
<!-- Content Header (Page header) -->
<section class="content-header no-print">
    <h1>
    	@lang('spreadsheet::lang.create_spreadsheet')
    </h1>
</section>
<!-- Main content -->
<section class="content no-print">
	 <div class="container-fluid">
        <div class="row">
            <div class="col-xs-4 col-sm-4 col-md-4 col-lg-4">
                <a href="{{action([\Modules\Spreadsheet\Http\Controllers\SpreadsheetController::class, 'index'])}}"
                    class="btn btn-warning btn-lg">
                    <i class="fas fa-chevron-left"></i>
                    @lang('messages.go_back')
                </a>
            </div>
            <div class="col-xs-4 col-sm-4 col-md-4 col-lg-4">
                <p>
                    <input type="file" class="font-17" id="import-excel-file" name="file_name" change="spreadsheetHandler"/> 
                </p>
            </div>
            <div class="col-xs-4 col-sm-4 col-md-4 col-lg-4">
               {!! Form::open(['action' => '\Modules\Spreadsheet\Http\Controllers\SpreadsheetController@store', 'id' => 'spreadsheet', 'method' => 'post']) !!}
                        <button type="submit" class="btn btn-primary btn-lg pull-right">
                            <i class="fas fa-save"></i>
                            @lang('messages.save')
                        </button>
                    <input type="hidden" name="name" id="name" value="">
                    <input type="hidden" name="sheet_data" id="spread_sheet_data" value="">
                    <input type="hidden" name="folder_id" value="{{$folder_id}}">
                {!! Form::close() !!}

                <button type="button" class="btn btn-primary btn-lg pull-right downloadSheet" style="margin-right: 5px;" onclick="alert('Feature Under Development')">
                <i class="fas fa-download"></i>
                    @lang('lang_v1.download')
                </button>

            </div>
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
            title: 'My Spreadsheet',
            lang: 'en',
            allowEdit: true,
            forceCalculation: false,
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
                                // showinfobar:false,
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