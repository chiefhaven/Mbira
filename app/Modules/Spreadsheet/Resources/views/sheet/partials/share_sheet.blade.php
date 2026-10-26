<div class="modal-dialog" role="document">
    {!! Form::open(['action' => '\Modules\Spreadsheet\Http\Controllers\SpreadsheetController@postShareSpreadsheet', 'id' => 'share_spreadsheet', 'method' => 'post']) !!}
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="myModalLabel">
                    @lang('spreadsheet::lang.share_excel')
                </h4>
            </div>
            <div class="modal-body">
                <input type="hidden" name="sheet_id" value="{{$id}}">
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            {!! Form::label('todo', __('spreadsheet::lang.todos') .':') !!}
                            {!! Form::select('share[todo][]', $todos, $shared_todos, ['class' => 'form-control select2', 'multiple', 'style' => 'width: 100%;']); !!}
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            {!! Form::label('user', __('role.user').':') !!} <br>
                            {!! Form::select('share[user][]', $users, $shared_users, ['class' => 'form-control select2', 'multiple' => 'multiple', 'style'=>"width: 100%;"]); !!}
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            {!! Form::label('role', __('user.role').':') !!} <br>
                            {!! Form::select('share[role][]', $roles, $shared_roles, ['class' => 'form-control select2', 'multiple' => 'multiple', 'style'=>"width: 100%;"]); !!}
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    @lang('messages.close')
                </button>
                <button type="submit" class="btn btn-primary ladda-button">
                    @lang('messages.submit')
                </button>
            </div>
        </div>
    {!! Form::close() !!}
  </div>
  <script type="text/javascript">
  $(document).ready(function(){
    __select2($('.select2'));
  })
</script>