@extends('layout.main')

@section('content')

<div class="col-xs-12">

	<div class="row">
		<div class="col-xs-12">
			<h1 class="text-center">Your Download is ready!</h1>
		</div>
	</div>

	<div class="row text-center">
		<div class="col-sm-8 col-sm-offset-2">
			<a href="{{ $path }}" class="btn btn-xxl btn-success">Download CSV-File</a>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-xs-12">
		<a href="{{ URL::route('dashboard') }}">&laquo; Back to Dashboard</a>
	</div>
</div>

@stop
