@extends('errors.layout')
@section('code', '419')
@section('title', 'Page expired')
@section('message', 'Your session timed out before the form was sent. Go back, refresh the page and try again.')
@section('actions')
    <a href="javascript:history.back()" class="btn">Go back</a>
    <a href="/" class="btn primary">Back to home</a>
@endsection
