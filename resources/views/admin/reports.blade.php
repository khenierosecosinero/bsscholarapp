@extends('layouts.admin')

@section('page-content')

@include('partials.admin-academic-year-selector', ['inputId' => 'admin-reports-year', 'title' => 'Academic Year'])

@include('partials.admin-regional-reports')

@endsection
