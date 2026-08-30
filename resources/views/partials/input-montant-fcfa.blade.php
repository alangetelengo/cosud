{{--
  Champ montant FCFA avec espaces (ex. 1 949 700).
  Props : name, value, id?, required?, class?, placeholder?, label?, labelClass?, error?
--}}
@php
    $name = $name ?? 'montant';
    $value = \App\Support\MontantFcfa::pourSaisie($value ?? old($name));
    $id = $id ?? $name;
    $required = $required ?? false;
    $class = $class ?? 'w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900';
    $placeholder = $placeholder ?? 'Ex. : 1 949 700';
    $label = $label ?? null;
    $labelClass = $labelClass ?? 'block text-xs font-semibold mb-1';
    $error = $error ?? $name;
@endphp
@if($label)
<label for="{{ $id }}" class="{{ $labelClass }}">
    {{ $label }}
    @if($required)<span class="text-red-500">*</span>@endif
</label>
@endif
<div x-data="montantFcfa(@js($value))">
    <input
        type="text"
        name="{{ $name }}"
        id="{{ $id }}"
        x-model="montant"
        @input="montant = format($event.target.value)"
        inputmode="numeric"
        autocomplete="off"
        @if($required) required @endif
        class="{{ $class }}"
        placeholder="{{ $placeholder }}"
    >
</div>
@error($error)<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
