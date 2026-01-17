@props(['code', 'size' => 'text-2xl'])

<span {{ $attributes->merge(['class' => $size]) }}>{{ App\Helpers\FlagHelper::toEmoji($code) }}</span>
