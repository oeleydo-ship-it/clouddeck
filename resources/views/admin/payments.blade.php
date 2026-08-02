@extends('layouts.admin')
@section('admin-title', 'Payments')
@section('admin-description', 'Stripe connection, price mapping, and webhook delivery.')
@section('admin')
    @include('admin.partials.stripe')
@endsection
