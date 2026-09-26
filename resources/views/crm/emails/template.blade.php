@extends('emails.layouts.branded', ['title' => $crmEmail->Subject])

@section('content')
    {!! $crmEmail->Body !!}
@endsection

@section('signature')
    @if ($crmEmail->creator?->Email_Signature)
        <div style="margin-top:20px;">{!! $crmEmail->creator->Email_Signature !!}</div>
    @endif
@endsection
