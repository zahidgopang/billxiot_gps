@extends('frontend.layout')

@section('title', __('seo.pages.cookies.title'))
@section('description', __('seo.pages.cookies.description'))

@section('content')
    @include('frontend.partials.legal-document', ['document' => 'cookies'])
@endsection
