@extends('frontend.layout')

@section('title', __('seo.pages.terms.title'))
@section('description', __('seo.pages.terms.description'))

@section('content')
    @include('frontend.partials.legal-document', ['document' => 'terms'])
@endsection
