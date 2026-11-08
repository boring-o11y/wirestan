<div x-data="{ a: $wire.$entangle('viaEntangle'), b: @entangle('viaEntangleDirective') }">
    {{-- wire:model="commentedOut" --}}
    <!-- <input wire:model="commentedOut"> -->
    <input wire:model="title">
    <input wire:model.live.debounce.300ms="search">
    <input wire:model="rows.{{ $i }}.name">
    <input wire:model='singleQuoted'>
    <input wire:model="$parent.parentsProperty">
    <input x-model="$wire.viaAlpineModel">
    <button wire:click="$set('viaSet', 'a')"></button>
    <button wire:click="$toggle('viaToggle')"></button>
    <button wire:click="viaEventExpression++"></button>
    <button x-on:click="$wire.set('viaWireSet', 1); $wire.viaAssignment = 'x'; $wire.viaNestedAssignment.a.b = 1"></button>
    <input type="file" x-on:change="$wire.upload('viaUpload', $event.target.files[0])">
    <p>{{ $onlyDisplayed }} {{ $wire.viaAssignment == 1 }}</p>
    {{-- A long path used to exhaust PCRE's backtrack limit and lose every assignment in the view. --}}
    <p x-text="$wire.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment.segment"></p>
</div>
