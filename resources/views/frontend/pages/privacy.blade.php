@extends('frontend.layout')

@section('title', __('seo.pages.privacy.title'))
@section('description', __('seo.pages.privacy.description'))

@section('content')
    @include('frontend.partials.legal-document', ['document' => 'privacy'])
@endsection
