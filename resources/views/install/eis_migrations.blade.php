@extends('layouts.app')
@section('title', 'EIS Database Migrations')

@section('content')
<section class="content-header">
    <h1>EIS Database Migrations</h1>
</section>
<section class="content">
    @if(session('status'))
        <div class="alert {{ session('status.success') ? 'alert-success' : 'alert-danger' }}" role="alert">
            {{ session('status.msg') }}
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    @component('components.widget')
        <p>Apply the contact authorization code, POS relief flag, and VAT5 certificate storage updates.</p>
        <div class="alert alert-warning">
            Database changes affect all businesses. Back up the database and stop sales/payment activity before continuing.
            Only the three migrations below will run. This action does not reset the database or run unrelated migrations.
        </div>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead><tr><th>Migration</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($migrations as $migration)
                    <tr>
                        <td>{{ $migration['name'] }}</td>
                        <td><span class="label {{ $migration['applied'] ? 'label-success' : 'label-warning' }}">
                            {{ $migration['applied'] ? 'Applied' : 'Pending' }}
                        </span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <form method="POST" action="{{ route('eis-migrations.store') }}"
            onsubmit="if (!confirm('Apply the listed EIS migrations to the database?')) return false; this.querySelector('button[type=submit]').disabled = true;">
            @csrf
            <div class="checkbox">
                <label><input type="checkbox" name="confirm_backup" value="1" required>
                    I have backed up the database and stopped sales/payment activity.
                </label>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-database"></i> Run EIS Migrations</button>
        </form>
    @endcomponent
</section>
@endsection