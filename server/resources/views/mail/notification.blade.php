<x-mail::message>
{{-- Same layout as Laravel's notifications::email, with every fixed text passed in by MailText (Thai or English) --}}
# {{ $greeting }}

@foreach ($introLines as $line)
{{ $line }}

@endforeach

@isset($actionText)
<x-mail::button :url="$actionUrl" color="primary">
{{ $actionText }}
</x-mail::button>
@endisset

@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{ $salutation }}<br>
{{ config('app.name') }}

@isset($actionText)
<x-slot:subcopy>
{{ $subcopyText }} <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
