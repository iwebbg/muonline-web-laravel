@extends('errors.layout')

@section('title', __('ui.errors.419.title'))
@section('code', '419')
@section('message', __('ui.errors.419.message'))
@section('description', __('ui.errors.419.description'))
@section('secondary_action_url', url('/login'))
@section('secondary_action_label', __('ui.errors.sign_in_action'))
