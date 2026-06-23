@extends('frontend.layout')

@section('title', __('seo.pages.security.title'))
@section('description', __('seo.pages.security.description'))

@section('content')
    @include('frontend.partials.legal-document', ['document' => 'security'])
@endsection
