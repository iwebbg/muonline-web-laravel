@extends('errors.layout')

@section('title', __('ui.errors.401.title'))
@section('code', '401')
@section('message', __('ui.errors.401.message'))
@section('description', __('ui.errors.401.description'))
@section('secondary_action_url', url('/login'))
@section('secondary_action_label', __('ui.errors.sign_in_action'))
