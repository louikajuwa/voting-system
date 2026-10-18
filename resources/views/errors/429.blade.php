@extends('errors.layout')
@section('code', '429')
@section('title', 'Too many requests')
@section('message', 'You have made too many attempts. Wait a minute and try again.')
@section('actions')
    <a href="javascript:history.back()" class="btn">Go back</a>
    <a href="/" class="btn primary">Back to home</a>
@endsection
